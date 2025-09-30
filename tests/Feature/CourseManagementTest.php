<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\FamiliarisationSector;
use App\Models\Role;
use App\Models\User;
use App\Models\WaitingListEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create roles
    $this->edggMentor = Role::factory()->create(['name' => 'EDGG Mentor']);
    $this->edmmMentor = Role::factory()->create(['name' => 'EDMM Mentor']);
    
    // Create users
    $this->trainee = User::factory()->create([
        'vatsim_id' => 1234567,
        'first_name' => 'Test',
        'last_name' => 'Trainee',
        'rating' => 2,
        'subdivision' => 'GER',
        'is_staff' => false,
        'last_rating_change' => now()->subDays(100),
    ]);

    $this->mentor = User::factory()->create([
        'vatsim_id' => 7654321,
        'first_name' => 'Mentor',
        'last_name' => 'User',
        'rating' => 5,
        'subdivision' => 'GER',
        'is_staff' => true,
    ]);
    
    $this->mentor->roles()->attach($this->edggMentor);

    // Mock roster API
    Http::fake([
        'core.vateud.net/api/facility/roster' => Http::response([
            'data' => [
                'controllers' => [$this->trainee->vatsim_id]
            ]
        ], 200),
    ]);
});

test('trainee can view available courses', function () {
    $course = Course::factory()->create([
        'name' => 'Frankfurt Tower S2',
        'airport_icao' => 'EDDF',
        'mentor_group_id' => $this->edggMentor->id,
        'min_rating' => 2,
        'max_rating' => 2,
        'type' => 'RTG',
        'position' => 'TWR',
    ]);

    $this->actingAs($this->trainee)
        ->get(route('courses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('training/courses')
            ->has('courses', 1)
        );
});

test('course filters by rating correctly', function () {
    // S2 course (rating 2)
    $s2Course = Course::factory()->create([
        'name' => 'Frankfurt Tower S2',
        'min_rating' => 2,
        'max_rating' => 2,
        'type' => 'RTG',
        'position' => 'TWR',
    ]);

    // S3 course (rating 3)
    $s3Course = Course::factory()->create([
        'name' => 'Frankfurt Approach S3',
        'min_rating' => 3,
        'max_rating' => 3,
        'type' => 'RTG',
        'position' => 'APP',
    ]);

    $this->actingAs($this->trainee) // Rating 2
        ->get(route('courses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('courses', 1) // Should only see S2 course
        );
});

test('trainee can join waiting list', function () {
    $course = Course::factory()->create([
        'name' => 'Frankfurt Tower S2',
        'mentor_group_id' => $this->edggMentor->id,
        'min_rating' => 2,
        'max_rating' => 2,
        'type' => 'RTG',
        'position' => 'TWR',
    ]);

    $this->actingAs($this->trainee)
        ->post(route('courses.toggle-waiting-list', $course))
        ->assertRedirect();

    $this->assertDatabaseHas('waiting_list_entries', [
        'user_id' => $this->trainee->id,
        'course_id' => $course->id,
    ]);
});

test('trainee can leave waiting list', function () {
    $course = Course::factory()->create([
        'min_rating' => 2,
        'max_rating' => 2,
        'type' => 'RTG',
        'position' => 'TWR',
    ]);

    WaitingListEntry::create([
        'user_id' => $this->trainee->id,
        'course_id' => $course->id,
        'date_added' => now(),
        'activity' => 0,
    ]);

    $this->actingAs($this->trainee)
        ->post(route('courses.toggle-waiting-list', $course))
        ->assertRedirect();

    $this->assertDatabaseMissing('waiting_list_entries', [
        'user_id' => $this->trainee->id,
        'course_id' => $course->id,
    ]);
});

test('trainee cannot join multiple rtg courses', function () {
    $course1 = Course::factory()->create([
        'name' => 'Course 1',
        'min_rating' => 2,
        'max_rating' => 2,
        'type' => 'RTG',
        'position' => 'TWR',
    ]);

    $course2 = Course::factory()->create([
        'name' => 'Course 2',
        'min_rating' => 2,
        'max_rating' => 2,
        'type' => 'RTG',
        'position' => 'APP',
    ]);

    // Join first course
    WaitingListEntry::create([
        'user_id' => $this->trainee->id,
        'course_id' => $course1->id,
        'date_added' => now(),
        'activity' => 0,
    ]);

    // Try to join second RTG course
    $response = $this->actingAs($this->trainee)
        ->post(route('courses.toggle-waiting-list', $course2));

    $this->assertDatabaseMissing('waiting_list_entries', [
        'user_id' => $this->trainee->id,
        'course_id' => $course2->id,
    ]);
});

test('waiting list position is calculated correctly', function () {
    $course = Course::factory()->create([
        'min_rating' => 2,
        'max_rating' => 2,
        'type' => 'RTG',
        'position' => 'TWR',
    ]);

    $user1 = User::factory()->create(['vatsim_id' => 1111111]);
    $user2 = User::factory()->create(['vatsim_id' => 2222222]);
    $user3 = User::factory()->create(['vatsim_id' => 3333333]);

    // Add users to waiting list at different times
    $entry1 = WaitingListEntry::create([
        'user_id' => $user1->id,
        'course_id' => $course->id,
        'date_added' => now()->subDays(3),
        'activity' => 0,
    ]);

    $entry2 = WaitingListEntry::create([
        'user_id' => $user2->id,
        'course_id' => $course->id,
        'date_added' => now()->subDays(2),
        'activity' => 0,
    ]);

    $entry3 = WaitingListEntry::create([
        'user_id' => $user3->id,
        'course_id' => $course->id,
        'date_added' => now()->subDays(1),
        'activity' => 0,
    ]);

    expect($entry1->position_in_queue)->toBe(1)
        ->and($entry2->position_in_queue)->toBe(2)
        ->and($entry3->position_in_queue)->toBe(3);
});

test('mentor can view waiting lists', function () {
    $course = Course::factory()->create([
        'mentor_group_id' => $this->edggMentor->id,
        'min_rating' => 2,
        'max_rating' => 2,
        'type' => 'RTG',
        'position' => 'TWR',
    ]);

    WaitingListEntry::create([
        'user_id' => $this->trainee->id,
        'course_id' => $course->id,
        'date_added' => now(),
        'activity' => 15.5,
    ]);

    $this->actingAs($this->mentor)
        ->get(route('waiting-lists.manage'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('training/mentor-waiting-lists')
            ->has('courses')
        );
});

test('mentor can update waiting list remarks', function () {
    $course = Course::factory()->create([
        'mentor_group_id' => $this->edggMentor->id,
    ]);

    $entry = WaitingListEntry::create([
        'user_id' => $this->trainee->id,
        'course_id' => $course->id,
        'date_added' => now(),
        'activity' => 10.0,
    ]);

    $this->actingAs($this->mentor)
        ->post(route('waiting-lists.update-remarks'), [
            'entry_id' => $entry->id,
            'remarks' => 'Good progress',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('waiting_list_entries', [
        'id' => $entry->id,
        'remarks' => 'Good progress',
    ]);
});

test('waiting time is formatted correctly', function () {
    $course = Course::factory()->create();
    
    $entry = WaitingListEntry::create([
        'user_id' => $this->trainee->id,
        'course_id' => $course->id,
        'date_added' => now(),
        'activity' => 0,
    ]);

    expect($entry->waiting_time)->toBe('Today');

    $entry->date_added = now()->subDays(1);
    expect($entry->waiting_time)->toBe('1 day');

    $entry->date_added = now()->subDays(5);
    expect($entry->waiting_time)->toBe('5 days');

    $entry->date_added = now()->subDays(14);
    expect($entry->waiting_time)->toContain('weeks');

    $entry->date_added = now()->subDays(60);
    expect($entry->waiting_time)->toContain('months');
});

test('non german users cannot join rtg courses', function () {
    $foreignUser = User::factory()->create([
        'vatsim_id' => 9999999,
        'rating' => 2,
        'subdivision' => 'USA', // Not GER
    ]);

    Http::fake([
        'core.vateud.net/api/facility/roster' => Http::response([
            'data' => ['controllers' => []]
        ], 200),
    ]);

    $course = Course::factory()->create([
        'min_rating' => 2,
        'max_rating' => 2,
        'type' => 'RTG',
        'position' => 'TWR',
    ]);

    $this->actingAs($foreignUser)
        ->post(route('courses.toggle-waiting-list', $course))
        ->assertRedirect();

    $this->assertDatabaseMissing('waiting_list_entries', [
        'user_id' => $foreignUser->id,
        'course_id' => $course->id,
    ]);
});