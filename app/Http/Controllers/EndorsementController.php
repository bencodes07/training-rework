<?php

namespace App\Http\Controllers;

use App\Models\EndorsementActivity;
use App\Models\Tier2Endorsement;
use App\Models\User;
use App\Models\Course;
use App\Services\VatEudService;
use App\Services\VatsimActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class EndorsementController extends Controller
{
    protected VatEudService $vatEudService;
    protected VatsimActivityService $activityService;

    public function __construct(VatEudService $vatEudService, VatsimActivityService $activityService)
    {
        $this->vatEudService = $vatEudService;
        $this->activityService = $activityService;
    }

    /**
     * Show trainee endorsements view
     */
    public function traineeView(Request $request): Response
    {
        $user = $request->user();
        
        if (!$user->isVatsimUser()) {
            return Inertia::render('endorsements/trainee', [
                'tier1Endorsements' => [],
                'tier2Endorsements' => [],
                'soloEndorsements' => [],
                'isVatsimUser' => false,
            ]);
        }

        try {
            $tier1Data = $this->getUserTier1Endorsements($user->vatsim_id);
            $tier2Data = $this->getUserTier2Endorsements($user->vatsim_id);
            $soloData = $this->getUserSoloEndorsements($user->vatsim_id);

            return Inertia::render('endorsements/trainee', [
                'tier1Endorsements' => $tier1Data,
                'tier2Endorsements' => $tier2Data,
                'soloEndorsements' => $soloData,
                'isVatsimUser' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Error loading endorsements', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Inertia::render('endorsements/trainee', [
                'tier1Endorsements' => [],
                'tier2Endorsements' => [],
                'soloEndorsements' => [],
                'isVatsimUser' => true,
                'error' => 'Failed to load endorsement data',
            ]);
        }
    }

    /**
     * Show mentor endorsements management view
     */
    public function mentorView(Request $request): Response
    {
        $user = $request->user();
        
        if (!$user->isMentor() && !$user->is_superuser) {
            abort(403, 'Access denied. Mentor privileges required.');
        }

        // Get courses based on user permissions to determine which positions mentor can access
        if ($user->is_superuser || $user->is_admin) {
            // Admins and superusers see all positions
            $courses = Course::all();
        } else {
            // Regular mentors only see positions from their assigned courses
            $courses = $user->mentorCourses;
        }

        // Extract unique positions (airport + position type) that this mentor can manage
        $allowedPositions = $courses->map(function ($course) {
            return [
                'airport' => $course->airport_icao,
                'position' => $course->position,
            ];
        })->unique(function ($item) {
            return $item['airport'] . '_' . $item['position'];
        });

        // Get all tier 1 endorsements with activity data
        $allEndorsements = $this->getAllTier1WithActivity();

        // Group endorsements by position and filter by mentor permissions
        $endorsementsByPosition = collect($allEndorsements)
            ->filter(function ($endorsement) use ($allowedPositions, $user) {
                // Admins see everything
                if ($user->is_superuser || $user->is_admin) {
                    return true;
                }

                // Extract position info from endorsement
                $parts = explode('_', $endorsement['position']);
                $airport = $parts[0];
                $positionType = end($parts);

                // Handle GNDDEL -> GND conversion
                if ($positionType === 'GNDDEL') {
                    $positionType = 'GND';
                }

                // Check if mentor has access to this position
                return $allowedPositions->contains(function ($allowed) use ($airport, $positionType) {
                    return $allowed['airport'] === $airport && $allowed['position'] === $positionType;
                });
            })
            ->groupBy('position')
            ->map(function ($endorsements, $position) {
                return [
                    'position' => $position,
                    'position_name' => $this->getPositionFullName($position),
                    'airport_icao' => explode('_', $position)[0],
                    'position_type' => $this->getPositionType($position),
                    'endorsements' => $endorsements->toArray(),
                ];
            })
            ->values();

        return Inertia::render('endorsements/manage', [
            'endorsementGroups' => $endorsementsByPosition,
        ]);
    }

    /**
     * Remove/mark for removal a Tier 1 endorsement
     */
    public function removeTier1(Request $request, int $endorsementId)
    {
        $user = $request->user();
        
        if (!$user->isMentor() && !$user->is_superuser) {
            return back()->with('error', 'Access denied');
        }

        try {
            $endorsement = EndorsementActivity::where('endorsement_id', $endorsementId)->first();
            
            if (!$endorsement) {
                return back()->with('error', 'Endorsement not found');
            }

            // Verify mentor has permission to manage this endorsement
            if (!$user->is_superuser && !$user->is_admin) {
                // Check if user is a mentor for a course matching this position
                $hasCourse = $user->mentorCourses()->where(function ($query) use ($endorsement) {
                    $parts = explode('_', $endorsement->position);
                    $airport = $parts[0];
                    $position = end($parts);

                    if ($position === 'GNDDEL') {
                        $position = 'GND';
                    }

                    $query->where('airport_icao', $airport)
                        ->where('position', $position);
                })->exists();

                if (!$hasCourse) {
                    return back()->with('error', 'You do not have permission to manage this endorsement');
                }
            }

            // Check if already marked for removal
            if ($endorsement->removal_date) {
                return back()->with('error', 'Endorsement already marked for removal');
            }

            // Check if activity is below threshold (regardless of age)
            $minRequiredMinutes = config('services.vateud.min_activity_minutes', 180);
            if ($endorsement->activity_minutes >= $minRequiredMinutes) {
                return back()->with('error', 'Endorsement has sufficient activity and cannot be marked for removal');
            }

            // Mark for removal
            $endorsement->removal_date = Carbon::now()->addDays(
                config('services.vateud.removal_warning_days', 31)
            );
            $endorsement->removal_notified = false;
            $endorsement->last_updated = Carbon::createFromTimestamp(0); // Trigger update
            $endorsement->save();

            Log::info('Endorsement marked for removal', [
                'endorsement_id' => $endorsementId,
                'position' => $endorsement->position,
                'vatsim_id' => $endorsement->vatsim_id,
                'marked_by' => $user->id,
                'removal_date' => $endorsement->removal_date
            ]);

            return back()->with('success', "Successfully marked {$endorsement->position} for removal");

        } catch (\Exception $e) {
            Log::error('Error marking endorsement for removal', [
                'endorsement_id' => $endorsementId,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'An error occurred while marking the endorsement for removal');
        }
    }

    /**
     * Request a Tier 2 endorsement
     */
    public function requestTier2(Request $request, int $tier2Id): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isVatsimUser()) {
            return response()->json(['error' => 'VATSIM account required'], 403);
        }

        try {
            $tier2Endorsement = Tier2Endorsement::findOrFail($tier2Id);
            
            // Check if user already has this endorsement
            $existingTier2 = collect($this->vatEudService->getTier2Endorsements())
                ->where('user_cid', $user->vatsim_id)
                ->where('position', $tier2Endorsement->position)
                ->first();

            if ($existingTier2) {
                return response()->json(['error' => 'You already have this endorsement'], 400);
            }

            // TODO: Check Moodle course completion here
            // For now, we'll assume it's completed
            
            // Create the endorsement in VatEUD
            $success = $this->vatEudService->createTier2Endorsement(
                $user->vatsim_id,
                $tier2Endorsement->position,
                config('services.vateud.atd_lead_cid', 1439797) // Default instructor CID
            );

            if (!$success) {
                return response()->json(['error' => 'Failed to create endorsement'], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Tier 2 endorsement created successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error requesting Tier 2 endorsement', [
                'tier2_id' => $tier2Id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get Tier 1 endorsements for a specific user
     */
    protected function getUserTier1Endorsements(int $vatsimId): array
    {
        $allTier1 = $this->vatEudService->getTier1Endorsements();

        $tier1Endorsements = collect($allTier1)->where('user_cid', $vatsimId);

        $result = [];
        $minRequiredMinutes = config('services.vateud.min_activity_minutes', 180);

        foreach ($tier1Endorsements as $endorsement) {
            $activity = EndorsementActivity::where('endorsement_id', $endorsement['id'])->first();
            
            if (!$activity) {
                Log::info('No activity record found for endorsement', [
                    'endorsement_id' => $endorsement['id'],
                    'position' => $endorsement['position']
                ]);
                continue;
            }

            $lastActivityDate = $activity->last_activity_date
                ? $activity->last_activity_date->format('Y-m-d')
                : 'Never';

            $result[] = [
                'position' => $endorsement['position'],
                'fullName' => $this->getPositionFullName($endorsement['position']),
                'type' => $this->getPositionType($endorsement['position']),
                'activity' => $activity->activity_minutes,
                'activityHours' => $activity->activity_hours,
                'status' => $activity->status,
                'progress' => $activity->progress,
                'lastActivity' => $lastActivityDate,
                'removalDate' => $activity->removal_date?->format('Y-m-d'),
            ];
        }

        return $result;
    }

    /**
     * Get Tier 2 endorsements for a specific user
     */
    protected function getUserTier2Endorsements(int $vatsimId): array
    {
        $tier2Endorsements = collect($this->vatEudService->getTier2Endorsements())
            ->where('user_cid', $vatsimId)
            ->pluck('position')
            ->toArray();

        $availableTier2 = Tier2Endorsement::all();
        $result = [];

        foreach ($availableTier2 as $endorsement) {
            $result[] = [
                'id' => $endorsement->id,
                'position' => $endorsement->position,
                'name' => $endorsement->name,
                'fullName' => $endorsement->name,
                'type' => $this->getPositionType($endorsement->position),
                'status' => in_array($endorsement->position, $tier2Endorsements) ? 'active' : 'available',
                'moodleCourseId' => $endorsement->moodle_course_id,
                'hasEndorsement' => in_array($endorsement->position, $tier2Endorsements),
            ];
        }

        return $result;
    }

    /**
     * Get solo endorsements for a specific user
     */
    protected function getUserSoloEndorsements(int $vatsimId): array
    {
        $soloEndorsements = collect($this->vatEudService->getSoloEndorsements())
            ->where('user_cid', $vatsimId);

        $result = [];

        foreach ($soloEndorsements as $solo) {
            $result[] = [
                'position' => $solo['position'],
                'fullName' => $this->getPositionFullName($solo['position']),
                'type' => $this->getPositionType($solo['position']),
                'status' => 'active',
                'mentor' => $this->getMentorName($solo['instructor_cid'] ?? null),
                'expiresAt' => isset($solo['expires_at']) ? Carbon::parse($solo['expires_at'])->format('Y-m-d') : null,
            ];
        }

        return $result;
    }

    /**
     * Get all Tier 1 endorsements with activity data
     */
    protected function getAllTier1WithActivity(): array
    {
        $tier1Endorsements = $this->vatEudService->getTier1Endorsements();
        $result = [];

        foreach ($tier1Endorsements as $endorsement) {
            $activity = EndorsementActivity::where('endorsement_id', $endorsement['id'])->first();
            
            if (!$activity) {
                continue;
            }

            $user = User::where('vatsim_id', $endorsement['user_cid'])->first();

            $result[] = [
                'id' => $activity->id,
                'endorsementId' => $endorsement['id'],
                'position' => $endorsement['position'],
                'vatsimId' => $endorsement['user_cid'],
                'userName' => $user ? $user->name : 'Unknown',
                'activity' => $activity->activity_minutes,
                'activityHours' => $activity->activity_hours,
                'status' => $activity->status,
                'progress' => $activity->progress,
                'removalDate' => $activity->removal_date?->format('Y-m-d'),
                'removalDays' => $activity->removal_date ? $activity->removal_date->diffInDays(now(), false) : -1,
            ];
        }

        return $result;
    }

    /**
     * Get full name for position
     */
    protected function getPositionFullName(string $position): string
    {
        $positionNames = [
            'EDDF_TWR' => 'Frankfurt Tower',
            'EDDF_APP' => 'Frankfurt Approach',
            'EDDF_GNDDEL' => 'Frankfurt Ground/Delivery',
            'EDDL_TWR' => 'Düsseldorf Tower',
            'EDDL_APP' => 'Düsseldorf Approach',
            'EDDL_GNDDEL' => 'Düsseldorf Ground/Delivery',
            'EDDK_TWR' => 'Köln Tower',
            'EDDK_APP' => 'Köln Approach',
            'EDDS_TWR' => 'Stuttgart Tower',
            'EDDH_TWR' => 'Hamburg Tower',
            'EDDH_APP' => 'Hamburg Approach',
            'EDDH_GNDDEL' => 'Hamburg Ground/Delivery',
            'EDDM_TWR' => 'München Tower',
            'EDDM_APP' => 'München Approach',
            'EDDM_GNDDEL' => 'München Ground/Delivery',
            'EDDB_APP' => 'Berlin Approach',
            'EDDB_TWR' => 'Berlin Tower',
            'EDDB_GNDDEL' => 'Berlin Ground/Delivery',
            'EDWW_CTR' => 'Bremen Big',
            'EDGG_KTG_CTR' => 'Kitzingen',
            'EDXX_AFIS' => 'AFIS Tower',
        ];

        return $positionNames[$position] ?? $position;
    }

    /**
     * Get position type
     */
    protected function getPositionType(string $position): string
    {
        if (str_ends_with($position, '_CTR')) {
            return 'CTR';
        } elseif (str_ends_with($position, '_APP')) {
            return 'APP';
        } elseif (str_ends_with($position, '_TWR')) {
            return 'TWR';
        } elseif (str_ends_with($position, '_GNDDEL')) {
            return 'GNDDEL';
        }
        
        return 'TWR'; // Default
    }

    /**
     * Get mentor name by VATSIM ID
     */
    protected function getMentorName(?int $vatsimId): string
    {
        if (!$vatsimId) {
            return 'Unknown';
        }

        $user = User::where('vatsim_id', $vatsimId)->first();
        return $user ? $user->name : "ID: {$vatsimId}";
    }
}