<?php

namespace Tests\Feature;

use App\Models\EndorsementActivity;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user full name attribute works correctly', function () {
    $user = User::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    expect($user->full_name)->toBe('John Doe')
        ->and($user->name)->toBe('John Doe');
});

test('user is mentor when has mentor role', function () {
    $user = User::factory()->create();
    $mentorRole = Role::factory()->create(['name' => 'EDGG Mentor']);
    
    $user->roles()->attach($mentorRole);

    expect($user->isMentor())->toBeTrue();
});

test('user is leadership when has leadership role', function () {
    $user = User::factory()->create();
    $leadershipRole = Role::factory()->create(['name' => 'ATD Leitung']);
    
    $user->roles()->attach($leadershipRole);

    expect($user->isLeadership())->toBeTrue()
        ->and($user->isMentor())->toBeTrue(); // Leadership also counts as mentor
});

test('user is not mentor without mentor role', function () {
    $user = User::factory()->create();

    expect($user->isMentor())->toBeFalse();
});

test('admin user is identified correctly', function () {
    $adminUser = User::factory()->admin()->create();
    $regularUser = User::factory()->create();

    expect($adminUser->isAdmin())->toBeTrue()
        ->and($regularUser->isAdmin())->toBeFalse();
});

test('vatsim user is identified correctly', function () {
    $vatsimUser = User::factory()->create();

    expect($vatsimUser->isVatsimUser())->toBeTrue();
});

test('user has active tier 1 endorsements', function () {
    $user = User::factory()->create();

    EndorsementActivity::create([
        'endorsement_id' => 1,
        'vatsim_id' => $user->vatsim_id,
        'position' => 'EDDF_TWR',
        'activity_minutes' => 200.0,
        'last_updated' => now(),
        'created_at_vateud' => now()->subDays(30),
    ]);

    expect($user->hasActiveTier1Endorsements())->toBeTrue();
});

test('user does not have active tier 1 endorsements with low activity', function () {
    $user = User::factory()->create();

    EndorsementActivity::create([
        'endorsement_id' => 1,
        'vatsim_id' => $user->vatsim_id,
        'position' => 'EDDF_TWR',
        'activity_minutes' => 50.0,
        'last_updated' => now(),
        'created_at_vateud' => now()->subDays(30),
    ]);

    expect($user->hasActiveTier1Endorsements())->toBeFalse();
});

test('user endorsement summary is calculated correctly', function () {
    $user = User::factory()->create();

    EndorsementActivity::create([
        'endorsement_id' => 1,
        'vatsim_id' => $user->vatsim_id,
        'position' => 'EDDF_TWR',
        'activity_minutes' => 200.0,
        'last_updated' => now(),
        'created_at_vateud' => now()->subDays(30),
    ]);

    EndorsementActivity::create([
        'endorsement_id' => 2,
        'vatsim_id' => $user->vatsim_id,
        'position' => 'EDDF_APP',
        'activity_minutes' => 50.0,
        'last_updated' => now(),
        'created_at_vateud' => now()->subDays(30),
    ]);

    $summary = $user->getEndorsementSummary();

    expect($summary['tier1_count'])->toBe(2)
        ->and($summary['low_activity_count'])->toBe(1);
});

test('user needs endorsement attention with low activity', function () {
    $user = User::factory()->create();

    EndorsementActivity::create([
        'endorsement_id' => 1,
        'vatsim_id' => $user->vatsim_id,
        'position' => 'EDDF_TWR',
        'activity_minutes' => 50.0,
        'last_updated' => now(),
        'created_at_vateud' => now()->subDays(30),
    ]);

    expect($user->needsEndorsementAttention())->toBeTrue();
});

test('user needs endorsement attention with removal date', function () {
    $user = User::factory()->create();

    EndorsementActivity::create([
        'endorsement_id' => 1,
        'vatsim_id' => $user->vatsim_id,
        'position' => 'EDDF_TWR',
        'activity_minutes' => 200.0,
        'removal_date' => now()->addDays(15),
        'last_updated' => now(),
        'created_at_vateud' => now()->subDays(30),
    ]);

    expect($user->needsEndorsementAttention())->toBeTrue();
});

test('user route key uses vatsim_id', function () {
    $user = User::factory()->create();

    expect($user->getRouteKeyName())->toBe('vatsim_id');
});

test('user has any role works correctly', function () {
    $user = User::factory()->create();
    $edggMentor = Role::factory()->create(['name' => 'EDGG Mentor']);
    $edmmMentor = Role::factory()->create(['name' => 'EDMM Mentor']);
    
    $user->roles()->attach($edggMentor);

    expect($user->hasAnyRole(['EDGG Mentor', 'EDMM Mentor']))->toBeTrue()
        ->and($user->hasAnyRole(['EDMM Mentor', 'EDWW Mentor']))->toBeFalse();
});

test('mentor scope returns only mentors', function () {
    $mentor = User::factory()->create();
    $trainee = User::factory()->create();
    
    $mentorRole = Role::factory()->create(['name' => 'EDGG Mentor']);
    $mentor->roles()->attach($mentorRole);

    $mentors = User::mentors()->get();

    expect($mentors)->toHaveCount(1)
        ->and($mentors->first()->id)->toBe($mentor->id);
});

test('leadership scope returns only leadership', function () {
    $leader = User::factory()->create();
    $mentor = User::factory()->create();
    
    $leadershipRole = Role::factory()->create(['name' => 'ATD Leitung']);
    $mentorRole = Role::factory()->create(['name' => 'EDGG Mentor']);
    
    $leader->roles()->attach($leadershipRole);
    $mentor->roles()->attach($mentorRole);

    $leadership = User::leadership()->get();

    expect($leadership)->toHaveCount(1)
        ->and($leadership->first()->id)->toBe($leader->id);
});

test('admin scope returns only admin users', function () {
    $admin = User::factory()->admin()->create();
    $regular = User::factory()->create();

    $admins = User::admins()->get();

    expect($admins)->toHaveCount(1)
        ->and($admins->first()->id)->toBe($admin->id);
});

test('vatsim users scope returns only vatsim users', function () {
    $vatsimUser = User::factory()->create();

    $vatsimUsers = User::vatsimUsers()->get();

    expect($vatsimUsers->count())->toBeGreaterThan(0);
});