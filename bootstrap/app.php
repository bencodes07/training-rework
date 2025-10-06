<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withSchedule(function ($schedule) {
        $schedule->command('endorsements:sync-activities', ['--limit' => 1])
            ->everyThreeMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Every 3 minutes starting at minute 1: Process endorsement removals (1-59/3 * * * *)
        $schedule->command('endorsements:remove', ['--notify'])
            ->cron('1-59/3 * * * *')
            ->withoutOverlapping()
            ->runInBackground();

        // Every 3 minutes starting at minute 2: Update waiting list activities (2-59/3 * * * *)
        $schedule->command('waitinglist:sync-activity', ['--limit' => 1])
            ->cron('2-59/3 * * * *')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('roster:check')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();

        /* 
                // Daily at midnight: Clean waiting lists (0 0 * * *)
                $schedule->command('waitinglist:clean')
                    ->dailyAt('00:00')
                    ->withoutOverlapping()
                    ->runInBackground();
         */
    })
    ->create();
