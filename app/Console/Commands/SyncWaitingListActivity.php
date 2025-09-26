<?php

namespace App\Console\Commands;

use App\Models\WaitingListEntry;
use App\Services\VatsimActivityService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncWaitingListActivity extends Command
{
    protected $signature = 'waiting-lists:sync-activity {--limit=1 : Number of entries to update per run}';
    protected $description = 'Sync activity hours for waiting list entries from VATSIM API';

    protected VatsimActivityService $activityService;

    public function __construct(VatsimActivityService $activityService)
    {
        parent::__construct();
        $this->activityService = $activityService;
    }

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        
        // Get waiting list entries that need updating (RTG courses only)
        $entries = WaitingListEntry::with(['user', 'course'])
            ->whereHas('course', function ($query) {
                $query->where('type', 'RTG');
            })
            ->orderBy('hours_updated')
            ->limit($limit)
            ->get();

        if ($entries->isEmpty()) {
            $this->info('No waiting list entries need updating.');
            return 0;
        }

        foreach ($entries as $entry) {
            try {
                $this->info("Updating activity for {$entry->user->name} in {$entry->course->name}");
                
                $activityHours = $this->calculateActivityForCourse($entry);
                
                $entry->update([
                    'activity' => $activityHours,
                    'hours_updated' => now(),
                ]);
                
                $this->info("Updated activity: {$activityHours} hours");
                
            } catch (\Exception $e) {
                $this->error("Failed to update entry {$entry->id}: " . $e->getMessage());
                Log::error('Failed to update waiting list activity', [
                    'entry_id' => $entry->id,
                    'user_id' => $entry->user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return 0;
    }

    protected function calculateActivityForCourse(WaitingListEntry $entry): float
    {
        // This is a simplified version - you'd implement the actual activity calculation
        // based on your VATSIM activity service and the course requirements
        
        $course = $entry->course;
        $user = $entry->user;
        
        // For now, return a random value for testing
        // In production, this would call your activity service
        return round(rand(0, 50), 1);
    }
}