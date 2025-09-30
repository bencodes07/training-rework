<?php

namespace App\Http\Controllers;

use App\Models\EndorsementActivity;
use App\Models\Tier2Endorsement;
use App\Models\User;
use App\Services\VatEudService;
use App\Services\VatsimActivityService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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

        // Get all tier 1 endorsements with activity data
        $endorsements = $this->getAllTier1WithActivity();
        
        // Group by position for easier management
        $groupedEndorsements = collect($endorsements)->groupBy('position');

        return Inertia::render('endorsements/manage', [
            'endorsementGroups' => $groupedEndorsements,
            'statistics' => $this->getEndorsementStatistics($endorsements),
        ]);
    }

    /**
     * Remove/mark for removal a Tier 1 endorsement
     */
    public function removeTier1(Request $request, int $endorsementId): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isMentor() && !$user->is_superuser) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            $endorsement = EndorsementActivity::where('endorsement_id', $endorsementId)->first();
            
            if (!$endorsement) {
                return response()->json(['error' => 'Endorsement not found'], 404);
            }

            // Check if already marked for removal
            if ($endorsement->removal_date) {
                return response()->json(['error' => 'Endorsement already marked for removal'], 400);
            }

            // Check if eligible for removal
            if (!$endorsement->isEligibleForRemoval()) {
                return response()->json(['error' => 'Endorsement not eligible for removal'], 400);
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

            return response()->json([
                'success' => true,
                'message' => "Removal process started for {$endorsement->position}",
                'removal_date' => $endorsement->removal_date->format('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            Log::error('Error marking endorsement for removal', [
                'endorsement_id' => $endorsementId,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
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
        Log::info('Getting Tier 1 endorsements for user', ['vatsim_id' => $vatsimId]);

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

            // Use actual last activity date instead of sync timestamp
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

            // Only include endorsements that are eligible for removal
            if (!$activity->isEligibleForRemoval(5 * 30 + 5)) { // 5 months + buffer
                continue;
            }

            $user = User::where('vatsim_id', $endorsement['user_cid'])->first();

            $result[] = [
                'id' => $endorsement['id'],
                'endorsementId' => $endorsement['id'],
                'position' => $endorsement['position'],
                'vatsimId' => $endorsement['user_cid'],
                'userName' => $user ? $user->name : 'Unknown',
                'activity' => $activity->activity_minutes,
                'activityHours' => $activity->activity_hours,
                'status' => $activity->status,
                'progress' => $activity->progress,
                'removalDate' => $activity->removal_date,
                'removalDays' => $activity->removal_date ? $activity->removal_date->diffInDays(now(), false) : -1,
            ];
        }

        return $result;
    }

    /**
     * Get endorsement statistics
     */
    protected function getEndorsementStatistics(array $endorsements): array
    {
        $total = count($endorsements);
        $minRequiredMinutes = config('services.vateud.min_activity_minutes', 180);
        
        $inactive = collect($endorsements)->filter(function ($endorsement) use ($minRequiredMinutes) {
            return $endorsement['activity'] < $minRequiredMinutes;
        })->count();

        $removal = collect($endorsements)->filter(function ($endorsement) {
            return $endorsement['removalDays'] >= 0;
        })->count();

        return [
            'total' => $total,
            'inactive' => $inactive,
            'removal' => $removal,
        ];
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
            'EDGG_KTG_CTR' => 'Sektor Kitzingen',
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