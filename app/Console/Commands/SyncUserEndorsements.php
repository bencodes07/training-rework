<?php

namespace App\Console\Commands;

use App\Models\EndorsementActivity;
use App\Services\VatEudService;
use App\Services\VatsimActivityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncUserEndorsements extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'endorsements:sync-user {vatsim_id : VATSIM ID of the user to sync}';

    /**
     * The console command description.
     */
    protected $description = 'Sync endorsement activities for a specific user';

    protected VatEudService $vatEudService;
    protected VatsimActivityService $activityService;

    public function __construct(VatEudService $vatEudService, VatsimActivityService $activityService)
    {
        parent::__construct();
        $this->vatEudService = $vatEudService;
        $this->activityService = $activityService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $vatsimId = (int) $this->argument('vatsim_id');
        
        $this->info("Syncing endorsement activities for VATSIM ID: {$vatsimId}");

        try {
            // First, sync with VatEUD to ensure we have current endorsements
            $this->syncUserEndorsementsFromVatEUD($vatsimId);

            // Then update activity for this user's endorsements
            $endorsements = EndorsementActivity::where('vatsim_id', $vatsimId)->get();

            if ($endorsements->isEmpty()) {
                $this->warn("No endorsements found for VATSIM ID: {$vatsimId}");
                return 1;
            }

            $this->info("Found {$endorsements->count()} endorsement(s) for this user");

            foreach ($endorsements as $endorsement) {
                $this->updateEndorsementActivity($endorsement);
            }

            $this->info("Successfully updated all endorsements for VATSIM ID: {$vatsimId}");
            return 0;

        } catch (\Exception $e) {
            $this->error('Error syncing user endorsements: ' . $e->getMessage());
            Log::error('User endorsement sync error', [
                'vatsim_id' => $vatsimId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Sync endorsements for a specific user from VatEUD (Tier 1 only)
     */
    protected function syncUserEndorsementsFromVatEUD(int $vatsimId): void
    {
        $this->line("Fetching current Tier 1 endorsements from VatEUD...");

        // Only fetch Tier 1 endorsements since they require activity tracking
        $tier1Endorsements = $this->vatEudService->getTier1Endorsements();
        $userEndorsements = collect($tier1Endorsements)->where('user_cid', $vatsimId);

        $this->line("Found {$userEndorsements->count()} current Tier 1 endorsement(s) in VatEUD for this user");

        foreach ($userEndorsements as $endorsement) {
            $this->syncEndorsement($endorsement);
        }
    }

    /**
     * Sync individual Tier 1 endorsement
     */
    protected function syncEndorsement(array $endorsement): void
    {
        $createdAt = null;
        if (!empty($endorsement['created_at'])) {
            try {
                $createdAt = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i:s.u\Z', $endorsement['created_at']);
            } catch (\Exception $e) {
                $createdAt = \Carbon\Carbon::createFromTimestamp(0);
            }
        }

        EndorsementActivity::updateOrCreate(
            ['endorsement_id' => $endorsement['id']],
            [
                'vatsim_id' => $endorsement['user_cid'],
                'position' => $endorsement['position'],
                'created_at_vateud' => $createdAt,
                'last_updated' => $createdAt ?? \Carbon\Carbon::createFromTimestamp(0),
            ]
        );

        $this->line("Synced endorsement: {$endorsement['position']} (ID: {$endorsement['id']})");
    }

    /**
     * Update activity for a specific endorsement
     * CRITICAL: Only updates activity data, NEVER automatically sets removal date
     */
    protected function updateEndorsementActivity(EndorsementActivity $endorsementActivity): void
    {
        try {
            $this->line("Updating activity for {$endorsementActivity->position}...");

            $endorsementData = [
                'user_cid' => $endorsementActivity->vatsim_id,
                'position' => $endorsementActivity->position,
            ];

            // Get both activity minutes and last activity date
            $activityResult = $this->activityService->getEndorsementActivity($endorsementData);
            $activityMinutes = $activityResult['minutes'] ?? 0;
            $lastActivityDate = $activityResult['last_activity_date'] ?? null;

            $minRequiredMinutes = config('services.vateud.min_activity_minutes', 180);

            // Update activity
            $endorsementActivity->activity_minutes = $activityMinutes;
            $endorsementActivity->last_activity_date = $lastActivityDate;
            $endorsementActivity->last_updated = now();

            // ONLY clear removal flags if activity recovered
            // NEVER automatically set removal date - this is a manual mentor action
            if ($activityMinutes >= $minRequiredMinutes) {
                if ($endorsementActivity->removal_date) {
                    $this->info("✓ Activity recovered, clearing removal date");
                    $endorsementActivity->removal_date = null;
                    $endorsementActivity->removal_notified = false;
                }
            }

            $endorsementActivity->save();

            $hours = round($activityMinutes / 60, 1);
            $status = $activityMinutes >= $minRequiredMinutes ? 'active' : 
                     ($activityMinutes >= $minRequiredMinutes * 0.5 ? 'warning' : 'low');

            $lastActivityStr = $lastActivityDate ? $lastActivityDate->format('Y-m-d') : 'Never';

            $this->info("✓ {$endorsementActivity->position}: {$activityMinutes} minutes ({$hours}h) - {$status} - Last active: {$lastActivityStr}");

        } catch (\Exception $e) {
            $this->error("✗ Failed to update {$endorsementActivity->position}: " . $e->getMessage());
            Log::error('Failed to update endorsement activity', [
                'endorsement_id' => $endorsementActivity->id,
                'vatsim_id' => $endorsementActivity->vatsim_id,
                'position' => $endorsementActivity->position,
                'error' => $e->getMessage()
            ]);
        }
    }
}