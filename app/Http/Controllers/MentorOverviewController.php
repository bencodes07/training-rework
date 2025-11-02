<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MentorOverviewController extends Controller
{

    /**
     * Show mentor overview with courses and trainees
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if ($user->is_superuser || $user->is_admin) {
            $courses = \App\Models\Course::with([
                'mentorGroup',
                'activeTrainees' => function ($query) use ($user) {
                    $query->orderByRaw("
                        CASE 
                            WHEN course_trainees.custom_order_mentor_id = ? AND course_trainees.custom_order IS NOT NULL 
                            THEN course_trainees.custom_order 
                            ELSE 999999 
                        END ASC,
                        users.first_name ASC,
                        users.last_name ASC
                    ", [$user->id]);
                },
                'activeTrainees.endorsementActivities',
                'activeTrainees.familiarisations.sector'
            ])->get();
        } else {
            $courses = $user->mentorCourses()->with([
                'mentorGroup',
                'activeTrainees' => function ($query) use ($user) {
                    $query->orderByRaw("
                        CASE 
                            WHEN course_trainees.custom_order_mentor_id = ? AND course_trainees.custom_order IS NOT NULL 
                            THEN course_trainees.custom_order 
                            ELSE 999999 
                        END ASC,
                        users.first_name ASC,
                        users.last_name ASC
                    ", [$user->id]);
                },
                'activeTrainees.endorsementActivities',
                'activeTrainees.familiarisations.sector'
            ])->get();
        }

        $formattedCourses = $courses->map(function ($course) use ($user) {
            $trainees = $course->activeTrainees->map(function ($trainee) use ($course, $user) {
                return $this->formatTrainee($trainee, $course, $user);
            });

            return [
                'id' => $course->id,
                'name' => $course->name,
                'position' => $course->position,
                'type' => $course->type,
                'soloStation' => $course->solo_station,
                'activeTrainees' => $course->activeTrainees->count(),
                'trainees' => $trainees,
            ];
        });

        $totalActiveTrainees = $courses->sum(function ($course) {
            return $course->activeTrainees->count();
        });

        $claimedTrainees = $user->mentorCourses()
            ->withCount('activeTrainees')
            ->get()
            ->sum('active_trainees_count');

        $trainingSessions = 0;

        $waitingListCount = \App\Models\WaitingListEntry::whereHas('course', function ($q) use ($user) {
            if (!$user->is_superuser && !$user->is_admin) {
                $q->whereHas('mentors', function ($mq) use ($user) {
                    $mq->where('user_id', $user->id);
                });
            }
        })->count();

        return Inertia::render('training/mentor-overview', [
            'courses' => $formattedCourses,
            'statistics' => [
                'activeTrainees' => $totalActiveTrainees,
                'claimedTrainees' => $claimedTrainees,
                'trainingSessions' => $trainingSessions,
                'waitingList' => $waitingListCount,
            ],
        ]);
    }

    /**
     * Format trainee data for frontend
     */
    protected function formatTrainee($trainee, $course, $currentMentor): array
    {
        $soloEndorsements = collect();
        try {
            $vatEudService = app(\App\Services\VatEudService::class);
            $allSolos = $vatEudService->getSoloEndorsements();
            $soloEndorsements = collect($allSolos)->where('user_cid', $trainee->vatsim_id);
        } catch (\Exception $e) {
            \Log::warning('Failed to fetch solo endorsements', [
                'vatsim_id' => $trainee->vatsim_id,
                'error' => $e->getMessage()
            ]);
        }

        $solo = $soloEndorsements->first(function ($s) use ($course) {
            $soloPos = explode('_', $s['position']);
            $courseAirport = $course->airport_icao;
            $coursePos = $course->position;

            return $soloPos[0] === $courseAirport &&
                (end($soloPos) === $coursePos ||
                    ($coursePos === 'GND' && end($soloPos) === 'GNDDEL'));
        });

        $soloStatus = null;
        if ($solo) {
            $expiryDate = \Carbon\Carbon::parse($solo['expiry']);
            $now = \Carbon\Carbon::now();

            $daysRemaining = max(0, ceil($now->diffInHours($expiryDate, false) / 24));

            $daysUsed = (int) ($solo['position_days'] ?? 0);

            $extensionDaysLeft = 90 - $daysUsed;

            $soloStatus = [
                'remaining' => (int) $daysRemaining,
                'used' => $daysUsed,
                'extensionDaysLeft' => $extensionDaysLeft,
                'expiry' => $expiryDate->format('Y-m-d'),
            ];
        }

        $endorsementStatus = null;
        if (
            (in_array($course->type, ['GST', 'EDMT']) || ($course->type === 'RTG' && $course->position === 'GND'))
            && !empty($course->solo_station)
        ) {
            try {
                $vatEudService = app(\App\Services\VatEudService::class);
                $tier1Endorsements = $vatEudService->getTier1Endorsements();
    
                $endorsement = collect($tier1Endorsements)->first(function ($e) use ($trainee, $course) {
                    return $e['user_cid'] == $trainee->vatsim_id &&
                        $e['position'] === $course->solo_station;
                });
    
                if ($endorsement) {
                    $endorsementStatus = $course->solo_station;
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to fetch Tier 1 endorsements', [
                    'vatsim_id' => $trainee->vatsim_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $moodleStatus = null;
        if ($course->type === 'EDMT' && !empty($course->moodle_course_ids)) {
            try {
                $moodleService = app(\App\Services\MoodleService::class);

                if (!$moodleService->userExists($trainee->vatsim_id)) {
                    $moodleStatus = 'not-started';
                } else {
                    $allCompleted = $moodleService->checkAllCoursesCompleted(
                        $trainee->vatsim_id,
                        $course->moodle_course_ids
                    );
                    $moodleStatus = $allCompleted ? 'completed' : 'in-progress';
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to check Moodle status for trainee', [
                    'trainee_id' => $trainee->id,
                    'vatsim_id' => $trainee->vatsim_id,
                    'error' => $e->getMessage()
                ]);
                $moodleStatus = 'unknown';
            }
        }

        $trainingLogs = \App\Models\TrainingLog::where('trainee_id', $trainee->id)
            ->where('course_id', $course->id)
            ->orderBy('session_date', 'desc')
            ->take(10)
            ->get();

        $progress = $trainingLogs->map(function ($log) {
            return $log->result;
        })->reverse()->values()->toArray();

        $lastSession = $trainingLogs->isNotEmpty()
            ? $trainingLogs->first()->session_date->toIso8601String()
            : null;

        $nextStep = $trainingLogs->isNotEmpty() && $trainingLogs->first()->next_step
            ? $trainingLogs->first()->next_step
            : '';

        $isClaimedByCurrentUser = $course->mentors->contains('id', $currentMentor->id);

        $claimedMentorId = DB::table('course_trainees')
            ->where('course_id', $course->id)
            ->where('user_id', $trainee->id)
            ->value('claimed_by_mentor_id');

        $claimedBy = null;
        $claimedByMentorId = null;

        if ($claimedMentorId) {
            $claimedMentor = \App\Models\User::find($claimedMentorId);
            if ($claimedMentor) {
                $claimedByMentorId = $claimedMentor->id;
                if ($claimedMentor->id === $currentMentor->id) {
                    $claimedBy = 'You';
                } else {
                    $claimedBy = $claimedMentor->name;
                }
            }
        }

        $pivot = DB::table('course_trainees')
            ->leftJoin('users as remark_author', 'course_trainees.remark_author_id', '=', 'remark_author.id')
            ->where('course_trainees.course_id', $course->id)
            ->where('course_trainees.user_id', $trainee->id)
            ->select(
                'course_trainees.remarks',
                'course_trainees.remark_updated_at',
                'remark_author.first_name as author_first_name',
                'remark_author.last_name as author_last_name'
            )
            ->first();

        $remarkData = null;
        if ($pivot && !empty($pivot->remarks)) {
            $remarkData = [
                'text' => $pivot->remarks,
                'updated_at' => $pivot->remark_updated_at
                    ? \Carbon\Carbon::parse($pivot->remark_updated_at)->toIso8601String()
                    : null,
                'author_initials' => $pivot->author_first_name && $pivot->author_last_name
                    ? strtoupper(mb_substr($pivot->author_first_name, 0, 1) . mb_substr($pivot->author_last_name, 0, 1))
                    : null,
                'author_name' => $pivot->author_first_name && $pivot->author_last_name
                    ? $pivot->author_first_name . ' ' . $pivot->author_last_name
                    : null,
            ];
        }

        return [
            'id' => $trainee->id,
            'name' => $trainee->name,
            'vatsimId' => $trainee->vatsim_id,
            'initials' => $this->getInitials($trainee->first_name, $trainee->last_name),
            'progress' => $progress,
            'lastSession' => $lastSession,
            'nextStep' => $nextStep,
            'claimedBy' => $claimedBy,
            'claimedByMentorId' => $claimedByMentorId,
            'soloStatus' => $soloStatus,
            'moodleStatus' => $moodleStatus,
            'endorsementStatus' => $endorsementStatus,
            'remark' => $remarkData,
        ];
    }

    /**
     * Get available mentors for a course
     */
    public function getCourseMentors(Request $request, $courseId)
    {
        $user = $request->user();

        if (!$user->isMentor() && !$user->is_superuser) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $course = \App\Models\Course::findOrFail($courseId);

        if (!$user->is_superuser && !$user->is_admin && !$user->mentorCourses()->where('courses.id', $course->id)->exists()) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $mentors = $course->mentors()->get()->map(function ($mentor) {
            return [
                'id' => $mentor->id,
                'name' => $mentor->name,
                'vatsim_id' => $mentor->vatsim_id,
            ];
        });

        return response()->json($mentors);
    }

    /**
     * Update remark for a trainee
     */
    public function updateRemark(Request $request)
    {
        $user = $request->user();

        if (!$user->isMentor() && !$user->is_superuser) {
            return back()->withErrors(['error' => 'Access denied']);
        }

        $request->validate([
            'trainee_id' => 'required|integer|exists:users,id',
            'course_id' => 'required|integer|exists:courses,id',
            'remark' => 'nullable|string|max:1000',
        ]);

        $course = \App\Models\Course::findOrFail($request->course_id);

        if (!$user->is_superuser && !$user->is_admin && !$user->mentorCourses()->where('courses.id', $course->id)->exists()) {
            return back()->withErrors(['error' => 'You cannot modify this course']);
        }

        try {
            DB::table('course_trainees')
                ->where('course_id', $request->course_id)
                ->where('user_id', $request->trainee_id)
                ->update([
                    'remarks' => $request->remark ?? '',
                    'remark_author_id' => $user->id,
                    'remark_updated_at' => now(),
                ]);

            \Log::info('Trainee remark updated', [
                'mentor_id' => $user->id,
                'trainee_id' => $request->trainee_id,
                'course_id' => $request->course_id
            ]);

            return back()->with('success', 'Remark updated successfully');
        } catch (\Exception $e) {
            \Log::error('Error updating trainee remark', [
                'mentor_id' => $user->id,
                'trainee_id' => $request->trainee_id,
                'course_id' => $request->course_id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'An error occurred while updating the remark.']);
        }
    }

    /**
     * Remove trainee from course
     */
    public function removeTrainee(Request $request)
    {
        $user = $request->user();

        if (!$user->isMentor() && !$user->is_superuser) {
            return back()->withErrors(['error' => 'Access denied']);
        }

        $request->validate([
            'trainee_id' => 'required|integer|exists:users,id',
            'course_id' => 'required|integer|exists:courses,id',
        ]);

        $course = \App\Models\Course::findOrFail($request->course_id);
        $trainee = \App\Models\User::findOrFail($request->trainee_id);

        if (!$user->is_superuser && !$user->is_admin && !$user->mentorCourses()->where('courses.id', $course->id)->exists()) {
            return back()->withErrors(['error' => 'You cannot modify this course']);
        }

        try {
            $course->activeTrainees()->detach($trainee->id);

            \Log::info('Trainee removed from course', [
                'mentor_id' => $user->id,
                'trainee_id' => $trainee->id,
                'trainee_name' => $trainee->name,
                'course_id' => $course->id,
                'course_name' => $course->name
            ]);

            return back()->with('success', "Successfully removed {$trainee->name} from {$course->name}");
        } catch (\Exception $e) {
            \Log::error('Error removing trainee from course', [
                'mentor_id' => $user->id,
                'trainee_id' => $trainee->id,
                'course_id' => $course->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'An error occurred while removing the trainee.']);
        }
    }

    /**
     * Claim a trainee (assign yourself as the responsible mentor)
     */
    public function claimTrainee(Request $request)
    {
        $user = $request->user();

        if (!$user->isMentor() && !$user->is_superuser) {
            return back()->withErrors(['error' => 'Access denied']);
        }

        $request->validate([
            'trainee_id' => 'required|integer|exists:users,id',
            'course_id' => 'required|integer|exists:courses,id',
        ]);

        $course = \App\Models\Course::findOrFail($request->course_id);
        $trainee = \App\Models\User::findOrFail($request->trainee_id);

        if (!$user->is_superuser && !$user->is_admin && !$user->mentorCourses()->where('courses.id', $course->id)->exists()) {
            return back()->withErrors(['error' => 'You cannot claim trainees for this course']);
        }

        if (!$course->activeTrainees()->where('user_id', $trainee->id)->exists()) {
            return back()->withErrors(['error' => 'Trainee is not in this course']);
        }

        try {
            $currentMentor = DB::table('course_trainees')
                ->where('course_id', $course->id)
                ->where('user_id', $trainee->id)
                ->value('claimed_by_mentor_id');

            DB::table('course_trainees')
                ->where('course_id', $course->id)
                ->where('user_id', $trainee->id)
                ->update([
                    'claimed_by_mentor_id' => $user->id,
                    'claimed_at' => now(),
                ]);

            \Log::info('Trainee claimed', [
                'mentor_id' => $user->id,
                'trainee_id' => $trainee->id,
                'trainee_name' => $trainee->name,
                'course_id' => $course->id,
                'course_name' => $course->name,
                'previous_mentor_id' => $currentMentor
            ]);

            return back()->with('success', "Successfully claimed {$trainee->name}");
        } catch (\Exception $e) {
            \Log::error('Error claiming trainee', [
                'mentor_id' => $user->id,
                'trainee_id' => $trainee->id,
                'course_id' => $course->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'An error occurred while claiming the trainee.']);
        }
    }

    /**
     * Assign a trainee to another mentor
     */
    public function assignTrainee(Request $request)
    {
        $user = $request->user();

        if (!$user->isMentor() && !$user->is_superuser) {
            return back()->withErrors(['error' => 'Access denied']);
        }

        $request->validate([
            'trainee_id' => 'required|integer|exists:users,id',
            'course_id' => 'required|integer|exists:courses,id',
            'mentor_id' => 'required|integer|exists:users,id',
        ]);

        $course = \App\Models\Course::findOrFail($request->course_id);
        $trainee = \App\Models\User::findOrFail($request->trainee_id);
        $newMentor = \App\Models\User::findOrFail($request->mentor_id);

        if (!$user->is_superuser && !$user->is_admin && !$user->mentorCourses()->where('courses.id', $course->id)->exists()) {
            return back()->withErrors(['error' => 'You cannot assign trainees for this course']);
        }

        if (!$newMentor->is_superuser && !$newMentor->is_admin && !$newMentor->mentorCourses()->where('courses.id', $course->id)->exists()) {
            return back()->withErrors(['error' => 'Selected mentor cannot mentor this course']);
        }

        if (!$course->activeTrainees()->where('user_id', $trainee->id)->exists()) {
            return back()->withErrors(['error' => 'Trainee is not in this course']);
        }

        try {
            $currentMentor = DB::table('course_trainees')
                ->where('course_id', $course->id)
                ->where('user_id', $trainee->id)
                ->value('claimed_by_mentor_id');

            DB::table('course_trainees')
                ->where('course_id', $course->id)
                ->where('user_id', $trainee->id)
                ->update([
                    'claimed_by_mentor_id' => $newMentor->id,
                    'claimed_at' => now(),
                ]);

            \Log::info('Trainee assigned to mentor', [
                'assigning_mentor_id' => $user->id,
                'new_mentor_id' => $newMentor->id,
                'new_mentor_name' => $newMentor->name,
                'trainee_id' => $trainee->id,
                'trainee_name' => $trainee->name,
                'course_id' => $course->id,
                'course_name' => $course->name,
                'previous_mentor_id' => $currentMentor
            ]);

            return back()->with('success', "Successfully assigned {$trainee->name} to {$newMentor->name}");
        } catch (\Exception $e) {
            \Log::error('Error assigning trainee', [
                'mentor_id' => $user->id,
                'trainee_id' => $trainee->id,
                'course_id' => $course->id,
                'new_mentor_id' => $request->mentor_id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'An error occurred while assigning the trainee.']);
        }
    }

    /**
     * Unclaim a trainee (remove yourself as the responsible mentor)
     */
    public function unclaimTrainee(Request $request)
    {
        $user = $request->user();

        if (!$user->isMentor() && !$user->is_superuser) {
            return back()->withErrors(['error' => 'Access denied']);
        }

        $request->validate([
            'trainee_id' => 'required|integer|exists:users,id',
            'course_id' => 'required|integer|exists:courses,id',
        ]);

        $course = \App\Models\Course::findOrFail($request->course_id);
        $trainee = \App\Models\User::findOrFail($request->trainee_id);

        if (!$user->is_superuser && !$user->is_admin && !$user->mentorCourses()->where('courses.id', $course->id)->exists()) {
            return back()->withErrors(['error' => 'You cannot unclaim trainees for this course']);
        }

        if (!$course->activeTrainees()->where('user_id', $trainee->id)->exists()) {
            return back()->withErrors(['error' => 'Trainee is not in this course']);
        }

        try {
            DB::table('course_trainees')
                ->where('course_id', $course->id)
                ->where('user_id', $trainee->id)
                ->update([
                    'claimed_by_mentor_id' => null,
                    'claimed_at' => null,
                ]);

            \Log::info('Trainee unclaimed', [
                'mentor_id' => $user->id,
                'trainee_id' => $trainee->id,
                'trainee_name' => $trainee->name,
                'course_id' => $course->id,
                'course_name' => $course->name
            ]);

            return back()->with('success', "Successfully unclaimed {$trainee->name}");
        } catch (\Exception $e) {
            \Log::error('Error unclaiming trainee', [
                'mentor_id' => $user->id,
                'trainee_id' => $trainee->id,
                'course_id' => $course->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'An error occurred while unclaiming the trainee.']);
        }
    }

    /**
     * Add a mentor to a course
     */
    public function addMentor(Request $request)
    {
        $user = $request->user();

        if (!$user->isMentor() && !$user->is_superuser) {
            return back()->withErrors(['error' => 'Access denied']);
        }

        $request->validate([
            'course_id' => 'required|integer|exists:courses,id',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $course = \App\Models\Course::findOrFail($request->course_id);
        $mentorToAdd = \App\Models\User::findOrFail($request->user_id);

        if (!$user->is_superuser && !$user->is_admin && !$user->mentorCourses()->where('courses.id', $course->id)->exists()) {
            return back()->withErrors(['error' => 'You cannot modify this course']);
        }

        if (!$mentorToAdd->isMentor() && !$mentorToAdd->is_superuser && !$mentorToAdd->is_admin) {
            return back()->withErrors(['error' => 'This user does not have mentor privileges']);
        }

        try {
            if ($course->mentors()->where('user_id', $mentorToAdd->id)->exists()) {
                return back()->withErrors(['error' => 'This user is already a mentor for this course']);
            }

            $course->mentors()->attach($mentorToAdd->id);

            \Log::info('Mentor added to course', [
                'admin_id' => $user->id,
                'new_mentor_id' => $mentorToAdd->id,
                'new_mentor_name' => $mentorToAdd->name,
                'course_id' => $course->id,
                'course_name' => $course->name
            ]);

            return back()->with('success', "Successfully added {$mentorToAdd->name} as a mentor");
        } catch (\Exception $e) {
            \Log::error('Error adding mentor to course', [
                'admin_id' => $user->id,
                'new_mentor_id' => $request->user_id,
                'course_id' => $course->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'An error occurred while adding the mentor.']);
        }
    }

    /**
     * Remove a mentor from a course
     */
    public function removeMentor(Request $request)
    {
        $user = $request->user();

        if (!$user->isMentor() && !$user->is_superuser) {
            return back()->withErrors(['error' => 'Access denied']);
        }

        $request->validate([
            'course_id' => 'required|integer|exists:courses,id',
            'mentor_id' => 'required|integer|exists:users,id',
        ]);

        $course = \App\Models\Course::findOrFail($request->course_id);
        $mentorToRemove = \App\Models\User::findOrFail($request->mentor_id);

        if (!$user->is_superuser && !$user->is_admin && !$user->mentorCourses()->where('courses.id', $course->id)->exists()) {
            return back()->withErrors(['error' => 'You cannot modify this course']);
        }

        try {
            if ($course->mentors()->count() <= 1) {
                return back()->withErrors(['error' => 'Cannot remove the last mentor from a course']);
            }

            if (!$course->mentors()->where('user_id', $mentorToRemove->id)->exists()) {
                return back()->withErrors(['error' => 'This user is not a mentor for this course']);
            }

            $course->mentors()->detach($mentorToRemove->id);

            DB::table('course_trainees')
                ->where('course_id', $course->id)
                ->where('claimed_by_mentor_id', $mentorToRemove->id)
                ->update([
                    'claimed_by_mentor_id' => null,
                    'claimed_at' => null,
                ]);

            \Log::info('Mentor removed from course', [
                'admin_id' => $user->id,
                'removed_mentor_id' => $mentorToRemove->id,
                'removed_mentor_name' => $mentorToRemove->name,
                'course_id' => $course->id,
                'course_name' => $course->name
            ]);

            return back()->with('success', "Successfully removed {$mentorToRemove->name} as a mentor");
        } catch (\Exception $e) {
            \Log::error('Error removing mentor from course', [
                'admin_id' => $user->id,
                'mentor_id' => $request->mentor_id,
                'course_id' => $course->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'An error occurred while removing the mentor.']);
        }
    }

    /**
     * Add a trainee to a course - WITH AUTOMATIC REACTIVATION
     */
    public function addTraineeToCourse(Request $request)
    {
        $user = $request->user();

        if (!$user->isMentor() && !$user->is_superuser) {
            return back()->withErrors(['error' => 'Access denied']);
        }

        $request->validate([
            'course_id' => 'required|integer|exists:courses,id',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $course = \App\Models\Course::findOrFail($request->course_id);
        $trainee = \App\Models\User::findOrFail($request->user_id);

        if (!$user->is_superuser && !$user->is_admin && !$user->mentorCourses()->where('courses.id', $course->id)->exists()) {
            return back()->withErrors(['error' => 'You cannot modify this course']);
        }

        try {
            if ($course->activeTrainees()->where('user_id', $trainee->id)->exists()) {
                return back()->withErrors(['error' => 'This trainee is already active in this course']);
            }

            if (!$trainee->isVatsimUser()) {
                return back()->withErrors(['error' => 'This user does not have a VATSIM account']);
            }

            \App\Models\WaitingListEntry::where('user_id', $trainee->id)
                ->where('course_id', $course->id)
                ->delete();

            $existingCompleted = DB::table('course_trainees')
                ->where('course_id', $course->id)
                ->where('user_id', $trainee->id)
                ->whereNotNull('completed_at')
                ->exists();

            if ($existingCompleted) {
                DB::table('course_trainees')
                    ->where('course_id', $course->id)
                    ->where('user_id', $trainee->id)
                    ->update([
                        'completed_at' => null,
                        'claimed_by_mentor_id' => $user->id,
                        'claimed_at' => now(),
                        'updated_at' => now(),
                    ]);

                \Log::info('Trainee reactivated in course', [
                    'mentor_id' => $user->id,
                    'trainee_id' => $trainee->id,
                    'trainee_name' => $trainee->name,
                    'course_id' => $course->id,
                    'course_name' => $course->name
                ]);

                return back()->with('success', "Successfully reactivated {$trainee->name} in the course");
            }

            $course->activeTrainees()->attach($trainee->id, [
                'claimed_by_mentor_id' => $user->id,
                'claimed_at' => now(),
            ]);

            if (!empty($course->moodle_course_ids)) {
                try {
                    $moodleService = app(\App\Services\MoodleService::class);
                    $moodleService->enrollUserInCourses(
                        $trainee->vatsim_id,
                        $course->moodle_course_ids
                    );

                    \Log::info('Trainee enrolled in Moodle courses', [
                        'trainee_id' => $trainee->id,
                        'vatsim_id' => $trainee->vatsim_id,
                        'course_id' => $course->id,
                        'moodle_courses' => $course->moodle_course_ids
                    ]);
                } catch (\Exception $e) {
                    \Log::warning('Failed to enroll trainee in Moodle courses', [
                        'trainee_id' => $trainee->id,
                        'course_id' => $course->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            \Log::info('Trainee added to course', [
                'mentor_id' => $user->id,
                'trainee_id' => $trainee->id,
                'trainee_name' => $trainee->name,
                'course_id' => $course->id,
                'course_name' => $course->name
            ]);

            return back()->with('success', "Successfully added {$trainee->name} to the course");
        } catch (\Exception $e) {
            \Log::error('Error adding trainee to course', [
                'mentor_id' => $user->id,
                'trainee_id' => $trainee->id,
                'course_id' => $course->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'An error occurred while adding the trainee.']);
        }
    }

    /**
     * Grant endorsement to a trainee
     */
    public function grantEndorsement(Request $request)
    {
        $user = $request->user();

        if (!$user->isMentor()) {
            return back()->withErrors(['error' => 'Access denied']);
        }

        $request->validate([
            'trainee_id' => 'required|integer|exists:users,id',
            'course_id' => 'required|integer|exists:courses,id',
        ]);

        $course = \App\Models\Course::findOrFail($request->course_id);
        $trainee = \App\Models\User::findOrFail($request->trainee_id);

        if (!in_array($course->type, ['GST', 'EDMT']) && !($course->type === 'RTG' && $course->position === 'GND')) {
            return back()->withErrors(['error' => 'This course does not support endorsements']);
        }

        if (!$user->is_superuser && !$user->is_admin && !$user->mentorCourses()->where('courses.id', $course->id)->exists()) {
            return back()->withErrors(['error' => 'You are not a mentor for this course']);
        }

        try {
            if (!$course->activeTrainees()->where('user_id', $trainee->id)->exists()) {
                return back()->withErrors(['error' => 'Trainee is not enrolled in this course']);
            }

            if (empty($course->solo_station)) {
                return back()->withErrors(['error' => 'Course does not have an endorsement position configured']);
            }

            $moodleCompleted = true; // TODO: Add moodle completion

            if (!$moodleCompleted) {
                return back()->withErrors(['error' => 'Trainee has not completed all required Moodle courses']);
            }

            $vatEudService = app(\App\Services\VatEudService::class);

            $result = $vatEudService->createTier1Endorsement(
                $trainee->vatsim_id,
                $course->solo_station,
                $user->vatsim_id
            );

            if ($result['success']) {
                $vatEudService->refreshEndorsementCache();

                \Log::info('Endorsement granted successfully', [
                    'mentor_id' => $user->id,
                    'mentor_vatsim_id' => $user->vatsim_id,
                    'trainee_id' => $trainee->id,
                    'trainee_vatsim_id' => $trainee->vatsim_id,
                    'course_id' => $course->id,
                    'position' => $course->solo_station,
                ]);

                return back()->with('success', "Successfully granted {$course->solo_station} endorsement to {$trainee->name}");
            } else {
                return back()->withErrors(['error' => $result['message'] ?? 'Failed to grant endorsement']);
            }

        } catch (\Exception $e) {
            \Log::error('Error granting endorsement', [
                'mentor_id' => $user->id,
                'trainee_id' => $trainee->id,
                'course_id' => $course->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'An error occurred while granting the endorsement. Please try again.']);
        }
    }

    /**
     * Finish a trainee's course (mark as completed instead of removing)
     */
    public function finishCourse(Request $request)
    {
        $user = $request->user();

        if (!$user->isMentor() && !$user->is_superuser) {
            return back()->withErrors(['error' => 'Access denied']);
        }

        $request->validate([
            'trainee_id' => 'required|integer|exists:users,id',
            'course_id' => 'required|integer|exists:courses,id',
        ]);

        $course = \App\Models\Course::findOrFail($request->course_id);
        $trainee = \App\Models\User::findOrFail($request->trainee_id);

        if (!$user->is_superuser && !$user->is_admin && !$user->mentorCourses()->where('courses.id', $course->id)->exists()) {
            return back()->withErrors(['error' => 'You cannot modify this course']);
        }

        if (!$course->activeTrainees()->where('user_id', $trainee->id)->exists()) {
            return back()->withErrors(['error' => 'Trainee is not in this course']);
        }

        try {
            DB::transaction(function () use ($course, $trainee, $user) {
                DB::table('course_trainees')
                    ->where('course_id', $course->id)
                    ->where('user_id', $trainee->id)
                    ->update([
                        'completed_at' => now(),
                    ]);

                $endorsementGroups = DB::table('course_endorsement_groups')
                    ->where('course_id', $course->id)
                    ->pluck('endorsement_group_name')
                    ->toArray();

                if (!empty($endorsementGroups)) {
                    $this->grantEndorsements($trainee, $endorsementGroups, $user);
                }

                if ($course->type === 'RTG' && $course->position === 'CTR') {
                    $this->addFIRFamiliarisations($trainee, $course, $user);
                } elseif ($course->type === 'FAM' && $course->familiarisation_sector_id) {
                    $this->addSingleFamiliarisation($trainee, $course, $user);
                }
            });

            \Log::info('Course finished for trainee', [
                'mentor_id' => $user->id,
                'trainee_id' => $trainee->id,
                'trainee_name' => $trainee->name,
                'course_id' => $course->id,
                'course_name' => $course->name
            ]);

            return back()->with('success', "Successfully finished {$course->name} for {$trainee->name}");
        } catch (\Exception $e) {
            \Log::error('Error finishing course', [
                'mentor_id' => $user->id,
                'trainee_id' => $trainee->id,
                'course_id' => $course->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'An error occurred while finishing the course. Please try again.']);
        }
    }

    /**
     * Get past trainees for a course
     */
    public function getPastTrainees(Request $request, $courseId)
    {
        $user = $request->user();

        if (!$user->isMentor() && !$user->is_superuser) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $course = \App\Models\Course::findOrFail($courseId);

        if (!$user->is_superuser && !$user->is_admin && !$user->mentorCourses()->where('courses.id', $course->id)->exists()) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            $pastTrainees = DB::table('course_trainees')
                ->join('users', 'course_trainees.user_id', '=', 'users.id')
                ->where('course_trainees.course_id', $courseId)
                ->whereNotNull('course_trainees.completed_at')
                ->select(
                    'users.id',
                    'users.vatsim_id',
                    'users.first_name',
                    'users.last_name',
                    'course_trainees.completed_at'
                )
                ->orderBy('course_trainees.completed_at', 'desc')
                ->get()
                ->map(function ($trainee) {
                    return [
                        'id' => $trainee->id,
                        'vatsim_id' => $trainee->vatsim_id,
                        'name' => $trainee->first_name . ' ' . $trainee->last_name,
                        'completed_at' => \Carbon\Carbon::parse($trainee->completed_at)->format('Y-m-d'),
                    ];
                });

            return response()->json([
                'success' => true,
                'trainees' => $pastTrainees
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching past trainees', [
                'course_id' => $courseId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Failed to fetch past trainees'], 500);
        }
    }

    /**
     * Reactivate a trainee (move from completed back to active)
     */
    public function reactivateTrainee(Request $request)
    {
        $user = $request->user();

        if (!$user->isMentor() && !$user->is_superuser) {
            return back()->withErrors(['error' => 'Access denied']);
        }

        $request->validate([
            'trainee_id' => 'required|integer|exists:users,id',
            'course_id' => 'required|integer|exists:courses,id',
        ]);

        $course = \App\Models\Course::findOrFail($request->course_id);
        $trainee = \App\Models\User::findOrFail($request->trainee_id);

        if (!$user->is_superuser && !$user->is_admin && !$user->mentorCourses()->where('courses.id', $course->id)->exists()) {
            return back()->withErrors(['error' => 'You cannot modify this course']);
        }

        try {
            $completed = DB::table('course_trainees')
                ->where('course_id', $course->id)
                ->where('user_id', $trainee->id)
                ->whereNotNull('completed_at')
                ->exists();

            if (!$completed) {
                return back()->withErrors(['error' => 'Trainee has not completed this course']);
            }

            DB::table('course_trainees')
                ->where('course_id', $course->id)
                ->where('user_id', $trainee->id)
                ->update([
                    'completed_at' => null,
                    'claimed_by_mentor_id' => $user->id,
                    'claimed_at' => now(),
                ]);

            \Log::info('Trainee reactivated', [
                'mentor_id' => $user->id,
                'trainee_id' => $trainee->id,
                'trainee_name' => $trainee->name,
                'course_id' => $course->id,
                'course_name' => $course->name
            ]);

            return back()->with('success', "Successfully reactivated {$trainee->name} for {$course->name}");
        } catch (\Exception $e) {
            \Log::error('Error reactivating trainee', [
                'mentor_id' => $user->id,
                'trainee_id' => $trainee->id,
                'course_id' => $course->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'An error occurred while reactivating the trainee.']);
        }
    }

    /**
     * Grant endorsements to a trainee
     */
    protected function grantEndorsements(\App\Models\User $trainee, array $endorsementGroups, \App\Models\User $mentor): void
    {
        try {
            $vatEudService = app(\App\Services\VatEudService::class);

            $existingEndorsements = collect($vatEudService->getTier1Endorsements())
                ->where('user_cid', $trainee->vatsim_id)
                ->pluck('position')
                ->toArray();

            foreach ($endorsementGroups as $position) {
                if (in_array($position, $existingEndorsements)) {
                    \Log::info('Trainee already has endorsement, skipping', [
                        'trainee_id' => $trainee->id,
                        'position' => $position
                    ]);
                    continue;
                }

                $result = $vatEudService->createTier1Endorsement(
                    $trainee->vatsim_id,
                    $position,
                    $mentor->vatsim_id
                );

                if ($result['success']) {
                    \Log::info('Tier 1 endorsement granted on course completion', [
                        'trainee_id' => $trainee->id,
                        'trainee_vatsim_id' => $trainee->vatsim_id,
                        'position' => $position,
                        'mentor_id' => $mentor->id,
                        'mentor_vatsim_id' => $mentor->vatsim_id
                    ]);
                } else {
                    \Log::warning('Failed to grant Tier 1 endorsement on course completion', [
                        'trainee_id' => $trainee->id,
                        'position' => $position,
                        'error' => $result['message'] ?? 'Unknown error'
                    ]);
                }
            }

            $vatEudService->refreshEndorsementCache();

        } catch (\Exception $e) {
            \Log::error('Error granting endorsements', [
                'trainee_id' => $trainee->id,
                'endorsement_groups' => $endorsementGroups,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Add all familiarisations for a FIR (for CTR courses)
     */
    protected function addFIRFamiliarisations(\App\Models\User $trainee, \App\Models\Course $course, \App\Models\User $mentor): void
    {
        try {
            if (!$course->mentor_group_id) {
                \Log::warning('No mentor group for CTR course, cannot determine FIR', [
                    'course_id' => $course->id
                ]);
                return;
            }

            $mentorGroup = \App\Models\Role::find($course->mentor_group_id);
            if (!$mentorGroup) {
                \Log::warning('Mentor group not found', [
                    'mentor_group_id' => $course->mentor_group_id
                ]);
                return;
            }

            $fir = substr($mentorGroup->name, 0, 4);

            $sectors = \App\Models\FamiliarisationSector::where('fir', $fir)->get();

            foreach ($sectors as $sector) {
                if (
                    !\App\Models\Familiarisation::where('user_id', $trainee->id)
                        ->where('familiarisation_sector_id', $sector->id)
                        ->exists()
                ) {

                    \App\Models\Familiarisation::create([
                        'user_id' => $trainee->id,
                        'familiarisation_sector_id' => $sector->id,
                    ]);

                    \Log::info('Familiarisation added on course completion', [
                        'trainee_id' => $trainee->id,
                        'sector_id' => $sector->id,
                        'sector_name' => $sector->name,
                        'fir' => $fir,
                        'mentor_id' => $mentor->id
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error adding FIR familiarisations', [
                'trainee_id' => $trainee->id,
                'course_id' => $course->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Add a single familiarisation (for FAM courses)
     */
    protected function addSingleFamiliarisation(\App\Models\User $trainee, \App\Models\Course $course, \App\Models\User $mentor): void
    {
        try {
            $familiarisation = \App\Models\Familiarisation::firstOrCreate([
                'user_id' => $trainee->id,
                'familiarisation_sector_id' => $course->familiarisation_sector_id,
            ]);

            if ($familiarisation->wasRecentlyCreated) {
                \Log::info('Familiarisation added on FAM course completion', [
                    'trainee_id' => $trainee->id,
                    'sector_id' => $course->familiarisation_sector_id,
                    'course_id' => $course->id,
                    'mentor_id' => $mentor->id
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error adding single familiarisation', [
                'trainee_id' => $trainee->id,
                'course_id' => $course->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get user initials
     */
    protected function getInitials(string $firstName, string $lastName): string
    {
        $firstInitial = mb_substr($firstName, 0, 1);
        $lastInitial = mb_substr($lastName, 0, 1);
        return strtoupper($firstInitial . $lastInitial);
    }
}