<?php

namespace App\Console\Commands;

use App\Models\WaitingListEntry;
use App\Services\VatsimActivityService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncWaitingListActivity extends Command
{
    protected $signature = 'waitinglist:sync-activity 
                            {--limit=1 : Number of entries to update per run}
                            {--force : Force update all entries}';

    protected $description = 'Sync waiting list activity from VATSIM (default: updates 1 entry per run)';

    protected VatsimActivityService $activityService;

    public function __construct(VatsimActivityService $activityService)
    {
        parent::__construct();
        $this->activityService = $activityService;
    }

    public function handle(): int
    {
        $this->info('Starting waiting list activity sync...');

        try {
            if ($this->option('force')) {
                $this->updateAllActivities();
            } else {
                $this->updateStaleActivities();
            }

            $this->info('Waiting list activity sync completed successfully.');
            return 0;
        } catch (\Exception $e) {
            $this->error('Error during sync: ' . $e->getMessage());
            Log::error('Waiting list activity sync error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Update activities for entries that need updating (oldest first)
     */
    protected function updateStaleActivities(): void
    {
        $limit = (int) $this->option('limit');
        
        // Get RTG courses only, ordered by oldest hours_updated
        $entries = WaitingListEntry::whereHas('course', function ($query) {
            $query->where('type', 'RTG');
        })
        ->orderBy('hours_updated', 'asc')
        ->limit($limit)
        ->get();

        if ($entries->isEmpty()) {
            $this->info('No waiting list entries need updating');
            return;
        }

        $this->info("Updating activity for {$entries->count()} entry/entries...");

        foreach ($entries as $entry) {
            $this->updateEntryActivity($entry);
        }
    }

    /**
     * Update all waiting list activities
     */
    protected function updateAllActivities(): void
    {
        $this->info("Force updating all RTG waiting list activities...");
        
        $totalCount = WaitingListEntry::whereHas('course', function ($query) {
            $query->where('type', 'RTG');
        })->count();
        
        $processedCount = 0;

        $this->info("Total entries to process: {$totalCount}");
        
        WaitingListEntry::whereHas('course', function ($query) {
            $query->where('type', 'RTG');
        })
        ->orderBy('hours_updated', 'asc')
        ->chunk(50, function ($entries) use (&$processedCount, $totalCount) {
            $this->info("Processing batch starting at entry " . ($processedCount + 1));
            
            foreach ($entries as $entry) {
                $this->updateEntryActivity($entry);
                $processedCount++;
                
                if ($processedCount % 10 === 0) {
                    $this->info("Progress: {$processedCount}/{$totalCount}");
                }
            }
            
            $this->info("Batch complete. Waiting 2 seconds before next batch...");
            sleep(2);
        });

        $this->info("Completed updating {$processedCount} entries.");
    }

    /**
     * Update activity for a specific waiting list entry
     */
    protected function updateEntryActivity(WaitingListEntry $entry): void
    {
        try {
            $course = $entry->course;
            $user = $entry->user;

            if (!$user->isVatsimUser()) {
                $this->warn("Skipping non-VATSIM user: {$user->id}");
                return;
            }

            // Calculate required hours based on position
            $activityHours = $this->getActivityHours($course, $user);

            // Update the entry
            $entry->activity = $activityHours;
            $entry->hours_updated = now();
            $entry->save();

            $this->line("Updated {$course->name} for user {$user->vatsim_id}: {$activityHours} hours");

        } catch (\Exception $e) {
            $this->error("Failed to update entry {$entry->id}: " . $e->getMessage());
            Log::error('Failed to update waiting list activity', [
                'entry_id' => $entry->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get activity hours for a user based on course position
     * This matches the Python logic from check_waiting_list.py
     */
    protected function getActivityHours($course, $user): float
    {
        $airport = $course->airport_icao;
        $position = $course->position;
        $fir = substr($course->mentorGroup->name, 0, 4); // Extract FIR from mentor group (e.g., "EDGG Mentor" -> "EDGG")

        // Get connections from last 60 days
        $start = Carbon::now()->subDays(60)->format('Y-m-d');
        $apiUrl = "https://api.vatsim.net/api/ratings/{$user->vatsim_id}/atcsessions/?start={$start}";

        try {
            $response = \Http::timeout(15)->retry(2, 1000)->get($apiUrl);
            
            if (!$response->successful()) {
                Log::warning('VATSIM API request failed for waiting list', [
                    'vatsim_id' => $user->vatsim_id,
                    'status' => $response->status()
                ]);
                return -1;
            }

            $connections = $response->json()['results'] ?? [];

            return match($position) {
                'GND', 'TWR' => $this->calculateS1TowerHours($connections, $fir),
                'APP' => $this->calculateAppHours($connections, $airport),
                'CTR' => 10, // TODO: Implement CTR logic
                default => -1
            };

        } catch (\Exception $e) {
            Log::error('Error fetching VATSIM connections for waiting list', [
                'vatsim_id' => $user->vatsim_id,
                'error' => $e->getMessage()
            ]);
            return -1;
        }
    }

    /**
     * Calculate S1 Tower hours (for GND/TWR positions)
     */
    protected function calculateS1TowerHours(array $connections, string $fir): float
    {
        // Get S1 tower stations from datahub
        $url = "https://raw.githubusercontent.com/VATGER-Nav/datahub/refs/heads/production/api/{$fir}/twr.json";
        
        try {
            $response = \Http::get($url);
            if (!$response->successful()) {
                Log::warning("Failed to fetch datahub for {$fir}");
                return 0;
            }

            $hub = $response->json();
            
            // Filter for S1 tower stations (excluding I_ positions)
            $stations = collect($hub)
                ->filter(function ($station) {
                    return isset($station['s1_twr']) 
                        && $station['s1_twr'] === true
                        && !str_contains($station['logon'] ?? '', '_I_');
                })
                ->pluck('logon')
                ->toArray();

            // Calculate hours
            $totalMinutes = 0;
            foreach ($connections as $session) {
                $callsign = $session['callsign'] ?? '';
                
                foreach ($stations as $station) {
                    if ($this->equalStr($callsign, $station)) {
                        $totalMinutes += floatval($session['minutes_on_callsign'] ?? 0);
                        break;
                    }
                }
            }

            return $totalMinutes / 60;

        } catch (\Exception $e) {
            Log::error('Error calculating S1 tower hours', [
                'fir' => $fir,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Calculate APP hours (based on TWR sessions)
     */
    protected function calculateAppHours(array $connections, string $airport): float
    {
        $totalMinutes = 0;
        
        foreach ($connections as $session) {
            $callsign = $session['callsign'] ?? '';
            
            if ($this->equalStr($callsign, "{$airport}_TWR")) {
                $totalMinutes += floatval($session['minutes_on_callsign'] ?? 0);
            }
        }

        return $totalMinutes / 60;
    }

    /**
     * Check if two callsigns match (matching Python logic)
     * Compares airport code and suffix, ignoring middle parts
     */
    protected function equalStr(string $a, string $b): bool
    {
        $partsA = explode('_', $a);
        $partsB = explode('_', $b);
        
        if (empty($partsA) || empty($partsB)) {
            return false;
        }

        return $partsA[0] === $partsB[0] && end($partsA) === end($partsB);
    }
}