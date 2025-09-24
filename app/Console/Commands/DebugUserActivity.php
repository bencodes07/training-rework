<?php

namespace App\Console\Commands;

use App\Services\VatsimActivityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class DebugUserActivity extends Command
{
    protected $signature = 'debug:user-activity {vatsim_id}';
    protected $description = 'Debug activity calculation for a specific user';

    protected VatsimActivityService $activityService;

    public function __construct(VatsimActivityService $activityService)
    {
        parent::__construct();
        $this->activityService = $activityService;
    }

    public function handle(): int
    {
        $vatsimId = (int) $this->argument('vatsim_id');
        
        $this->info("Debugging activity for VATSIM ID: {$vatsimId}");
        
        // Test direct VATSIM API call
        $start = Carbon::now()->subDays(180)->format('Y-m-d');
        $apiUrl = "https://api.vatsim.net/api/ratings/{$vatsimId}/atcsessions/?start={$start}";
        
        $this->info("Making direct API call to: {$apiUrl}");
        
        try {
            $response = Http::timeout(15)->get($apiUrl);
            
            $this->line("Response Status: {$response->status()}");
            $this->line("Response Headers: " . json_encode($response->headers()));
            
            if ($response->successful()) {
                $data = $response->json();
                $this->line("Response Structure: " . json_encode(array_keys($data), JSON_PRETTY_PRINT));
                
                if (isset($data['results'])) {
                    $count = count($data['results']);
                    $this->line("Found {$count} ATC sessions");
                    
                    if ($count > 0) {
                        $this->line("First session example:");
                        $this->line(json_encode($data['results'][0], JSON_PRETTY_PRINT));
                        
                        // Test activity calculation for a specific position
                        $positions = ['EDDF_TWR', 'EDDF_APP', 'EDDF_GNDDEL', 'EDDL_TWR', 'EDDL_APP', 'EDDL_GNDDEL'];
                        
                        foreach ($positions as $position) {
                            $this->line("\n--- Testing position: {$position} ---");
                            
                            $endorsement = [
                                'user_cid' => $vatsimId,
                                'position' => $position
                            ];
                            
                            // Show matching connections
                            $matchingConnections = [];
                            $totalMinutes = 0;
                            
                            foreach ($data['results'] as $connection) {
                                $callsign = $connection['callsign'];
                                $minutes = floatval($connection['minutes_on_callsign']);
                                
                                // Test if this connection matches the position
                                if ($this->testConnectionMatch($position, $callsign)) {
                                    $matchingConnections[] = [
                                        'callsign' => $callsign,
                                        'minutes' => $minutes,
                                        'start' => $connection['start']
                                    ];
                                    $totalMinutes += $minutes;
                                }
                            }
                            
                            $this->line("Matching connections: " . count($matchingConnections));
                            foreach (array_slice($matchingConnections, 0, 5) as $match) {
                                $this->line("  - {$match['callsign']}: {$match['minutes']} min ({$match['start']})");
                            }
                            if (count($matchingConnections) > 5) {
                                $this->line("  ... and " . (count($matchingConnections) - 5) . " more");
                            }
                            
                            $activity = $this->activityService->getEndorsementActivity($endorsement);
                            $this->line("Manual total: {$totalMinutes} minutes");
                            $this->line("Service result: {$activity} minutes");
                            $this->line("Difference: " . ($totalMinutes - $activity));
                        }
                    } else {
                        $this->warn("No ATC sessions found in the last 180 days");
                    }
                } else {
                    $this->error("No 'results' key in response");
                    $this->line("Full response: " . $response->body());
                }
            } else {
                $this->error("API request failed: " . $response->body());
            }
        } catch (\Exception $e) {
            $this->error("Exception: " . $e->getMessage());
        }
        
        return 0;
    }

    /**
     * Test if a connection matches a position (simplified version of the service logic)
     */
    protected function testConnectionMatch(string $position, string $callsign): bool
    {
        // CTR position logic
        if (str_ends_with($position, '_CTR')) {
            $ctrlPrefix = substr($position, 0, 6);
            return str_starts_with($callsign, $ctrlPrefix) || 
                   ($position === 'EDWW_W_CTR' && $callsign === 'EDWW_CTR');
        }

        // Airport position logic
        $parts = explode('_', $position);
        if (count($parts) >= 2) {
            $airport = $parts[0];
            $station = end($parts);
            
            $viableSuffixes = [
                'APP' => ['APP', 'DEP'],
                'TWR' => ['APP', 'DEP', 'TWR'],
                'GNDDEL' => ['APP', 'DEP', 'TWR', 'GND', 'DEL'],
            ];
            
            $ctrTopdown = [
                'EDDB' => ['EDWW_F', 'EDWW_B', 'EDWW_K', 'EDWW_M', 'EDWW_C'],
                'EDDH' => ['EDWW_H', 'EDWW_A', 'EDWW_W', 'EDWW_C'],
                'EDDF' => ['EDGG_G', 'EDGG_R', 'EDGG_D', 'EDGG_B', 'EDGG_K'],
                'EDDK' => ['EDGG_P'],
                'EDDL' => ['EDGG_P'],
                'EDDM' => ['EDMM_N', 'EDMM_Z', 'EDMM_R'],
            ];
            
            $callsignParts = explode('_', $callsign);
            if (count($callsignParts) < 2) {
                return false;
            }
            
            $csApt = $callsignParts[0];
            $csStation = end($callsignParts);
            
            // Check CTR topdown
            $stationsToConsider = $ctrTopdown[$airport] ?? [];
            foreach ($stationsToConsider as $ctrStation) {
                if (str_starts_with($callsign, $ctrStation)) {
                    return true;
                }
            }
            
            // Check suffix condition
            $allowedSuffixes = $viableSuffixes[$station] ?? [];
            return $csApt === $airport && in_array($csStation, $allowedSuffixes);
        }
        
        return false;
    }
}