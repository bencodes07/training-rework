<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use App\Models\WaitingListEntry;
use App\Services\CourseValidationService;
use App\Services\WaitingListService;
use App\Services\FamiliarisationService;
use App\Services\MoodleService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    protected CourseValidationService $validationService;
    protected WaitingListService $waitingListService;
    protected FamiliarisationService $familiarisationService;
    protected MoodleService $moodleService;

    public function __construct(
        CourseValidationService $validationService,
        WaitingListService $waitingListService,
        FamiliarisationService $familiarisationService,
        MoodleService $moodleService
    ) {
        $this->validationService = $validationService;
        $this->waitingListService = $waitingListService;
        $this->familiarisationService = $familiarisationService;
        $this->moodleService = $moodleService;
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        
        if (!$user->isVatsimUser()) {
            return Inertia::render('training/courses', [
                'courses' => [],
                'userWaitingLists' => [],
                'isVatsimUser' => false,
                'moodleSignedUp' => false,
                'error' => 'VATSIM account required to view courses',
            ]);
        }

        $moodleSignedUp = $this->moodleService->userExists($user->vatsim_id);

        try {
            if ($user->is_admin || $user->is_superuser) {
                $courses = Course::with(['mentorGroup', 'familiarisationSector'])->get();
                $filteredCourses = $courses;
            } else {
                $courses = Course::forRating(rating: $user->rating)
                    ->availableFor($user)
                    ->with(['mentorGroup', 'familiarisationSector'])
                    ->get();

                $filteredCourses = $this->filterCoursesForUser($courses, $user);
            }

            $waitingListEntries = WaitingListEntry::with('course')
                ->where('user_id', $user->id)
                ->get();

            $userHasActiveRtgCourse = $user->activeRatingCourses()->exists() ||
                $user->waitingListEntries()->whereHas('course', function ($q) {
                    $q->where('type', 'RTG');
                })->exists();

            $formattedCourses = $filteredCourses->map(function ($course) use ($user, $waitingListEntries, $moodleSignedUp) {
                $entry = $waitingListEntries->firstWhere('course_id', $course->id);

                $courseData = [
                    'id' => $course->id,
                    'name' => $course->name,
                    'trainee_display_name' => $course->trainee_display_name,
                    'description' => $course->description,
                    'airport_name' => $course->airport_name,
                    'airport_icao' => $course->airport_icao,
                    'type' => $course->type,
                    'type_display' => $course->type_display,
                    'position' => $course->position,
                    'position_display' => $course->position_display,
                    'mentor_group' => $course->mentorGroup?->name,
                    'min_rating' => $course->min_rating,
                    'max_rating' => $course->max_rating,
                    'is_on_waiting_list' => $entry !== null,
                    'waiting_list_position' => $entry?->position_in_queue,
                    'waiting_list_activity' => $entry?->activity,
                    'can_join' => $this->validationService->canUserJoinCourse($course, $user)[0],
                    'join_error' => $this->validationService->canUserJoinCourse($course, $user)[1],
                ];

                if ($course->type === 'EDMT' && !empty($course->moodle_course_ids) && $moodleSignedUp) {
                    $courseData['moodle_completed'] = $this->moodleService->checkAllCoursesCompleted(
                        $user->vatsim_id,
                        $course->moodle_course_ids
                    );
                }

                return $courseData;
            });

            return Inertia::render('training/courses', [
                'courses' => $formattedCourses,
                'isVatsimUser' => true,
                'moodleSignedUp' => $moodleSignedUp,
                'userHasActiveRtgCourse' => $userHasActiveRtgCourse,
            ]);

        } catch (\Exception $e) {
            return Inertia::render('training/courses', [
                'courses' => [],
                'isVatsimUser' => true,
                'moodleSignedUp' => $moodleSignedUp ?? false,
                'error' => 'Failed to load courses. Please try again.',
            ]);
        }
    }

    public function toggleWaitingList(Request $request, Course $course): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        if (!$user->isVatsimUser()) {
            return back()->with('flash', [
                'success' => false,
                'message' => 'VATSIM account required',
                'action' => 'error'
            ]);
        }

        // Check if user is signed up on Moodle
        if (!$this->moodleService->userExists($user->vatsim_id)) {
            return back()->with('flash', [
                'success' => false,
                'message' => 'You must sign up on Moodle before joining a waiting list',
                'action' => 'error'
            ]);
        }

        try {
            $entry = WaitingListEntry::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->first();

            if ($entry) {
                [$success, $message] = $this->waitingListService->leaveWaitingList($course, $user);

                return back()->with('flash', [
                    'success' => $success,
                    'message' => $message,
                    'action' => $success ? 'left' : 'error',
                ]);
            } else {
                [$success, $message] = $this->waitingListService->joinWaitingList($course, $user);

                if ($success) {
                    $newEntry = WaitingListEntry::where('user_id', $user->id)
                        ->where('course_id', $course->id)
                        ->first();

                    \Log::info('New waiting list entry created', [
                        'user_id' => $user->id,
                        'course_id' => $course->id,
                        'position' => $newEntry?->position_in_queue,
                        'entry_exists' => $newEntry !== null
                    ]);

                    return back()->with('flash', [
                        'success' => true,
                        'message' => $message,
                        'action' => 'joined',
                        'position' => $newEntry ? $newEntry->position_in_queue : 1,
                    ]);
                } else {
                    return back()->with('flash', [
                        'success' => false,
                        'message' => $message,
                        'action' => 'error'
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error in toggleWaitingList', [
                'user_id' => $user->id,
                'course_id' => $course->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('flash', [
                'success' => false,
                'message' => 'An error occurred. Please try again.',
                'action' => 'error'
            ]);
        }
    }

    protected function filterCoursesForUser($courses, User $user)
    {
        return $courses->filter(function ($course) use ($user) {
            if ($user->subdivision === 'GER') {

                try {
                    $isOnRoster = $this->validationService->isUserOnRoster($user->vatsim_id);
                    
                    if ($course->type === 'RST' && $isOnRoster) return false;
                    if ($course->type !== 'RST' && !$isOnRoster) return false;
                } catch (\Exception $e) {
                    if ($course->type === 'RST') return false;
                }
            } else {
                if ($course->type === 'RTG') return false;
            }

            if ($course->type === 'RTG' && $user->activeRatingCourses()->exists()) {
                return false;
            }

            if ($user->rating === 3 && $course->type === 'RTG' && $course->position === 'APP') {
                $minDays = config('services.training.s3_rating_change_days', 90);
                if ($user->last_rating_change && 
                    now()->diffInDays($user->last_rating_change) < $minDays) {
                    return false;
                }
            }

            return true;
        });
    }
}