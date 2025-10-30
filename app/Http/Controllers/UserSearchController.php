<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class UserSearchController extends Controller
{
    /**
     * Search for users by name or VATSIM ID
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2',
        ]);

        $query = trim($request->input('query'));

        try {
            // Check if query is numeric (VATSIM ID search)
            if (is_numeric($query)) {
                $users = User::where('vatsim_id', $query)
                    ->whereNotNull('vatsim_id')
                    ->limit(10)
                    ->get(['id', 'vatsim_id', 'first_name', 'last_name', 'email']);
            } else {
                // Smart search by name (case-insensitive, partial matching)
                $users = User::where(function ($q) use ($query) {
                    // Search in first name
                    $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($query) . '%'])
                        // Search in last name
                        ->orWhereRaw('LOWER(last_name) LIKE ?', ['%' . strtolower($query) . '%'])
                        // Search in full name (first + last) - PostgreSQL syntax
                        ->orWhereRaw('LOWER(first_name || \' \' || last_name) LIKE ?', ['%' . strtolower($query) . '%'])
                        // Search in reversed full name (last + first) - PostgreSQL syntax
                        ->orWhereRaw('LOWER(last_name || \' \' || first_name) LIKE ?', ['%' . strtolower($query) . '%']);
                })
                    ->whereNotNull('vatsim_id')
                    ->orderByRaw('
                    CASE
                        WHEN LOWER(first_name) = ? THEN 1
                        WHEN LOWER(last_name) = ? THEN 2
                        WHEN LOWER(first_name || \' \' || last_name) = ? THEN 3
                        WHEN LOWER(first_name) LIKE ? THEN 4
                        WHEN LOWER(last_name) LIKE ? THEN 5
                        ELSE 6
                    END
                ', [
                        strtolower($query),
                        strtolower($query),
                        strtolower($query),
                        strtolower($query) . '%',
                        strtolower($query) . '%'
                    ])
                    ->limit(10)
                    ->get(['id', 'vatsim_id', 'first_name', 'last_name', 'email']);
            }

            $results = $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'vatsim_id' => $user->vatsim_id,
                    'name' => $user->name,
                    'email' => $user->email,
                ];
            });

            return response()->json([
                'success' => true,
                'users' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('User search error', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Search failed'
            ], 500);
        }
    }

    /**
     * Show user profile page
     */
    public function show(int $vatsimId)
    {
        $user = User::where('vatsim_id', $vatsimId)
            ->whereNotNull('vatsim_id')
            ->firstOrFail();

        $currentUser = auth()->user();

        // Get courses the current user is a mentor of (to determine visibility)
        if ($currentUser->isSuperuser()) {
            $mentorCourseIds = \App\Models\Course::pluck('id')->toArray();
        } else {
            $mentorCourseIds = $currentUser->mentorCourses()->pluck('id')->toArray();
        }

        // Debug logging
        \Log::info('User profile view', [
            'viewing_user_id' => $user->id,
            'viewing_user_vatsim' => $user->vatsim_id,
            'current_user_id' => $currentUser->id,
            'is_superuser' => $currentUser->isSuperuser(),
            'mentor_course_ids' => $mentorCourseIds,
        ]);

        // Get active courses with training logs for courses the current user mentors
        $activeCourses = $user->activeCourses()
            ->with(['mentorGroup'])
            ->get()
            ->map(function ($course) use ($mentorCourseIds, $user) {
                $courseData = [
                    'id' => $course->id,
                    'name' => $course->name,
                    'type' => $course->type,
                    'position' => $course->position,
                    'is_mentor' => in_array($course->id, $mentorCourseIds),
                    'logs' => [],
                ];

                // Only include logs if current user is a mentor of this course
                if (in_array($course->id, $mentorCourseIds)) {
                    try {
                        $logs = \App\Models\TrainingLog::where('course_id', $course->id)
                            ->where('trainee_id', $user->id)
                            ->with(['trainee', 'mentor'])
                            ->orderBy('session_date', 'desc')
                            ->limit(5)
                            ->get()
                            ->map(function ($log) {
                                return [
                                    'id' => $log->id,
                                    'session_date' => $log->session_date->format('Y-m-d'),
                                    'position' => $log->position ?? 'N/A',
                                    'type' => $log->type ?? 'O',
                                    'type_display' => $log->type_display ?? 'Online',
                                    'result' => $log->result ?? false,
                                    'mentor_name' => $log->mentor ? ($log->mentor->first_name . ' ' . $log->mentor->last_name) : 'Unknown',
                                    'session_duration' => $log->session_duration ?? null,
                                ];
                            });

                        $courseData['logs'] = $logs;
                    } catch (\Exception $e) {
                        \Log::error('Error fetching training logs', [
                            'course_id' => $course->id,
                            'user_id' => $user->id,
                            'error' => $e->getMessage()
                        ]);
                        $courseData['logs'] = [];
                    }
                }

                return $courseData;
            });

        // Get completed courses - courses that were once active but aren't anymore
        // We'll need to track this separately, for now return empty array
        // In a full implementation, you'd have a course_history or similar table
        $completedCourses = [];
        
        // Get endorsements
        $endorsements = $user->endorsementActivities()
            ->get()
            ->map(function($activity) {
                return [
                    'position' => $activity->position,
                    'activity_hours' => $activity->activity_hours,
                    'status' => $activity->status,
                    'last_activity_date' => $activity->last_activity_date?->format('Y-m-d'),
                ];
            });

        // Get familiarisations grouped by FIR
        $familiarisations = $user->familiarisations()
            ->with('sector')
            ->get()
            ->groupBy('sector.fir')
            ->map(function($fams) {
                return $fams->map(function($fam) {
                    return [
                        'id' => $fam->id,
                        'sector_name' => $fam->sector->name,
                        'fir' => $fam->sector->fir,
                    ];
                })->values();
            });

        // Get Moodle courses (would need integration with Moodle API)
        // For now, we can get the course IDs from active courses
        $moodleCourses = [];
        foreach ($activeCourses as $course) {
            $fullCourse = \App\Models\Course::find($course['id']);
            if ($fullCourse && $fullCourse->moodle_course_ids) {
                // Ensure moodle_course_ids is an array
                $moodleIds = is_array($fullCourse->moodle_course_ids)
                    ? $fullCourse->moodle_course_ids
                    : json_decode($fullCourse->moodle_course_ids, true);

                if (is_array($moodleIds)) {
                    foreach ($moodleIds as $moodleId) {
                        $moodleCourses[] = [
                            'id' => $moodleId,
                            'name' => "Moodle Course {$moodleId}", // Would fetch real name from Moodle
                            'passed' => false, // Would check actual completion status
                            'link' => "https://moodle.vatsim-germany.org/course/view.php?id={$moodleId}",
                        ];
                    }
                }
            }
        }

        $userData = [
            'user' => [
                'vatsim_id' => $user->vatsim_id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'rating' => $user->rating,
                'subdivision' => $user->subdivision,
                'last_rating_change' => $user->last_rating_change?->format('Y-m-d'),
                'is_staff' => $user->is_staff,
                'is_superuser' => $user->is_superuser,
            ],
            'active_courses' => $activeCourses,
            'completed_courses' => $completedCourses,
            'endorsements' => $endorsements,
            'moodle_courses' => $moodleCourses,
            'familiarisations' => $familiarisations,
        ];

        return Inertia::render('users/profile', [
            'userData' => $userData,
        ]);
    }
}