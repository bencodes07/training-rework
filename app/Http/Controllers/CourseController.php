<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use App\Models\WaitingListEntry;
use App\Services\CourseValidationService;
use App\Services\WaitingListService;
use App\Services\FamiliarisationService;
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

    public function __construct(
        CourseValidationService $validationService,
        WaitingListService $waitingListService,
        FamiliarisationService $familiarisationService
    ) {
        $this->validationService = $validationService;
        $this->waitingListService = $waitingListService;
        $this->familiarisationService = $familiarisationService;
    }

    /**
     * Show available courses for trainees
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        
        if (!$user->isVatsimUser()) {
            return Inertia::render('training/courses', [
                'courses' => [],
                'userWaitingLists' => [],
                'isVatsimUser' => false,
                'error' => 'VATSIM account required to view courses',
            ]);
        }

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

            // Get user's current waiting list entries
            $waitingListEntries = WaitingListEntry::with('course')
                ->where('user_id', $user->id)
                ->get();

            // Check if user has an active RTG course (either in training or on waiting list)
            $userHasActiveRtgCourse = $user->activeRatingCourses()->exists() ||
                $waitingListEntries->whereIn('course.type', ['RTG'])->isNotEmpty();

            // Format courses for frontend
            $formattedCourses = $filteredCourses->map(function ($course) use ($user, $waitingListEntries) {
                $entry = $waitingListEntries->firstWhere('course_id', $course->id);
                
                return [
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
            });

            return Inertia::render('training/courses', [
                'courses' => $formattedCourses,
                'isVatsimUser' => true,
                'userHasActiveRtgCourse' => $userHasActiveRtgCourse,
            ]);

        } catch (\Exception $e) {
            return Inertia::render('training/courses', [
                'courses' => [],
                'isVatsimUser' => true,
                'error' => 'Failed to load courses. Please try again.',
            ]);
        }
    }

    /**
     * Join or leave a waiting list
     */
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

        try {
            $entry = WaitingListEntry::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->first();

            if ($entry) {
                // Leave waiting list
                [$success, $message] = $this->waitingListService->leaveWaitingList($course, $user);

                return back()->with('flash', [
                    'success' => $success,
                    'message' => $message,
                    'action' => $success ? 'left' : 'error',
                ]);
            } else {
                // Join waiting list
                [$success, $message] = $this->waitingListService->joinWaitingList($course, $user);
                
                if ($success) {
                    // Refresh the entry to get the exact position
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

    /**
     * Filter courses based on user's specific requirements - UPDATED
     */
    protected function filterCoursesForUser($courses, User $user)
    {
        return $courses->filter(function ($course) use ($user) {
            if ($user->subdivision === 'GER') {

                try {
                    $isOnRoster = $this->validationService->isUserOnRoster($user->vatsim_id);
                    
                    if ($course->type === 'RST' && $isOnRoster) return false;
                    if ($course->type !== 'RST' && !$isOnRoster) return false;
                } catch (\Exception $e) {
                    // If roster check fails, allow all courses except RST
                    if ($course->type === 'RST') return false;
                }
            } else {
                if ($course->type === 'RTG') return false;
            }

            // Check for active RTG courses
            if ($course->type === 'RTG' && $user->activeRatingCourses()->exists()) {
                return false;
            }

            // Check S3 rating change restrictions
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