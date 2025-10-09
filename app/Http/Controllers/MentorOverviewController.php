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

        // Get primary mentor (first mentor in the list, or implement your own logic)
        $primaryMentor = $course->mentors->first();
        $claimedBy = null;
        if ($isClaimedByCurrentUser) {
            $claimedBy = 'You';
        } elseif ($primaryMentor) {
            $claimedBy = $primaryMentor->name;
        }

        // Get remarks from course_trainees pivot table
        $pivot = DB::table('course_trainees')
            ->where('course_id', $course->id)
            ->where('user_id', $trainee->id)
            ->first();

        $remarks = $pivot->remarks ?? '';

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
            'soloStatus' => $soloStatus,
            'remark' => $remarks,
        ];
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