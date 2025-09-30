<?php

namespace Tests\Feature;

use App\Models\EndorsementActivity;
use App\Models\User;
use App\Services\VatsimActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test users
    $this->vatsimUser = User::factory()->create([
        'vatsim_id' => 1234567,
        'first_name' => 'Test',
        'last_name' => 'User',
        'rating' => 3,
        'subdivision' => 'GER',
        'is_staff' => false,
    ]);

    $this->mentor = User::factory()->create([
        'vatsim_id' => 7654321,
        'first_name' => 'Mentor',
        'last_name' => 'User',
        'rating' => 5,
        'subdivision' => 'GER',
        'is_staff' => true,
        'is_superuser' => false,
    ]);
});

test('endorsement activity is created and tracked correctly', function () {
    $activity = EndorsementActivity::create([
        'endorsement_id' => 1,
        'vatsim_id' => $this->vatsimUser->vatsim_id,
        'position' => 'EDDF_TWR',
        'activity_minutes' => 150.0,
        'last_updated' => now(),
        'created_at_vateud' => now()->subDays(30),
    ]);

    expect($activity)->toBeInstanceOf(EndorsementActivity::class)
        ->and($activity->activity_minutes)->toBe(150.0)
        ->and($activity->activity_hours)->toBe(2.5)
        ->and($activity->position)->toBe('EDDF_TWR');
});

test('endorsement activity status is calculated correctly', function () {
    // Active status (>= 180 minutes)
    $activeEndorsement = EndorsementActivity::create([
        'endorsement_id' => 1,
        'vatsim_id' => $this->vatsimUser->vatsim_id,
        'position' => 'EDDF_TWR',
        'activity_minutes' => 200.0,
        'last_updated' => now(),
        'created_at_vateud' => now()->subDays(30),
    ]);

    expect($activeEndorsement->status)->toBe('active');

    // Warning status (90-180 minutes)
    $warningEndorsement = EndorsementActivity::create([
        'endorsement_id' => 2,
        'vatsim_id' => $this->vatsimUser->vatsim_id,
        'position' => 'EDDF_APP',
        'activity_minutes' => 120.0,
        'last_updated' => now(),
        'created_at_vateud' => now()->subDays(30),
    ]);

    expect($warningEndorsement->status)->toBe('warning');

    // Warning status for low activity (< 90 minutes)
    $lowActivityEndorsement = EndorsementActivity::create([
        'endorsement_id' => 3,
        'vatsim_id' => $this->vatsimUser->vatsim_id,
        'position' => 'EDDF_GNDDEL',
        'activity_minutes' => 50.0,
        'last_updated' => now(),
        'created_at_vateud' => now()->subDays(30),
    ]);

    expect($lowActivityEndorsement->status)->toBe('warning');
});

test('endorsement is eligible for removal when criteria met', function () {
    $oldEndorsement = EndorsementActivity::create([
        'endorsement_id' => 1,
        'vatsim_id' => $this->vatsimUser->vatsim_id,
        'position' => 'EDDF_TWR',
        'activity_minutes' => 50.0,
        'last_updated' => now(),
        'created_at_vateud' => now()->subDays(200), // Old enough
    ]);

    expect($oldEndorsement->isEligibleForRemoval())->toBeTrue();

    $recentEndorsement = EndorsementActivity::create([
        'endorsement_id' => 2,
        'vatsim_id' => $this->vatsimUser->vatsim_id,
        'position' => 'EDDF_APP',
        'activity_minutes' => 50.0,
        'last_updated' => now(),
        'created_at_vateud' => now()->subDays(30), // Too recent
    ]);

    expect($recentEndorsement->isEligibleForRemoval())->toBeFalse();
});

test('vatsim activity service calculates activity correctly for tower position', function () {
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

    $service = app(VatsimActivityService::class);
    $result = $service->getEndorsementActivity([
        'user_cid' => $this->vatsimUser->vatsim_id,
        'position' => 'EDDF_TWR',
    ]);

    // Should include TWR and APP connections (APP covers TWR)
    expect($result['minutes'])->toBe(150.0)
        ->and($result['last_activity_date'])->not->toBeNull();
});

test('vatsim activity service handles ctr position correctly', function () {
    Http::fake([
        'api.vatsim.net/*' => Http::response([
            'results' => [
                [
                    'callsign' => 'EDWW_W_CTR',
                    'minutes_on_callsign' => 120.0,
                    'start' => now()->subDays(5)->toIso8601String(),
                ],
                [
                    'callsign' => 'EDWW_CTR',
                    'minutes_on_callsign' => 60.0,
                    'start' => now()->subDays(10)->toIso8601String(),
                ],
            ],
        ], 200),
    ]);

    $service = app(VatsimActivityService::class);
    $result = $service->getEndorsementActivity([
        'user_cid' => $this->vatsimUser->vatsim_id,
        'position' => 'EDWW_W_CTR',
    ]);

    // Should match both EDWW_W_CTR prefix and EDWW_CTR special case
    expect($result['minutes'])->toBe(180.0);
});

test('endorsement activity progress is calculated correctly', function () {
    $endorsement = EndorsementActivity::create([
        'endorsement_id' => 1,
        'vatsim_id' => $this->vatsimUser->vatsim_id,
        'position' => 'EDDF_TWR',
        'activity_minutes' => 90.0, // 50% of 180 required
        'last_updated' => now(),
        'created_at_vateud' => now()->subDays(30),
    ]);

    expect($endorsement->progress)->toBe(50.0);

    $fullActivityEndorsement = EndorsementActivity::create([
        'endorsement_id' => 2,
        'vatsim_id' => $this->vatsimUser->vatsim_id,
        'position' => 'EDDF_APP',
        'activity_minutes' => 200.0, // Over 100%
        'last_updated' => now(),
        'created_at_vateud' => now()->subDays(30),
    ]);

    expect($fullActivityEndorsement->progress)->toBe(100.0);
});

test('trainee can view their endorsements', function () {
    EndorsementActivity::create([
        'endorsement_id' => 1,
        'vatsim_id' => $this->vatsimUser->vatsim_id,
        'position' => 'EDDF_TWR',
        'activity_minutes' => 150.0,
        'last_updated' => now(),
        'created_at_vateud' => now()->subDays(30),
    ]);

    $this->actingAs($this->vatsimUser)
        ->get(route('endorsements.trainee'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('endorsements/trainee')
            ->has('tier1Endorsements')
        );
});

test('mentor can view endorsement management page', function () {
    $this->actingAs($this->mentor)
        ->get(route('endorsements.manage'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('endorsements/manage')
        );
});

test('regular user cannot access endorsement management', function () {
    $this->actingAs($this->vatsimUser)
        ->get(route('endorsements.manage'))
        ->assertForbidden();
});

test('endorsement needs update scope returns stale endorsements', function () {
    // Recent update
    $recentEndorsement = EndorsementActivity::create([
        'endorsement_id' => 1,
        'vatsim_id' => $this->vatsimUser->vatsim_id,
        'position' => 'EDDF_TWR',
        'activity_minutes' => 150.0,
        'last_updated' => now(),
        'created_at_vateud' => now()->subDays(30),
    ]);

    // Old update
    $staleEndorsement = EndorsementActivity::create([
        'endorsement_id' => 2,
        'vatsim_id' => $this->vatsimUser->vatsim_id,
        'position' => 'EDDF_APP',
        'activity_minutes' => 150.0,
        'last_updated' => now()->subHours(2),
        'created_at_vateud' => now()->subDays(30),
    ]);

    $needsUpdate = EndorsementActivity::needsUpdate()->get();

    expect($needsUpdate)->toHaveCount(2)
        ->and($needsUpdate->first()->id)->toBe($staleEndorsement->id);
});