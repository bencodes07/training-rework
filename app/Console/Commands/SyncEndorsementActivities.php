<?php

namespace App\Console\Commands;

use App\Models\EndorsementActivity;
use App\Services\VatEudService;
use App\Services\VatsimActivityService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncEndorsementActivities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'endorsements:sync-activities 
                            {--limit=1 : Number of endorsements to update per run}
                            {--force : Force update all endorsements}
                            {--batch-size=50 : Size of batches when processing all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync endorsement activities from VATSIM and VatEUD (default: updates 1 endorsement per run, designed for frequent scheduling)';

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
        $this->info('Starting endorsement activity sync...');

        try {
            // First, sync with VatEUD to get current endorsements
            $this->syncWithVatEud();

            // Then update activity data - ONLY FOR TIER 1 (which require activity)
            if ($this->option('force')) {
                $this->updateAllActivities();
            } else {
                $this->updateStaleActivities();
            }

            $this->info('Endorsement activity sync completed successfully.');
            return 0;
        } catch (\Exception $e) {
            $this->error('Error during sync: ' . $e->getMessage());
            Log::error('Endorsement sync error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Sync current endorsements from VatEUD - FIXED: Only sync Tier 1 for activity tracking
     */
    protected function syncWithVatEud(): void
    {
        $this->info('Fetching Tier 1 endorsements from VatEUD for activity tracking...');

        // Only sync Tier 1 endorsements since they require activity tracking
        $tier1Endorsements = $this->vatEudService->getTier1Endorsements();
        
        $this->info('Found ' . count($tier1Endorsements) . ' Tier 1 endorsements');

        foreach ($tier1Endorsements as $endorsement) {
            $this->syncEndorsement($endorsement);
        }

        // Clean up endorsements that no longer exist in VatEUD (Tier 1 only)
        $this->cleanupRemovedEndorsements($tier1Endorsements);

        // Note: Tier 2 and Solo endorsements are fetched directly from VatEUD API when needed
        // They don't require activity tracking or local storage
    }

    /**
     * Sync individual Tier 1 endorsement for activity tracking
     */
    protected function syncEndorsement(array $endorsement): void
    {
        $createdAt = null;
        if (!empty($endorsement['created_at'])) {
            try {
                $createdAt = Carbon::createFromFormat('Y-m-d\TH:i:s.u\Z', $endorsement['created_at']);
            } catch (\Exception $e) {
                $createdAt = Carbon::createFromTimestamp(0);
            }
        }

        EndorsementActivity::updateOrCreate(
            ['endorsement_id' => $endorsement['id']],
            [
                'vatsim_id' => $endorsement['user_cid'],
                'position' => $endorsement['position'],
                'created_at_vateud' => $createdAt,
                'last_updated' => $createdAt ?? Carbon::createFromTimestamp(0),
            ]
        );
    }

    /**
     * Clean up endorsements that no longer exist in VatEUD (Tier 1 only)
     */
    protected function cleanupRemovedEndorsements(array $currentEndorsements): void
    {
        $currentIds = collect($currentEndorsements)->pluck('id')->toArray();
        
        $removedCount = EndorsementActivity::whereNotIn('endorsement_id', $currentIds)->delete();
        
        if ($removedCount > 0) {
            $this->info("Cleaned up {$removedCount} removed Tier 1 endorsements");
        }
    }

    /**
     * Update activities for endorsements that need updating (Tier 1 only)
     */
    protected function updateStaleActivities(): void
    {
        $limit = (int) $this->option('limit');
        
        $endorsements = EndorsementActivity::needsUpdate()
            ->limit($limit)
            ->get();

        if ($endorsements->isEmpty()) {
            $this->info('No Tier 1 endorsements need updating');
            return;
        }

        $this->info("Updating activity for {$endorsements->count()} Tier 1 endorsement(s)...");

        foreach ($endorsements as $endorsementActivity) {
            $this->updateEndorsementActivity($endorsementActivity);
        }
    }

    /**
     * Update all endorsement activities (Tier 1 only)
     */
    protected function updateAllActivities(): void
    {
        $batchSize = (int) $this->option('batch-size');
        $this->info("Force updating all Tier 1 endorsement activities in batches of {$batchSize}...");
        
        $totalCount = EndorsementActivity::count();
        $processedCount = 0;

        $this->info("Total Tier 1 endorsements to process: {$totalCount}");
        
        EndorsementActivity::chunk($batchSize, function ($endorsements) use (&$processedCount, $totalCount) {
            $this->info("Processing batch starting at endorsement " . ($processedCount + 1));
            
            foreach ($endorsements as $endorsementActivity) {
                $this->updateEndorsementActivity($endorsementActivity);
                $processedCount++;
                
                // Show progress every 10 endorsements
                if ($processedCount % 10 === 0) {
                    $this->info("Progress: {$processedCount}/{$totalCount}");
                }
            }
            
            // Small delay between batches
            $this->info("Batch complete. Waiting 2 seconds before next batch...");
            sleep(2);
        });

        $this->info("Completed updating {$processedCount} Tier 1 endorsements.");
    }

    /**
     * Update activity for a specific Tier 1 endorsement - UPDATED to handle last activity date
     */
    protected function updateEndorsementActivity(EndorsementActivity $endorsementActivity): void
    {
        try {
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

            // Handle removal logic
            if ($activityMinutes >= $minRequiredMinutes) {
                // Reset removal date if activity is sufficient
                $endorsementActivity->removal_date = null;
                $endorsementActivity->removal_notified = false;
            } else {
                // Check if eligible for removal and not already marked
                if ($endorsementActivity->isEligibleForRemoval() && !$endorsementActivity->removal_date) {
                    $endorsementActivity->removal_date = now()->addDays(
                        config('services.vateud.removal_warning_days', 31)
                    );
                    $endorsementActivity->removal_notified = false;
                }
            }

            $endorsementActivity->save();

            $lastActivityStr = $lastActivityDate ? $lastActivityDate->format('Y-m-d') : 'Never';
            $this->line("Updated {$endorsementActivity->position} for user {$endorsementActivity->vatsim_id}: {$activityMinutes} minutes, last active: {$lastActivityStr}");

        } catch (\Exception $e) {
            $this->error("Failed to update endorsement {$endorsementActivity->id}: " . $e->getMessage());
            Log::error('Failed to update endorsement activity', [
                'endorsement_id' => $endorsementActivity->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}