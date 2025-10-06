<?php

namespace App\Console\Commands;

use App\Models\RosterEntry;
use App\Models\WaitingListEntry;
use App\Services\VatEudService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckRosterStatus extends Command
{
    protected $signature = 'roster:check';
    protected $description = 'Check roster status and remove inactive users';

    protected VatEudService $vatEudService;

    public function __construct(VatEudService $vatEudService)
    {
        parent::__construct();
        $this->vatEudService = $vatEudService;
    }

    public function handle(): int
    {
        $this->info('Starting roster check...');

        try {
            // Get current roster from VatEUD
            $roster = $this->getRoster();
            
            if (empty($roster)) {
                $this->error('Failed to fetch roster from VatEUD');
                return 1;
            }

            $this->info('Found ' . count($roster) . ' users on roster');

            foreach ($roster as $vatsimId) {
                $this->checkUser($vatsimId);
            }

            $this->info('Roster check completed successfully.');
            return 0;

        } catch (\Exception $e) {
            $this->error('Error during roster check: ' . $e->getMessage());
            Log::error('Roster check error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Get roster from VatEUD
     */
    protected function getRoster(): array
    {
        try {
            $response = Http::withHeaders([
                'X-API-KEY' => config('services.vateud.token'),
                'Accept' => 'application/json',
                'User-Agent' => 'VATGER Training System',
            ])->get('https://core.vateud.net/api/facility/roster');

            if ($response->successful()) {
                $data = $response->json();
                return $data['data']['controllers'] ?? [];
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch roster from VatEUD', ['error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Check individual user on roster
     */
    protected function checkUser(int $vatsimId): void
    {
        try {
            // Get or create roster entry
            $entry = RosterEntry::firstOrCreate(
                ['user_id' => $vatsimId],
                [
                    'last_session' => Carbon::createFromTimestamp(0),
                    'removal_date' => null
                ]
            );

            // Ensure last_session has timezone
            if ($entry->last_session && !$entry->last_session->timezone) {
                $entry->last_session = Carbon::parse($entry->last_session)->timezone('UTC');
                $entry->save();
            }

            // If last session is recent (within 11 months), skip further checks
            if ($entry->last_session && now()->diffInDays($entry->last_session) < (11 * 30)) {
                return;
            }

            // Fetch last session from VATSIM
            try {
                $lastSession = $this->getLastSession($vatsimId);
                $entry->last_session = $lastSession;
                $entry->save();
            } catch (\Exception $e) {
                $this->warn("Error getting last session for {$vatsimId}: " . $e->getMessage());
                return;
            }

            // Check if user should be removed (>366 days inactive)
            if ($entry->last_session->lt(now()->subDays(366))) {
                if ($entry->removal_date && $entry->removal_date->lt(now())) {
                    // Time to remove
                    $this->removeFromRoster($vatsimId);
                    $entry->delete();
                    return;
                }
            }

            // Check if user is inactive for 11 months
            if ($entry->last_session->lt(now()->subDays(11 * 30))) {
                // Check if S1 rating was obtained within 11 months
                try {
                    [$isRecentS1, $ratingChangeDate] = $this->checkS1Status($vatsimId);
                    
                    if ($isRecentS1) {
                        // User got S1 recently, update last session and clear removal date
                        $entry->last_session = $ratingChangeDate;
                        $entry->removal_date = null;
                        $entry->save();
                        return;
                    }
                } catch (\Exception $e) {
                    $this->warn("Error checking rating for {$vatsimId}: " . $e->getMessage());
                }

                // Set removal date if not already set
                if (!$entry->removal_date) {
                    $this->sendRemovalWarning($vatsimId);
                    $entry->removal_date = now()->addDays(35);
                    $entry->save();
                    $this->info("Set removal date for {$vatsimId}: " . $entry->removal_date->format('Y-m-d'));
                }
            } else {
                // User is active, clear removal date
                if ($entry->removal_date) {
                    $entry->removal_date = null;
                    $entry->save();
                }
            }

        } catch (\Exception $e) {
            $this->error("Error checking user {$vatsimId}: " . $e->getMessage());
            Log::error('Error in roster check for user', [
                'vatsim_id' => $vatsimId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get last session for a user in Germany (ED/ET airports)
     */
    protected function getLastSession(int $vatsimId): Carbon
    {
        $date = now()->subDays(365);
        $apiUrl = "https://api.vatsim.net/api/ratings/{$vatsimId}/atcsessions/?start={$date->format('Y-m-d')}";

        try {
            $response = Http::timeout(15)->get($apiUrl);
            
            if (!$response->successful()) {
                throw new \Exception("API request failed with status: " . $response->status());
            }

            $connections = $response->json()['results'] ?? [];
            
            // Find most recent German session (callsign starts with ED or ET)
            foreach ($connections as $connection) {
                $callsign = $connection['callsign'] ?? '';
                $prefix = substr($callsign, 0, 2);
                
                if (in_array($prefix, ['ED', 'ET'])) {
                    $endTime = $connection['end'] ?? null;
                    if ($endTime) {
                        return Carbon::parse($endTime)->timezone('UTC');
                    }
                }
            }

            $this->warn("No German connections found for user {$vatsimId}");
            return Carbon::createFromTimestamp(0)->timezone('UTC');

        } catch (\Exception $e) {
            Log::error('Error fetching last session', [
                'vatsim_id' => $vatsimId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Check if user obtained S1 rating within last 11 months
     */
    protected function checkS1Status(int $vatsimId): array
    {
        try {
            $response = Http::timeout(10)->get("https://api.vatsim.net/api/ratings/{$vatsimId}/");
            
            if (!$response->successful()) {
                return [false, null];
            }

            $data = $response->json();
            $rating = $data['rating'] ?? 0;

            if ($rating == 2) { // S1
                $ratingChange = $data['lastratingchange'] ?? null;
                
                if ($ratingChange) {
                    $ratingChangeDate = Carbon::parse($ratingChange)->timezone('UTC');
                    $isRecent = now()->diffInDays($ratingChangeDate) < (11 * 30);
                    
                    return [$isRecent, $ratingChangeDate];
                }
            }

            return [false, null];

        } catch (\Exception $e) {
            Log::error('Error checking S1 status', [
                'vatsim_id' => $vatsimId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send removal warning notification
     */
    protected function sendRemovalWarning(int $vatsimId): void
    {
        return;
        $apiKey = config('services.vatger.api_key');
        
        if (!$apiKey) {
            Log::warning('VATGER API key not configured, skipping removal notification');
            return;
        }

        $message = "You have not controlled in the past 11 months. " .
                   "If you want to stay on the VATSIM Germany roster, " .
                   "please log in to the VATSIM network and control at least once in the next 35 days. " .
                   "If you do not, your account will be removed from the roster. " .
                   "If you believe this is a mistake, please contact the ATD.";

        $data = [
            'title' => 'Removal from VATSIM Germany Roster',
            'message' => $message,
            'source_name' => 'VATGER ATD',
            'via' => 'board.ping',
        ];

        try {
          return;
            $response = Http::withHeaders([
                'Authorization' => "Token {$apiKey}",
            ])->post("https://vatsim-germany.org/api/user/{$vatsimId}/send_notification", $data);

            if (!$response->successful()) {
                Log::warning('Failed to send removal notification', [
                    'vatsim_id' => $vatsimId,
                    'status' => $response->status()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error sending removal notification', [
                'vatsim_id' => $vatsimId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Remove user from roster and clean up endorsements
     */
    protected function removeFromRoster(int $vatsimId): void
    {
        try {
            $this->info("Removing user {$vatsimId} from roster...");

            // Remove from VatEUD roster and endorsements
            $this->vatEudService->removeRosterAndEndorsements($vatsimId);

            // Remove waiting list entries
            WaitingListEntry::whereHas('user', function ($query) use ($vatsimId) {
                $query->where('vatsim_id', $vatsimId);
            })->delete();

            Log::info('User removed from roster', [
                'vatsim_id' => $vatsimId
            ]);

        } catch (\Exception $e) {
            Log::error('Error removing user from roster', [
                'vatsim_id' => $vatsimId,
                'error' => $e->getMessage()
            ]);
        }
    }
}