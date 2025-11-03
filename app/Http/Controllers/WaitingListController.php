<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use App\Models\WaitingListEntry;
use App\Services\WaitingListService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\ActivityLogger;

class WaitingListController extends Controller
{
    protected WaitingListService $waitingListService;

    public function __construct(WaitingListService $waitingListService)
    {
        $this->waitingListService = $waitingListService;
    }

    /**
     * Show mentor waiting list management view
     */
    public function mentorView(Request $request): Response
    {
        if (!Gate::allows('mentor')) {
            abort(403, 'Access denied. Mentor privileges required.');
        }

        $user = $request->user();

        // Superusers and admins see ALL courses
        // Regular mentors only see their assigned courses
        if ($user->is_superuser || $user->is_admin) {
            $courses = Course::with(['waitingListEntries.user', 'mentorGroup'])->get();
        } else {
            $courses = $user->mentorCourses()->with(['waitingListEntries.user', 'mentorGroup'])->get();
        }

        $courseData = [];
        $totalWaiting = 0;
        $statistics = [
            'rtg_waiting' => 0,
            'edmt_waiting' => 0,
            'fam_waiting' => 0,
            'gst_waiting' => 0,
            'rst_waiting' => 0,
        ];

        foreach ($courses as $course) {
            $waitingEntries = $course->waitingListEntries()
                ->with('user')
                ->orderBy('date_added')
                ->get();

            $formattedEntries = $waitingEntries->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'name' => $entry->user->name,
                    'vatsim_id' => $entry->user->vatsim_id,
                    'activity' => round($entry->activity, 1),
                    'waiting_time' => $entry->waiting_time,
                    'waiting_days' => $entry->date_added->diffInDays(now()),
                    'remarks' => $entry->remarks,
                    'date_added' => $entry->date_added->format('Y-m-d H:i:s'),
                ];
            });


            $courseData[] = [
                'id' => $course->id,
                'name' => $course->name,
                'type' => $course->type,
                'type_display' => $course->type_display,
                'position' => $course->position,
                'position_display' => $course->position_display,
                'waiting_count' => $waitingEntries->count(),
                'waiting_list' => $formattedEntries,
            ];

            $totalWaiting += $waitingEntries->count();
            $statistics[strtolower($course->type) . '_waiting'] += $waitingEntries->count();
        }

        // Sort courses by type then position
        usort($courseData, function ($a, $b) {
            $typeOrder = ['RTG' => 1, 'EDMT' => 2, 'FAM' => 3, 'GST' => 4, 'RST' => 5];
            $posOrder = ['GND' => 1, 'TWR' => 2, 'APP' => 3, 'CTR' => 4];
            
            $typeDiff = ($typeOrder[$a['type']] ?? 99) - ($typeOrder[$b['type']] ?? 99);
            if ($typeDiff !== 0) return $typeDiff;
            
            return ($posOrder[$a['position']] ?? 99) - ($posOrder[$b['position']] ?? 99);
        });

        return Inertia::render('training/mentor-waiting-lists', [
            'courses' => $courseData,
            'statistics' => array_merge($statistics, ['total_waiting' => $totalWaiting]),
            'config' => [
                'min_activity' => config('services.training.min_activity', 10),
                'display_activity' => config('services.training.display_activity', 8),
            ],
        ]);
    }

    /**
     * Start training for a Trainee
     */
    public function startTraining(Request $request, WaitingListEntry $entry): JsonResponse
    {
        if (!Gate::allows('mentor')) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $user = $request->user();
        
        // Check if user can mentor this course
        // Superusers and admins can mentor any course
        if (!$user->is_superuser && !$user->is_admin && !$user->mentorCourses()->where('id', $entry->course_id)->exists()) {
            return response()->json(['error' => 'You cannot mentor this course'], 403);
        }

        try {
            [$success, $message] = $this->waitingListService->startTraining($entry, $user);

            // TODO: Add logging

            return response()->json([
                'success' => $success,
                'message' => $message,
            ], $success ? 200 : 400);
        } catch (\Exception $e) {
            \Log::error('Error starting training', [
                'entry_id' => $entry->id,
                'mentor_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'An error occurred while starting training.',
            ], 500);
        }
    }

    /**
     * Update remarks for a waiting list entry
     */
    public function updateRemarks(Request $request)
    {
        if (!Gate::allows('mentor')) {
            return back()->withErrors(['error' => 'Access denied']);
        }

        $request->validate([
            'entry_id' => 'required|integer|exists:waiting_list_entries,id',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $entry = WaitingListEntry::findOrFail($request->entry_id);
        $user = $request->user();

        // Check if user can mentor this course
        if (!$user->is_superuser && !$user->is_admin && !$user->mentorCourses()->where('id', $entry->course_id)->exists()) {
            return back()->withErrors(['error' => 'You cannot modify this entry']);
        }

        try {
            $entry->update(['remarks' => $request->remarks ?? '']);

            ActivityLogger::remarksUpdated($entry->course, $entry->user, $user, $request->remarks ?? '');

            return back();
        } catch (\Exception $e) {
            \Log::error('Error updating remarks', [
                'entry_id' => $entry->id,
                'mentor_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'An error occurred while updating remarks.']);
        }
    }
}