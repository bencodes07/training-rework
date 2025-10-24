<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use App\Services\VatEudService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SoloController extends Controller
{
    protected VatEudService $vatEudService;

    public function __construct(VatEudService $vatEudService)
    {
        $this->vatEudService = $vatEudService;
    }

    /**
     * Add a solo endorsement for a trainee
     */
    public function addSolo(Request $request)
    {
        $user = $request->user();

        if (!$user->isMentor() && !$user->is_superuser) {
            return back()->withErrors(['error' => 'Access denied']);
        }

        $request->validate([
            'trainee_id' => 'required|integer|exists:users,id',
            'course_id' => 'required|integer|exists:courses,id',
            'expiry_date' => 'required|date|after:today',
        ]);

        $trainee = User::findOrFail($request->trainee_id);
        $course = Course::findOrFail($request->course_id);

        // Check if user can mentor this course
        if (!$user->is_superuser && !$user->is_admin && !$user->mentorCourses()->where('courses.id', $course->id)->exists()) {
            return back()->withErrors(['error' => 'You cannot manage this course']);
        }

        // Verify course type is RTG
        if ($course->type !== 'RTG') {
            return back()->withErrors(['error' => 'Solo endorsements can only be granted for rating courses']);
        }

        // Verify course has solo station configured
        if (empty($course->solo_station)) {
            return back()->withErrors(['error' => 'This course does not have a solo station configured']);
        }

        // Validate expiry date is not more than 31 days in the future
        $expiryDate = Carbon::parse($request->expiry_date);
        $maxDate = Carbon::now()->addDays(31);

        if ($expiryDate->greaterThan($maxDate)) {
            return back()->withErrors(['error' => 'Solo endorsement cannot exceed 31 days']);
        }

        // Check if trainee already has a solo for this position
        $existingSolos = $this->vatEudService->getSoloEndorsements();
        $hasSolo = collect($existingSolos)->first(function ($solo) use ($trainee, $course) {
            return $solo['user_cid'] == $trainee->vatsim_id && 
                   $solo['position'] === $course->solo_station;
        });

        if ($hasSolo) {
            return back()->withErrors(['error' => 'Trainee already has a solo endorsement for this position']);
        }

        try {
            // Format the expiry date with time
            $expiryDateTime = $expiryDate->setTime(23, 59, 0);
            $formattedExpiry = $expiryDateTime->format('Y-m-d\TH:i:s.v\Z');

            $result = $this->vatEudService->createSoloEndorsement(
                $trainee->vatsim_id,
                $course->solo_station,
                $formattedExpiry,
                $user->vatsim_id
            );

            if ($result['success']) {
                // Refresh cached endorsements
                $this->vatEudService->refreshEndorsementCache();

                Log::info('Solo endorsement granted', [
                    'mentor_id' => $user->id,
                    'mentor_vatsim_id' => $user->vatsim_id,
                    'trainee_id' => $trainee->id,
                    'trainee_vatsim_id' => $trainee->vatsim_id,
                    'course_id' => $course->id,
                    'position' => $course->solo_station,
                    'expiry' => $formattedExpiry,
                ]);

                return back()->with('success', "Successfully granted solo endorsement for {$course->solo_station} to {$trainee->name}");
            } else {
                return back()->withErrors(['error' => $result['message'] ?? 'Failed to grant solo endorsement']);
            }

        } catch (\Exception $e) {
            Log::error('Error granting solo endorsement', [
                'mentor_id' => $user->id,
                'trainee_id' => $trainee->id,
                'course_id' => $course->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'An error occurred while granting the solo endorsement']);
        }
    }

    /**
     * Extend a solo endorsement for a trainee
     */
    public function extendSolo(Request $request)
    {
        $user = $request->user();

        if (!$user->isMentor() && !$user->is_superuser) {
            return back()->withErrors(['error' => 'Access denied']);
        }

        $request->validate([
            'trainee_id' => 'required|integer|exists:users,id',
            'course_id' => 'required|integer|exists:courses,id',
            'expiry_date' => 'required|date|after:today',
        ]);

        $trainee = User::findOrFail($request->trainee_id);
        $course = Course::findOrFail($request->course_id);

        // Check if user can mentor this course
        if (!$user->is_superuser && !$user->is_admin && !$user->mentorCourses()->where('courses.id', $course->id)->exists()) {
            return back()->withErrors(['error' => 'You cannot manage this course']);
        }

        // Validate expiry date is not more than 31 days in the future
        $expiryDate = Carbon::parse($request->expiry_date);
        $maxDate = Carbon::now()->addDays(31);

        if ($expiryDate->greaterThan($maxDate)) {
            return back()->withErrors(['error' => 'Solo endorsement cannot exceed 31 days']);
        }

        try {
            // Find the existing solo
            $existingSolos = $this->vatEudService->getSoloEndorsements();
            $solo = collect($existingSolos)->first(function ($s) use ($trainee, $course) {
                return $s['user_cid'] == $trainee->vatsim_id && 
                       $s['position'] === $course->solo_station;
            });

            if (!$solo) {
                return back()->withErrors(['error' => 'No solo endorsement found for this trainee and position']);
            }

            // Remove the old solo
            $this->vatEudService->removeSoloEndorsement($solo['id']);

            // Create new solo with extended date
            $expiryDateTime = $expiryDate->setTime(23, 59, 0);
            $formattedExpiry = $expiryDateTime->format('Y-m-d\TH:i:s.v\Z');

            $result = $this->vatEudService->createSoloEndorsement(
                $trainee->vatsim_id,
                $course->solo_station,
                $formattedExpiry,
                $user->vatsim_id
            );

            if ($result['success']) {
                // Refresh cached endorsements
                $this->vatEudService->refreshEndorsementCache();

                Log::info('Solo endorsement extended', [
                    'mentor_id' => $user->id,
                    'trainee_id' => $trainee->id,
                    'course_id' => $course->id,
                    'position' => $course->solo_station,
                    'old_solo_id' => $solo['id'],
                    'new_expiry' => $formattedExpiry,
                ]);

                return back()->with('success', "Successfully extended solo endorsement for {$trainee->name}");
            } else {
                return back()->withErrors(['error' => $result['message'] ?? 'Failed to extend solo endorsement']);
            }

        } catch (\Exception $e) {
            Log::error('Error extending solo endorsement', [
                'mentor_id' => $user->id,
                'trainee_id' => $trainee->id,
                'course_id' => $course->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'An error occurred while extending the solo endorsement']);
        }
    }

    /**
     * Remove a solo endorsement
     */
    public function removeSolo(Request $request)
    {
        $user = $request->user();

        if (!$user->isMentor() && !$user->is_superuser) {
            return back()->withErrors(['error' => 'Access denied']);
        }

        $request->validate([
            'trainee_id' => 'required|integer|exists:users,id',
            'course_id' => 'required|integer|exists:courses,id',
        ]);

        $trainee = User::findOrFail($request->trainee_id);
        $course = Course::findOrFail($request->course_id);

        // Check if user can mentor this course
        if (!$user->is_superuser && !$user->is_admin && !$user->mentorCourses()->where('courses.id', $course->id)->exists()) {
            return back()->withErrors(['error' => 'You cannot manage this course']);
        }

        try {
            // Find the existing solo
            $existingSolos = $this->vatEudService->getSoloEndorsements();
            $solo = collect($existingSolos)->first(function ($s) use ($trainee, $course) {
                return $s['user_cid'] == $trainee->vatsim_id && 
                       $s['position'] === $course->solo_station;
            });

            if (!$solo) {
                return back()->withErrors(['error' => 'No solo endorsement found for this trainee and position']);
            }

            // Remove the solo
            $success = $this->vatEudService->removeSoloEndorsement($solo['id']);

            if ($success) {
                // Refresh cached endorsements
                $this->vatEudService->refreshEndorsementCache();

                Log::info('Solo endorsement removed', [
                    'mentor_id' => $user->id,
                    'trainee_id' => $trainee->id,
                    'course_id' => $course->id,
                    'solo_id' => $solo['id'],
                    'position' => $course->solo_station,
                ]);

                return back()->with('success', "Successfully removed solo endorsement for {$trainee->name}");
            } else {
                return back()->withErrors(['error' => 'Failed to remove solo endorsement']);
            }

        } catch (\Exception $e) {
            Log::error('Error removing solo endorsement', [
                'mentor_id' => $user->id,
                'trainee_id' => $trainee->id,
                'course_id' => $course->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'An error occurred while removing the solo endorsement']);
        }
    }
}