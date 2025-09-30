<?php

namespace Tests\Feature;

use App\Models\EndorsementActivity;
use App\Models\User;
use App\Services\VatEudService;
use App\Services\VatsimActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('create admin command creates admin user', function () {
    Artisan::call('app:create-admin', [
        '--email' => 'admin@test.com',
        '--name' => 'Test Admin',
        '--password' => 'password123',
    ]);

    $this->assertDatabaseHas('users', [
        'email' => 'admin@test.com',
        'first_name' => 'Test',
        'last_name' => 'Admin',
        'is_admin' => true,
        'is_staff' => true,
        'is_superuser' => true,
    ]);

    $admin = User::where('email', 'admin@test.com')->first();
    expect($admin->vatsim_id)->toBeGreaterThan(9000000)
        ->and($admin->vatsim_id)->toBeLessThan(10000000);
});

test('create admin command validates email uniqueness', function () {
    User::factory()->create([
        'email' => 'existing@test.com',
        'vatsim_id' => 1234567,
    ]);

    Artisan::call('app:create-admin', [
        '--email' => 'existing@test.com',
        '--name' => 'Test Admin',
        '--password' => 'password123',
    ]);

    $output = Artisan::output();
    expect($output)->toContain('Validation failed');
});

test('setup database command runs migrations and seeders', function () {
    Artisan::call('app:setup-database');

    $this->assertDatabaseHas('roles', [
        'name' => 'EDGG Mentor',
    ]);

    $this->assertDatabaseHas('roles', [
        'name' => 'ATD Leitung',
    ]);
});

test('sync endorsement activities command syncs from vateud', function () {
    Http::fake([
        'core.vateud.net/api/facility/endorsements/tier-1' => Http::response([
            'data' => [
                [
                    'id' => 1,
                    'user_cid' => 1234567,
                    'instructor_cid' => 7654321,
                    'position' => 'EDDF_TWR',
                    'facility' => 9,
                    'created_at' => '2025-01-15T12:00:00.000000Z',
                ],
            ],
        ], 200),
        'api.vatsim.net/*' => Http::response([
            'results' => [
                [
                    'callsign' => 'EDDF_TWR',
                    'minutes_on_callsign' => 200.0,
                    'start' => now()->subDays(5)->toIso8601String(),
                ],
            ],
        ], 200),
    ]);

    Artisan::call('endorsements:sync-activities', ['--limit' => 1]);

    $this->assertDatabaseHas('endorsement_activities', [
        'endorsement_id' => 1,
        'vatsim_id' => 1234567,
        'position' => 'EDDF_TWR',
    ]);

    $activity = EndorsementActivity::where('endorsement_id', 1)->first();
    expect($activity->activity_minutes)->toBeGreaterThan(0);
});

test('sync user endorsements command syncs specific user', function () {
    $user = User::factory()->create(['vatsim_id' => 1234567]);

    Http::fake([
        'core.vateud.net/api/facility/endorsements/tier-1' => Http::response([
            'data' => [
                [
                    'id' => 1,
                    'user_cid' => 1234567,
                    'position' => 'EDDF_TWR',
                    'created_at' => '2025-01-15T12:00:00.000000Z',
                ],
            ],
        ], 200),
        'api.vatsim.net/*' => Http::response([
            'results' => [
                [
                    'callsign' => 'EDDF_TWR',
                    'minutes_on_callsign' => 150.0,
                    'start' => now()->subDays(5)->toIso8601String(),
                ],
            ],
        ], 200),
    ]);

    Artisan::call('endorsements:sync-user', ['vatsim_id' => 1234567]);

    $this->assertDatabaseHas('endorsement_activities', [
        'vatsim_id' => 1234567,
        'position' => 'EDDF_TWR',
    ]);
});

test('debug user activity command provides detailed output', function () {
    $user = User::factory()->create(['vatsim_id' => 1234567]);

    Http::fake([
        'api.vatsim.net/*' => Http::response([
            'results' => [
                [
                    'callsign' => 'EDDF_TWR',
                    'minutes_on_callsign' => 60.0,
                    'start' => now()->subDays(5)->toIso8601String(),
                ],
                [
                    'callsign' => 'EDDF_APP',
                    'minutes_on_callsign' => 90.0,
                    'start' => now()->subDays(10)->toIso8601String(),
                ],
            ],
        ], 200),
    ]);

    Artisan::call('debug:user-activity', ['vatsim_id' => 1234567]);

    $output = Artisan::output();
    expect($output)->toContain('Debugging activity for VATSIM ID: 1234567')
        ->and($output)->toContain('Found 2 ATC sessions');
});

test('sync activities command respects limit parameter', function () {
    // Create multiple endorsements
    for ($i = 1; $i <= 5; $i++) {
        EndorsementActivity::create([
            'endorsement_id' => $i,
            'vatsim_id' => 1234560 + $i,
            'position' => 'EDDF_TWR',
            'activity_minutes' => 0,
            'last_updated' => now()->subHours(2),
            'created_at_vateud' => now()->subDays(30),
        ]);
    }

    Http::fake([
        'core.vateud.net/*' => Http::response(['data' => []], 200),
        'api.vatsim.net/*' => Http::response(['results' => []], 200),
    ]);

    // Only sync 2 endorsements
    Artisan::call('endorsements:sync-activities', ['--limit' => 2]);

    // Should have made API calls for only 2 users
    Http::assertSentCount(2); // 2 VATSIM API calls (one per user)
});