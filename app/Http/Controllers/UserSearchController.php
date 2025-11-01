<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class UserSearchController extends Controller
{
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2',
        ]);

        $query = trim($request->input('query'));

        try {
            if (is_numeric($query)) {
                $users = User::where('vatsim_id', $query)
                    ->whereNotNull('vatsim_id')
                    ->limit(10)
                    ->get(['id', 'vatsim_id', 'first_name', 'last_name', 'email']);
            } else {
                $users = User::where(function ($q) use ($query) {
                    $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($query) . '%'])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', ['%' . strtolower($query) . '%'])
                        ->orWhereRaw('LOWER(first_name || \' \' || last_name) LIKE ?', ['%' . strtolower($query) . '%'])
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

    public function show(int $vatsimId)
    {
        $user = User::where('vatsim_id', $vatsimId)
            ->whereNotNull('vatsim_id')
            ->firstOrFail();

        $currentUser = auth()->user();

        if (!$currentUser->isMentor() && !$currentUser->isSuperuser() && !$currentUser->is_admin) {
            abort(403, 'Only mentors can view user profiles.');
        }

        if ($currentUser->isSuperuser() || $currentUser->is_admin) {
            $mentorCourseIds = \App\Models\Course::pluck('id')->toArray();
        } else {
            $mentorCourseIds = $currentUser->mentorCourses()->pluck('courses.id')->toArray();
        }

        \Log::info('User profile view', [
            'viewing_user_id' => $user->id,
            'viewing_user_vatsim' => $user->vatsim_id,
            'current_user_id' => $currentUser->id,
            'is_superuser' => $currentUser->isSuperuser(),
            'is_admin' => $currentUser->is_admin,
            'mentor_course_ids' => $mentorCourseIds,
        ]);

        $activeCourses = $user->activeCourses()
            ->with(['mentorGroup'])
            ->get()
            ->map(function ($course) use ($mentorCourseIds, $user, $currentUser) {
                $isMentor = in_array($course->id, $mentorCourseIds);

                $courseData = [
                    'id' => $course->id,
                    'name' => $course->name,
                    'type' => $course->type,
                    'position' => $course->position,
                    'is_mentor' => $isMentor,
                    'logs' => [],
                ];

                if ($isMentor) {
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
                                    'mentor_name' => $log->mentor ? $log->mentor->name : 'Unknown',
                                    'session_duration' => $log->session_duration ?? null,
                                    'next_step' => $log->next_step ?? null,
                                    'average_rating' => $log->average_rating ?? null,
                                ];
                            });

                        $courseData['logs'] = $logs->toArray();

                        \Log::info('Loaded training logs for course', [
                            'course_id' => $course->id,
                            'user_id' => $user->id,
                            'viewer_id' => $currentUser->id,
                            'is_mentor' => $isMentor,
                            'log_count' => $logs->count()
                        ]);
                    } catch (\Exception $e) {
                        \Log::error('Error fetching training logs', [
                            'course_id' => $course->id,
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        $courseData['logs'] = [];
                    }
                }

                return $courseData;
            });

        $completedCourses = collect();

        try {
            $completedData = DB::table('course_trainees')
                ->join('courses', 'course_trainees.course_id', '=', 'courses.id')
                ->where('course_trainees.user_id', $user->id)
                ->whereNotNull('course_trainees.completed_at')
                ->select(
                    'courses.*',
                    'course_trainees.completed_at'
                )
                ->get();

            foreach ($completedData as $courseData) {
                if (in_array($courseData->id, $mentorCourseIds)) {
                    $logs = \App\Models\TrainingLog::where('trainee_id', $user->id)
                        ->where('course_id', $courseData->id)
                        ->with(['trainee', 'mentor'])
                        ->orderBy('session_date', 'desc')
                        ->limit(10)
                        ->get()
                        ->map(function ($log) {
                            return [
                                'id' => $log->id,
                                'session_date' => $log->session_date->format('Y-m-d'),
                                'position' => $log->position ?? 'N/A',
                                'type' => $log->type ?? 'O',
                                'type_display' => $log->type_display ?? 'Online',
                                'result' => $log->result ?? false,
                                'mentor_name' => $log->mentor ? $log->mentor->name : 'Unknown',
                                'session_duration' => $log->session_duration ?? null,
                                'next_step' => $log->next_step ?? null,
                                'average_rating' => $log->average_rating ?? null,
                            ];
                        });

                    $totalSessions = \App\Models\TrainingLog::where('trainee_id', $user->id)
                        ->where('course_id', $courseData->id)
                        ->count();

                    $completedCourses->push([
                        'id' => $courseData->id,
                        'name' => $courseData->name,
                        'type' => $courseData->type,
                        'position' => $courseData->position,
                        'completed_at' => \Carbon\Carbon::parse($courseData->completed_at)->format('Y-m-d'),
                        'total_sessions' => $totalSessions,
                        'logs' => $logs->toArray(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error fetching completed courses', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            $completedCourses = collect();
        }
        
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

        // Get Moodle courses for active courses only
        $moodleCourses = [];
        $moodleService = app(\App\Services\MoodleService::class);

        foreach ($activeCourses as $course) {
            $fullCourse = \App\Models\Course::find($course['id']);
            if ($fullCourse && $fullCourse->moodle_course_ids) {
                $moodleIds = is_array($fullCourse->moodle_course_ids)
                    ? $fullCourse->moodle_course_ids
                    : json_decode($fullCourse->moodle_course_ids, true);

                if (is_array($moodleIds)) {
                    foreach ($moodleIds as $moodleId) {
                        try {
                            $courseName = $moodleService->getCourseName($moodleId);
                            $isPassed = $moodleService->getCourseCompletion($user->vatsim_id, $moodleId);

                            $moodleCourses[] = [
                                'id' => $moodleId,
                                'name' => $courseName ?? "Moodle Course {$moodleId}",
                                'passed' => $isPassed,
                                'link' => "https://moodle.vatsim-germany.org/course/view.php?id={$moodleId}",
                            ];
                        } catch (\Exception $e) {
                            \Log::warning('Failed to fetch Moodle course info', [
                                'moodle_id' => $moodleId,
                                'error' => $e->getMessage()
                            ]);
                        }
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
                'is_mentor' => $user->isMentor(),
                'is_superuser' => $user->is_superuser,
            ],
            'active_courses' => $activeCourses->values()->toArray(),
            'completed_courses' => $completedCourses->toArray(),
            'endorsements' => $endorsements,
            'moodle_courses' => $moodleCourses,
            'familiarisations' => $familiarisations,
        ];

        return Inertia::render('users/profile', [
            'userData' => $userData,
        ]);
    }
}