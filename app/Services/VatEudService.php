<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class VatEudService
{
    protected $headers;
    protected $baseUrl = 'https://core.vateud.net/api';

    public function __construct()
    {
        $this->headers = [
            'X-API-KEY' => config('services.vateud.token'),
            'Accept' => 'application/json',
            'User-Agent' => 'VATGER Training System',
        ];
    }

    /**
     * Get Tier 1 endorsements from VatEUD
     */
    public function getTier1Endorsements(): array
    {
        if (config('services.vateud.use_mock', false)) {
            Log::info('Using mock Tier 1 data');
            return $this->getMockTier1Data();
        }

        try {
            $cacheKey = 'vateud:tier1_endorsements';
            
            return Cache::remember($cacheKey, now()->addMinutes(10), function () {
                Log::info('Fetching Tier 1 endorsements from VatEUD API', [
                    'url' => "{$this->baseUrl}/facility/endorsements/tier-1",
                    'headers' => array_keys($this->headers), // Don't log the actual API key
                ]);

                $response = Http::withHeaders($this->headers)
                    ->timeout(10)
                    ->get("{$this->baseUrl}/facility/endorsements/tier-1");

                Log::info('VatEUD Tier 1 API response', [
                    'status' => $response->status(),
                    'successful' => $response->successful(),
                    'body_preview' => substr($response->body(), 0, 500),
                ]);

                if (!$response->successful()) {
                    Log::error('Failed to fetch Tier 1 endorsements', [
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    return [];
                }

                $data = $response->json();
                
                // Handle different response structures
                if (isset($data['data'])) {
                    $endorsements = $data['data'];
                } elseif (is_array($data)) {
                    $endorsements = $data;
                } else {
                    Log::warning('Unexpected Tier 1 endorsements response structure', ['data' => $data]);
                    return [];
                }

                Log::info('Processed Tier 1 endorsements', [
                    'count' => count($endorsements),
                    'sample' => array_slice($endorsements, 0, 2), // Log first 2 for debugging
                ]);

                return $this->sortEndorsements($endorsements);
            });
        } catch (\Exception $e) {
            Log::error('Error fetching Tier 1 endorsements', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Get Tier 2 endorsements from VatEUD
     */
    public function getTier2Endorsements(): array
    {
        if (config('services.vateud.use_mock', false)) {
            return $this->getMockTier2Data();
        }

        try {
            $cacheKey = 'vateud:tier2_endorsements';
            
            return Cache::remember($cacheKey, now()->addMinutes(10), function () {
                $response = Http::withHeaders($this->headers)
                    ->timeout(10)
                    ->get("{$this->baseUrl}/facility/endorsements/tier-2");

                if (!$response->successful()) {
                    Log::error('Failed to fetch Tier 2 endorsements', [
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    return [];
                }

                $data = $response->json();
                
                // Handle different response structures
                if (isset($data['data'])) {
                    return $data['data'];
                } elseif (is_array($data)) {
                    return $data;
                } else {
                    Log::warning('Unexpected Tier 2 endorsements response structure', ['data' => $data]);
                    return [];
                }
            });
        } catch (\Exception $e) {
            Log::error('Error fetching Tier 2 endorsements', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Get solo endorsements from VatEUD
     */
    public function getSoloEndorsements(): array
    {
        if (config('services.vateud.use_mock', false)) {
            return $this->getMockSoloData();
        }

        try {
            $cacheKey = 'vateud:solo_endorsements';
            
            return Cache::remember($cacheKey, now()->addMinutes(5), function () {
                $response = Http::withHeaders($this->headers)
                    ->timeout(10)
                    ->get("{$this->baseUrl}/facility/endorsements/solo");

                if (!$response->successful()) {
                    Log::error('Failed to fetch solo endorsements', [
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    return [];
                }

                $data = $response->json();
                
                if (isset($data['data'])) {
                    return $data['data'];
                } elseif (is_array($data)) {
                    return $data;
                } else {
                    Log::warning('Unexpected solo endorsements response structure', ['data' => $data]);
                    return [];
                }
            });
        } catch (\Exception $e) {
            Log::error('Error fetching solo endorsements', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Remove roster and endorsements for a user (GDPR compliance)
     */
    public function removeRosterAndEndorsements(int $vatsimId): bool
    {
        if (config('services.vateud.use_mock', false)) {
            return true;
        }

        try {
            // Remove from roster
            $rosterResponse = Http::withHeaders($this->headers)
                ->delete("{$this->baseUrl}/facility/roster/{$vatsimId}");

            // Get user's endorsements and remove them
            $tier1 = collect($this->getTier1Endorsements())
                ->where('user_cid', $vatsimId);
            
            $tier2 = collect($this->getTier2Endorsements())
                ->where('user_cid', $vatsimId);

            foreach ($tier1 as $endorsement) {
                Http::withHeaders($this->headers)
                    ->delete("{$this->baseUrl}/facility/endorsements/tier-1/{$endorsement['id']}");
            }

            foreach ($tier2 as $endorsement) {
                Http::withHeaders($this->headers)
                    ->delete("{$this->baseUrl}/facility/endorsements/tier-2/{$endorsement['id']}");
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Error removing roster and endorsements', [
                'vatsim_id' => $vatsimId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Remove a specific Tier 1 endorsement
     */
    public function removeTier1Endorsement(int $endorsementId): bool
    {
        if (config('services.vateud.use_mock', false)) {
            return true;
        }

        try {
            $response = Http::withHeaders($this->headers)
                ->delete("{$this->baseUrl}/facility/endorsements/tier-1/{$endorsementId}");

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Error removing Tier 1 endorsement', [
                'endorsement_id' => $endorsementId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Create a Tier 2 endorsement
     */
    public function createTier2Endorsement(int $userCid, string $position, int $instructorCid): bool
    {
        if (config('services.vateud.use_mock', false)) {
            return true;
        }

        try {
            $response = Http::withHeaders($this->headers)
                ->post("{$this->baseUrl}/facility/endorsements/tier-2", [
                    'user_cid' => $userCid,
                    'position' => $position,
                    'instructor_cid' => $instructorCid,
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Error creating Tier 2 endorsement', [
                'user_cid' => $userCid,
                'position' => $position,
                'instructor_cid' => $instructorCid,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Sort endorsements by position (matching Python logic)
     */
    protected function sortEndorsements(array $endorsements): array
    {
        usort($endorsements, function ($a, $b) {
            $sortA = $this->getEndorsementSortKey($a['position']);
            $sortB = $this->getEndorsementSortKey($b['position']);
            
            return strcmp($sortA, $sortB);
        });

        return $endorsements;
    }

    /**
     * Get sort key for endorsement position (matching Python logic)
     */
    protected function getEndorsementSortKey(string $position): string
    {
        if (str_ends_with($position, '_CTR')) {
            $ctrCode = substr($position, 0, -4);
            return "0_CTR_{$ctrCode}";
        }

        $parts = explode('_', $position);
        if (count($parts) >= 2) {
            $airport = $parts[0];
            $endorsementType = implode('_', array_slice($parts, 1));

            $typePriority = [
                'APP' => '1',
                'TWR' => '2',
                'GNDDEL' => '3'
            ];

            $priority = $typePriority[$endorsementType] ?? '9';
            return "1_{$airport}_{$priority}";
        }

        return "9_{$position}_";
    }

    /**
     * Mock data for development/testing
     */
    protected function getMockTier1Data(): array
    {
        return [
            [
                'id' => 1,
                'user_cid' => 1601613,
                'instructor_cid' => 1439797,
                'position' => 'EDDL_TWR',
                'facility' => 9,
                'created_at' => '2025-04-19T12:02:38.000000Z',
                'updated_at' => '2025-04-19T12:02:38.000000Z',
            ],
            [
                'id' => 2,
                'user_cid' => 1601613,
                'instructor_cid' => 1439797,
                'position' => 'EDDL_APP',
                'facility' => 9,
                'created_at' => '2025-04-19T12:02:38.000000Z',
                'updated_at' => '2025-04-19T12:02:38.000000Z',
            ],
        ];
    }

    protected function getMockTier2Data(): array
    {
        return [
            [
                'id' => 25,
                'user_cid' => 1439797,
                'instructor_cid' => 1439797,
                'position' => 'EDXX_AFIS',
                'facility' => 9,
                'created_at' => '2024-02-29T22:39:33.000000Z',
                'updated_at' => '2024-02-29T22:39:33.000000Z',
            ],
        ];
    }

    protected function getMockSoloData(): array
    {
        return [
            [
                "id" => 1903,
                "user_cid" => 1749937,
                "instructor_cid" => 1601613,
                "position" => "EDDL_TWR",
                "expiry" => "2025-10-09T23:59:00.000000Z",
                "max_days" => 90,
                "facility" => 9,
                "created_at" => "2025-09-10T20:38:32.000000Z",
                "updated_at" => "2025-09-10T20:38:32.000000Z",
                "position_days" => 14
            ],
            [
                "id" => 1915,
                "user_cid" => 1772662,
                "instructor_cid" => 1312976,
                "position" => "EDDB_TWR",
                "expiry" => "2025-10-10T23:59:00.000000Z",
                "max_days" => 88,
                "facility" => 9,
                "created_at" => "2025-09-17T06:58:22.000000Z",
                "updated_at" => "2025-09-17T06:58:22.000000Z",
                "position_days" => 10
            ],
            [
                "id" => 1919,
                "user_cid" => 1601613,
                "instructor_cid" => 1523643,
                "position" => "EDDH_TWR",
                "expiry" => "2025-10-12T23:59:00.000000Z",
                "max_days" => 53,
                "facility" => 9,
                "created_at" => "2025-09-17T20:40:47.000000Z",
                "updated_at" => "2025-09-17T20:40:47.000000Z",
                "position_days" => 44
            ]
        ];
    }
}