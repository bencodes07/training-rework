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
    $user = User::factory()->create(['vatsim_id' => 1234567]);
    $mentorRole = Role::factory()->create(['name' => 'EDGG Mentor']);
    
    $user->roles()->attach($mentorRole);

    expect($user->isMentor())->toBeTrue();
});

test('user is leadership when has leadership role', function () {
    $user = User::factory()->create(['vatsim_id' => 1234567]);
    $leadershipRole = Role::factory()->create(['name' => 'ATD Leitung']);
    
    $user->roles()->attach($leadershipRole);

    expect($user->isLeadership())->toBeTrue()
        ->and($user->isMentor())->toBeTrue(); // Leadership also counts as mentor
});

test('user is not mentor without mentor role', function () {
    $user = User::factory()->create(['vatsim_id' => 1234567]);

    expect($user->isMentor())->toBeFalse();
});

test('admin user is identified correctly', function () {
    $adminUser = User::factory()->create([
        'vatsim_id' => 9000001,
        'is_admin' => true,
    ]);

    $regularUser = User::factory()->create([
        'vatsim_id' => 1234567,
        'is_admin' => false,
    ]);

    expect($adminUser->isAdmin())->toBeTrue()
        ->and($regularUser->isAdmin())->toBeFalse();
});

test('vatsim user is identified correctly', function () {
    $vatsimUser = User::factory()->create([
        'vatsim_id' => 1234567,
    ]);

    $adminUser = User::factory()->create([
        'vatsim_id' => null,
        'is_admin' => true,
    ]);

    expect($vatsimUser->isVatsimUser())->toBeTrue()
        ->and($adminUser->isVatsimUser())->toBeFalse();
});

test('user has active tier 1 endorsements', function () {
    $user = User::factory()->create(['vatsim_id' => 1234567]);

    EndorsementActivity::create([
        'endorsement_id' => 1,
        'vatsim_id' => $user->vatsim_id,
        'position' => 'EDDF_TWR',
        'activity_minutes' => 200.0, // Above minimum
        'last_updated' => now(),
        'created_at_vateud' => now()->subDays(30),
    ]);

    expect($user->hasActiveTier1Endorsements())->toBeTrue();
});

test('user does not have active tier 1 endorsements with low activity', function () {
    $user = User::factory()->create(['vatsim_id' => 1234567]);

    EndorsementActivity::create([
        'endorsement_id' => 1,
        'vatsim_id' => $user->vatsim_id,
        'position' => 'EDDF_TWR',
        'activity_minutes' => 50.0, // Below minimum
        'last_updated' => now(),
        'created_at_vateud' => now()->subDays(30),
    ]);

    expect($user->hasActiveTier1Endorsements())->toBeFalse();
});

test('user endorsement summary is calculated correctly', function () {
    $user = User::factory()->create(['vatsim_id' => 1234567]);

    // Active endorsement
    EndorsementActivity::create([
        'endorsement_id' => 1,
        'vatsim_id' => $user->vatsim_id,
        'position' => 'EDDF_TWR',
        'activity_minutes' => 200.0,
        'last_updated' => now(),
        'created_at_vateud' => now()->subDays(30),
    ]);

    // Low activity endorsement
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
    $user = User::factory()->create(['vatsim_id' => 1234567]);

    EndorsementActivity::create([
        'endorsement_id' => 1,
        'vatsim_id' => $user->vatsim_id,
        'position' => 'EDDF_TWR',
        'activity_minutes' => 50.0, // Low activity
        'last_updated' => now(),
        'created_at_vateud' => now()->subDays(30),
    ]);

    expect($user->needsEndorsementAttention())->toBeTrue();
});

test('user needs endorsement attention with removal date', function () {
    $user = User::factory()->create(['vatsim_id' => 1234567]);

    EndorsementActivity::create([
        'endorsement_id' => 1,
        'vatsim_id' => $user->vatsim_id,
        'position' => 'EDDF_TWR',
        'activity_minutes' => 200.0,
        'removal_date' => now()->addDays(15), // Marked for removal
        'last_updated' => now(),
        'created_at_vateud' => now()->subDays(30),
    ]);

    expect($user->needsEndorsementAttention())->toBeTrue();
});

test('user route key uses vatsim_id', function () {
    $user = User::factory()->create(['vatsim_id' => 1234567]);

    expect($user->getRouteKeyName())->toBe('vatsim_id');
});

test('user has any role works correctly', function () {
    $user = User::factory()->create(['vatsim_id' => 1234567]);
    $edggMentor = Role::factory()->create(['name' => 'EDGG Mentor']);
    $edmmMentor = Role::factory()->create(['name' => 'EDMM Mentor']);
    
    $user->roles()->attach($edggMentor);

    expect($user->hasAnyRole(['EDGG Mentor', 'EDMM Mentor']))->toBeTrue()
        ->and($user->hasAnyRole(['EDMM Mentor', 'EDWW Mentor']))->toBeFalse();
});

test('mentor scope returns only mentors', function () {
    $mentor = User::factory()->create(['vatsim_id' => 1111111]);
    $trainee = User::factory()->create(['vatsim_id' => 2222222]);
    
    $mentorRole = Role::factory()->create(['name' => 'EDGG Mentor']);
    $mentor->roles()->attach($mentorRole);

    $mentors = User::mentors()->get();

    expect($mentors)->toHaveCount(1)
        ->and($mentors->first()->vatsim_id)->toBe(1111111);
});

test('leadership scope returns only leadership', function () {
    $leader = User::factory()->create(['vatsim_id' => 1111111]);
    $mentor = User::factory()->create(['vatsim_id' => 2222222]);
    
    $leadershipRole = Role::factory()->create(['name' => 'ATD Leitung']);
    $mentorRole = Role::factory()->create(['name' => 'EDGG Mentor']);
    
    $leader->roles()->attach($leadershipRole);
    $mentor->roles()->attach($mentorRole);

    $leadership = User::leadership()->get();

    expect($leadership)->toHaveCount(1)
        ->and($leadership->first()->vatsim_id)->toBe(1111111);
});

test('admin scope returns only admin users', function () {
    $admin = User::factory()->create([
        'vatsim_id' => 9000001,
        'is_admin' => true,
    ]);
    
    $regular = User::factory()->create([
        'vatsim_id' => 1234567,
        'is_admin' => false,
    ]);

    $admins = User::admins()->get();

    expect($admins)->toHaveCount(1)
        ->and($admins->first()->vatsim_id)->toBe(9000001);
});

test('vatsim users scope returns only vatsim users', function () {
    $vatsimUser = User::factory()->create([
        'vatsim_id' => 1234567,
    ]);
    
    $adminUser = User::factory()->create([
        'vatsim_id' => null,
        'is_admin' => true,
        'email' => 'admin@example.com',
    ]);

    $vatsimUsers = User::vatsimUsers()->get();

    expect($vatsimUsers)->toHaveCount(1)
        ->and($vatsimUsers->first()->vatsim_id)->toBe(1234567);
});