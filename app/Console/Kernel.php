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
        Commands\DebugUserEndorsements::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Every minute: Update one endorsement's activity
        $schedule->command('endorsements:sync-activities --limit=1')
            ->everyMinute()
            ->withoutOverlapping();

        // Daily at 9 AM: Send notifications and process removals
        $schedule->command('endorsements:remove --notify')
            ->dailyAt('09:00')
            ->withoutOverlapping();
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