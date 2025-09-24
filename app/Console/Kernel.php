<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\SyncEndorsementActivities::class,
        Commands\RemoveEndorsements::class,
        Commands\SyncUserEndorsements::class,
        Commands\DebugUserActivity::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Sync one endorsement activity every minute (matches Python behavior)
        $schedule->command('endorsements:sync-activities --limit=1')
            ->everyMinute()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/endorsement-sync.log'));
        
        // Process removals and notifications daily at 9 AM
        $schedule->command('endorsements:remove --notify')
            ->dailyAt('09:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/endorsement-removal.log'));

        // For testing - you can uncomment this to run every 5 minutes instead
        // $schedule->command('endorsements:sync-activities --limit=5')
        //     ->everyFiveMinutes()
        //     ->withoutOverlapping()
        //     ->appendOutputTo(storage_path('logs/endorsement-sync.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}