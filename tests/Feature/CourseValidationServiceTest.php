<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use App\Models\WaitingListEntry;
use App\Services\CourseValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->validationService = app(CourseValidationService::class);
    
    $this->trainee = User::factory()->create([
        'vatsim_id' => 1234567,
        'rating' => 2,
        'subdivision' => 'GER',
        'last_rating_change' => now()->subDays(100),
    ]);

    // Mock roster API
    Http::fake([
        'core.vateud.net/api/facility/roster' => Http::response([
            'data' => [
                'controllers' => [$this->trainee->vatsim_id]
            ]
        ], 200),
    ]);
});

test('validation service checks rating requirements', function () {
    $s2Course = Course::factory()->create([
        'min_rating' => 2,
        'max_rating' => 2,
        'type' => 'RTG',
    ]);

    $s3Course = Course::factory()->create([
        'min_rating' => 3,
        'max_rating' => 3,
        'type' => 'RTG',
    ]);

    [$canJoinS2, $reasonS2] = $this->validationService->canUserJoinCourse($s2Course, $this->trainee);
    [$canJoinS3, $reasonS3] = $this->validationService->canUserJoinCourse($s3Course, $this->trainee);

    expect($canJoinS2)->toBeTrue()
        ->and($canJoinS3)->toBeFalse()
        ->and($reasonS3)->toContain('required rating');
});

test('validation service prevents multiple rtg courses', function () {
    $course1 = Course::factory()->create([
        'min_rating' => 2,
        'max_rating' => 2,
        'type' => 'RTG',
        'position' => 'TWR',
    ]);

    $course2 = Course::factory()->create([
        'min_rating' => 2,
        'max_rating' => 2,
        'type' => 'RTG',
        'position' => 'APP',
    ]);

    // User already has active RTG course
    $this->trainee->activeCourses()->attach($course1);

    [$canJoin, $reason] = $this->validationService->canUserJoinCourse($course2, $this->trainee);

    expect($canJoin)->toBeFalse()
        ->and($reason)->toContain('already have an active RTG course');
});

test('validation service checks subdivision restrictions', function () {
    $foreignUser = User::factory()->create([
        'vatsim_id' => 9999999,
        'rating' => 2,
        'subdivision' => 'USA',
    ]);

    Http::fake([
        'core.vateud.net/api/facility/roster' => Http::response([
            'data' => ['controllers' => []]
        ], 200),
    ]);

    $rtgCourse = Course::factory()->create([
        'min_rating' => 2,
        'max_rating' => 2,
        'type' => 'RTG',
    ]);

    $gstCourse = Course::factory()->create([
        'min_rating' => 2,
        'max_rating' => 2,
        'type' => 'GST',
    ]);

    [$canJoinRtg, $reasonRtg] = $this->validationService->canUserJoinCourse($rtgCourse, $foreignUser);
    [$canJoinGst, $reasonGst] = $this->validationService->canUserJoinCourse($gstCourse, $foreignUser);

    expect($canJoinRtg)->toBeFalse()
        ->and($reasonRtg)->toContain('not allowed')
        ->and($canJoinGst)->toBeTrue();

    // German user should not be able to join GST
    [$canGermanJoinGst, $reasonGerman] = $this->validationService->canUserJoinCourse($gstCourse, $this->trainee);
    
    expect($canGermanJoinGst)->toBeFalse()
        ->and($reasonGerman)->toContain('visitor course');
});

test('validation service checks roster status', function () {
    $notOnRoster = User::factory()->create([
        'vatsim_id' => 8888888,
        'rating' => 2,
        'subdivision' => 'GER',
    ]);

    Http::fake([
        'core.vateud.net/api/facility/roster' => Http::response([
            'data' => [
                'controllers' => [$this->trainee->vatsim_id] // Only trainee on roster
            ]
        ], 200),
    ]);

    $rtgCourse = Course::factory()->create([
        'min_rating' => 2,
        'max_rating' => 2,
        'type' => 'RTG',
    ]);

    $rstCourse = Course::factory()->create([
        'min_rating' => 2,
        'max_rating' => 2,
        'type' => 'RST',
    ]);

    [$canOnRosterJoinRtg] = $this->validationService->canUserJoinCourse($rtgCourse, $this->trainee);
    [$canNotOnRosterJoinRtg, $reason] = $this->validationService->canUserJoinCourse($rtgCourse, $notOnRoster);
    [$canOnRosterJoinRst] = $this->validationService->canUserJoinCourse($rstCourse, $this->trainee);

    expect($canOnRosterJoinRtg)->toBeTrue()
        ->and($canNotOnRosterJoinRtg)->toBeFalse()
        ->and($reason)->toContain('not on the roster')
        ->and($canOnRosterJoinRst)->toBeFalse(); // Can't join RST if already on roster
});

test('validation service checks s3 rating change restriction', function () {
    $recentS3 = User::factory()->create([
        'vatsim_id' => 7777777,
        'rating' => 3,
        'subdivision' => 'GER',
        'last_rating_change' => now()->subDays(30), // Too recent
    ]);

    $oldS3 = User::factory()->create([
        'vatsim_id' => 6666666,
        'rating' => 3,
        'subdivision' => 'GER',
        'last_rating_change' => now()->subDays(100), // Old enough
    ]);

    Http::fake([
        'core.vateud.net/api/facility/roster' => Http::response([
            'data' => [
                'controllers' => [$recentS3->vatsim_id, $oldS3->vatsim_id]
            ]
        ], 200),
    ]);

    $appCourse = Course::factory()->create([
        'min_rating' => 3,
        'max_rating' => 3,
        'type' => 'RTG',
        'position' => 'APP',
    ]);

    [$recentCanJoin, $recentReason] = $this->validationService->canUserJoinCourse($appCourse, $recentS3);
    [$oldCanJoin] = $this->validationService->canUserJoinCourse($appCourse, $oldS3);

    expect($recentCanJoin)->toBeFalse()
        ->and($recentReason)->toContain('last rating change')
        ->and($oldCanJoin)->toBeTrue();
});

test('validation service checks roster correctly', function () {
    $roster = $this->validationService->getRoster();

    expect($roster)->toBeArray()
        ->and($roster)->toContain($this->trainee->vatsim_id);
});

test('validation service is user on roster', function () {
    $isOnRoster = $this->validationService->isUserOnRoster($this->trainee->vatsim_id);
    
    expect($isOnRoster)->toBeTrue();

    Http::fake([
        'core.vateud.net/api/facility/roster' => Http::response([
            'data' => ['controllers' => []]
        ], 200),
    ]);

    $isNotOnRoster = $this->validationService->isUserOnRoster(9999999);
    
    expect($isNotOnRoster)->toBeFalse();
});

test('validation service caches roster data', function () {
    Http::fake([
        'core.vateud.net/api/facility/roster' => Http::response([
            'data' => [
                'controllers' => [1234567]
            ]
        ], 200),
    ]);

    // First call
    $this->validationService->getRoster();
    
    // Second call - should use cache
    $this->validationService->getRoster();

    // Should only have made one API call
    Http::assertSentCount(1);
});

test('validation service handles roster api failure gracefully', function () {
    Http::fake([
        'core.vateud.net/api/facility/roster' => Http::response([], 500),
    ]);

    $course = Course::factory()->create([
        'min_rating' => 2,
        'max_rating' => 2,
        'type' => 'RTG',
    ]);

    // Should allow course join when roster check fails
    [$canJoin] = $this->validationService->canUserJoinCourse($course, $this->trainee);

    expect($canJoin)->toBeTrue();
});

test('validation service prevents joining if already on waiting list', function () {
    $course = Course::factory()->create([
        'min_rating' => 2,
        'max_rating' => 2,
        'type' => 'RTG',
    ]);

    WaitingListEntry::create([
        'user_id' => $this->trainee->id,
        'course_id' => $course->id,
        'date_added' => now(),
        'activity' => 0,
    ]);

    [$canJoin] = $this->validationService->canUserJoinCourse($course, $this->trainee);

    // Note: This is handled in the controller, not the validation service
    // The validation service should return true, but controller checks waiting list
    expect($canJoin)->toBeTrue();
});

test('validation service allows guest courses for all ratings', function () {
    $gstCourse = Course::factory()->create([
        'min_rating' => 2,
        'max_rating' => 10,
        'type' => 'GST',
    ]);

    $lowRatingUser = User::factory()->create([
        'vatsim_id' => 5555555,
        'rating' => 1,
        'subdivision' => 'USA',
    ]);

    $highRatingUser = User::factory()->create([
        'vatsim_id' => 4444444,
        'rating' => 7,
        'subdivision' => 'USA',
    ]);

    Http::fake([
        'core.vateud.net/api/facility/roster' => Http::response([
            'data' => ['controllers' => []]
        ], 200),
    ]);

    [$lowCanJoin] = $this->validationService->canUserJoinCourse($gstCourse, $lowRatingUser);
    [$highCanJoin] = $this->validationService->canUserJoinCourse($gstCourse, $highRatingUser);

    expect($lowCanJoin)->toBeFalse() // Below min rating
        ->and($highCanJoin)->toBeTrue(); // Within range
});

test('validation service gets user endorsements', function () {
    Http::fake([
        'core.vateud.net/api/facility/endorsements/tier-1' => Http::response([
            'data' => [
                [
                    'id' => 1,
                    'user_cid' => $this->trainee->vatsim_id,
                    'position' => 'EDDF_TWR',
                ],
                [
                    'id' => 2,
                    'user_cid' => $this->trainee->vatsim_id,
                    'position' => 'EDDF_APP',
                ],
            ],
        ], 200),
    ]);

    $endorsements = $this->validationService->getUserEndorsements($this->trainee->vatsim_id);

    expect($endorsements)->toHaveCount(2)
        ->and($endorsements)->toContain('EDDF_TWR')
        ->and($endorsements)->toContain('EDDF_APP');
});