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
    protected $signature = 'endorsements:sync-activities 
                            {--limit=1 : Number of endorsements to update per run}
                            {--force : Force update all endorsements}
                            {--batch-size=50 : Size of batches when processing all}';

    protected $description = 'Sync endorsement activities from VATSIM and VatEUD (default: updates 1 endorsement per run, designed for frequent scheduling)';

    protected VatEudService $vatEudService;
    protected VatsimActivityService $activityService;

    public function __construct(VatEudService $vatEudService, VatsimActivityService $activityService)
    {
        parent::__construct();
        $this->vatEudService = $vatEudService;
        $this->activityService = $activityService;
    }

    public function handle(): int
    {
        $this->info('Starting endorsement activity sync...');

        try {
            $this->syncAllTier1Endorsements();

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

    protected function syncAllTier1Endorsements(): void
    {
        $this->info('Fetching ALL Tier 1 endorsements from VatEUD...');

        $tier1Endorsements = $this->vatEudService->getTier1Endorsements();
        
        $this->info('Found ' . count($tier1Endorsements) . ' Tier 1 endorsements');

        foreach ($tier1Endorsements as $endorsement) {
            try {
                $existingActivity = EndorsementActivity::where('endorsement_id', $endorsement['id'])->first();

                if ($existingActivity) {
                    continue;
                }

                $createdAt = null;
                if (!empty($endorsement['created_at'])) {
                    try {
                        $createdAt = Carbon::createFromFormat('Y-m-d\TH:i:s.u\Z', $endorsement['created_at']);
                    } catch (\Exception $e) {
                        $createdAt = Carbon::createFromTimestamp(1);
                    }
                }

                EndorsementActivity::create([
                    'endorsement_id' => $endorsement['id'],
                    'vatsim_id' => $endorsement['user_cid'],
                    'position' => $endorsement['position'],
                    'activity_minutes' => 0.0,
                    'created_at_vateud' => $createdAt ?? Carbon::createFromTimestamp(1),
                    'last_updated' => Carbon::createFromTimestamp(1),
                ]);

                $this->line("Created new activity record for endorsement {$endorsement['id']} (User: {$endorsement['user_cid']}, Position: {$endorsement['position']})");

            } catch (\Exception $e) {
                $this->error("Failed to sync endorsement {$endorsement['id']}: " . $e->getMessage());
                Log::error('Failed to sync endorsement', [
                    'endorsement_id' => $endorsement['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->cleanupRemovedEndorsements($tier1Endorsements);
    }

    protected function cleanupRemovedEndorsements(array $currentEndorsements): void
    {
        $currentIds = collect($currentEndorsements)->pluck('id')->toArray();
        
        $removedCount = EndorsementActivity::whereNotIn('endorsement_id', $currentIds)->delete();
        
        if ($removedCount > 0) {
            $this->info("Cleaned up {$removedCount} removed Tier 1 endorsements");
        }
    }

    protected function updateStaleActivities(): void
    {
        $limit = (int) $this->option('limit');
        
        $endorsements = EndorsementActivity::orderBy('last_updated', 'asc')
            ->limit($limit)
            ->get();

        if ($endorsements->isEmpty()) {
            $this->info('No endorsements need updating');
            return;
        }

        $this->info("Updating activity for {$endorsements->count()} endorsement(s)...");

        foreach ($endorsements as $endorsementActivity) {
            $this->updateEndorsementActivity($endorsementActivity);
        }
    }

    protected function updateAllActivities(): void
    {
        $batchSize = (int) $this->option('batch-size');
        $this->info("Force updating all endorsement activities in batches of {$batchSize}...");
        
        $totalCount = EndorsementActivity::count();
        $processedCount = 0;

        $this->info("Total endorsements to process: {$totalCount}");
        
        EndorsementActivity::chunk($batchSize, function ($endorsements) use (&$processedCount, $totalCount) {
            $this->info("Processing batch starting at endorsement " . ($processedCount + 1));
            
            foreach ($endorsements as $endorsementActivity) {
                $this->updateEndorsementActivity($endorsementActivity);
                $processedCount++;
                
                if ($processedCount % 10 === 0) {
                    $this->info("Progress: {$processedCount}/{$totalCount}");
                }
            }
            
            $this->info("Batch complete. Waiting 2 seconds before next batch...");
            sleep(2);
        });

        $this->info("Completed updating {$processedCount} endorsements.");
    }

    protected function updateEndorsementActivity(EndorsementActivity $endorsementActivity): void
    {
        try {
            $endorsementData = [
                'user_cid' => $endorsementActivity->vatsim_id,
                'position' => $endorsementActivity->position,
            ];

            $activityResult = $this->activityService->getEndorsementActivity($endorsementData);
            $activityMinutes = $activityResult['minutes'] ?? 0;
            $lastActivityDate = $activityResult['last_activity_date'] ?? null;

            $minRequiredMinutes = config('services.vateud.min_activity_minutes', 180);

            $endorsementActivity->activity_minutes = $activityMinutes;
            $endorsementActivity->last_activity_date = $lastActivityDate;
            $endorsementActivity->last_updated = now();

            if ($activityMinutes >= $minRequiredMinutes) {
                if ($endorsementActivity->removal_date) {
                    $this->info("✓ User {$endorsementActivity->vatsim_id} recovered activity for {$endorsementActivity->position}, clearing removal date");
                    $endorsementActivity->removal_date = null;
                    $endorsementActivity->removal_notified = false;
                }
            }

            $endorsementActivity->save();

            $lastActivityStr = $lastActivityDate ? $lastActivityDate->format('Y-m-d') : 'Never';
            $removalStatus = $endorsementActivity->removal_date
                ? " [REMOVAL: {$endorsementActivity->removal_date->format('Y-m-d')}]"
                : '';

            $this->line("Updated {$endorsementActivity->position} for user {$endorsementActivity->vatsim_id}: {$activityMinutes} min, last: {$lastActivityStr}{$removalStatus}");

        } catch (\Exception $e) {
            $this->error("Failed to update endorsement {$endorsementActivity->id}: " . $e->getMessage());
            Log::error('Failed to update endorsement activity', [
                'endorsement_id' => $endorsementActivity->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}