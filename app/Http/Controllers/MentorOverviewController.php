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

        // Get courses based on permissions
        if ($user->is_superuser || $user->is_admin) {
            $courses = \App\Models\Course::with([
                'mentorGroup',
                'activeTrainees.endorsementActivities',
                'activeTrainees.familiarisations.sector'
            ])->get();
        } else {
            $courses = $user->mentorCourses()->with([
                'mentorGroup',
                'activeTrainees.endorsementActivities',
                'activeTrainees.familiarisations.sector'
            ])->get();
        }

        // Format courses for frontend
        $formattedCourses = $courses->map(function ($course) use ($user) {
            $trainees = $course->activeTrainees->map(function ($trainee) use ($course, $user) {
                return $this->formatTrainee($trainee, $course, $user);
            });

            return [
                'id' => $course->id,
                'name' => $course->name,
                'position' => $course->position,
                'type' => $course->type,
                'activeTrainees' => $course->activeTrainees->count(),
                'trainees' => $trainees,
            ];
        });

        // Calculate statistics
        $totalActiveTrainees = $courses->sum(function ($course) {
            return $course->activeTrainees->count();
        });

        // Get claimed trainees (courses where user is a mentor)
        $claimedTrainees = $user->mentorCourses()
            ->withCount('activeTrainees')
            ->get()
            ->sum('active_trainees_count');

        // Get training sessions from last 30 days (placeholder - implement when you add training sessions table)
        $trainingSessions = 0;

        // Get waiting list count
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
        // Get solo endorsements for this trainee and position
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

        // Find solo for this course's position
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
            $daysRemaining = max(0, $expiryDate->diffInDays(now(), false) * -1);
            $daysUsed = $solo['max_days'] - $daysRemaining;

            $soloStatus = [
                'remaining' => (int) $daysRemaining,
                'used' => (int) $daysUsed,
                'expiry' => $expiryDate->format('Y-m-d'),
            ];
        }

        // Get trainee's progress (placeholder - implement when you add training sessions table)
        // For now, return empty array
        $progress = [];

        // Check if trainee is claimed by current mentor
        $isClaimedByCurrentUser = $course->mentors->contains('id', $currentMentor->id);

        // Get claimed mentor info from pivot table
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

        // Get remarks from course_trainees pivot table with author information
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

        // Get last session date (placeholder - implement when you add training sessions table)
        $lastSession = null;

        // Get next step (placeholder - implement when you add training sessions table)
        $nextStep = '';

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

        // Check if user can mentor this course
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

        // Check if user can mentor this course
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

        // Check if user can mentor this course
        if (!$user->is_superuser && !$user->is_admin && !$user->mentorCourses()->where('courses.id', $course->id)->exists()) {
            return back()->withErrors(['error' => 'You cannot modify this course']);
        }

        try {
            // Remove from active trainees
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

        // Check if user can mentor this course
        if (!$user->is_superuser && !$user->is_admin && !$user->mentorCourses()->where('courses.id', $course->id)->exists()) {
            return back()->withErrors(['error' => 'You cannot claim trainees for this course']);
        }

        // Check if trainee is actually in this course
        if (!$course->activeTrainees()->where('user_id', $trainee->id)->exists()) {
            return back()->withErrors(['error' => 'Trainee is not in this course']);
        }

        try {
            // Get current claimed mentor
            $currentMentor = DB::table('course_trainees')
                ->where('course_id', $course->id)
                ->where('user_id', $trainee->id)
                ->value('claimed_by_mentor_id');

            // Update the claimed mentor
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

        // Check if user can mentor this course
        if (!$user->is_superuser && !$user->is_admin && !$user->mentorCourses()->where('courses.id', $course->id)->exists()) {
            return back()->withErrors(['error' => 'You cannot assign trainees for this course']);
        }

        // Check if the new mentor can actually mentor this course
        if (!$newMentor->is_superuser && !$newMentor->is_admin && !$newMentor->mentorCourses()->where('course.id', $course->id)->exists()) {
            return back()->withErrors(['error' => 'Selected mentor cannot mentor this course']);
        }

        // Check if trainee is actually in this course
        if (!$course->activeTrainees()->where('user_id', $trainee->id)->exists()) {
            return back()->withErrors(['error' => 'Trainee is not in this course']);
        }

        try {
            // Get current claimed mentor
            $currentMentor = DB::table('course_trainees')
                ->where('course_id', $course->id)
                ->where('user_id', $trainee->id)
                ->value('claimed_by_mentor_id');

            // Update the claimed mentor
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

        // Check if user can mentor this course
        if (!$user->is_superuser && !$user->is_admin && !$user->mentorCourses()->where('courses.id', $course->id)->exists()) {
            return back()->withErrors(['error' => 'You cannot unclaim trainees for this course']);
        }

        // Check if trainee is actually in this course
        if (!$course->activeTrainees()->where('user_id', $trainee->id)->exists()) {
            return back()->withErrors(['error' => 'Trainee is not in this course']);
        }

        try {
            // Update the claimed mentor to null
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
     * Get user initials
     */
    protected function getInitials(string $firstName, string $lastName): string
    {
        $firstInitial = mb_substr($firstName, 0, 1);
        $lastInitial = mb_substr($lastName, 0, 1);
        return strtoupper($firstInitial . $lastInitial);
    }
}