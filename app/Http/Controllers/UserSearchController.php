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
            $users = User::where(function($q) use ($query) {
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

        $results = $users->map(function($user) {
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

        // Get active courses
        $activeCourses = $user->activeCourses()
            ->with(['mentorGroup'])
            ->get()
            ->map(function($course) {
                return [
                    'id' => $course->id,
                    'name' => $course->name,
                    'type' => $course->type_display,
                    'position' => $course->position_display,
                ];
            });

        // Get completed courses
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

        // Get familiarisations
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
                });
            });

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
            'moodle_courses' => [],
            'familiarisations' => $familiarisations,
        ];

        return Inertia::render('users/profile', [
            'userData' => $userData,
        ]);
    }
}