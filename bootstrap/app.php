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
        // Every minute: Update one endorsement's activity
        $schedule->command('endorsements:sync-activities', ['--limit' => 1])
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        // Daily at 9 AM: Send notifications and process removals
        $schedule->command('endorsements:remove', ['--notify'])
            ->dailyAt('09:00')
            ->withoutOverlapping()
            ->runInBackground();
    })
    ->create();
