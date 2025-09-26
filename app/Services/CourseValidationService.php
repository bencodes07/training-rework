<?php

namespace App\Services;

use App\Models\Course;
use App\Models\User;
use App\Models\WaitingListEntry;
use App\Models\Familiarisation;
use App\Services\VatEudService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class CourseValidationService
{
    protected VatEudService $vatEudService;
    protected VatsimActivityService $activityService;

    public function __construct(VatEudService $vatEudService, VatsimActivityService $activityService)
    {
        $this->vatEudService = $vatEudService;
        $this->activityService = $activityService;
    }

    /**
     * Check if a user can join a waiting list for a course
     */
    public function canUserJoinCourse(Course $course, User $user): array
    {
        try {
            $roster = $this->getRoster();
        } catch (\Exception $e) {
            Log::warning('Failed to fetch roster, allowing course join', ['error' => $e->getMessage()]);
            return [true, ''];
        }

        // Check rating requirements (skip for guest courses)
        if ($course->type !== 'GST' && 
            !($course->min_rating <= $user->rating && $user->rating <= $course->max_rating)) {
            return [false, 'You do not have the required rating for this course.'];
        }

        // Check for existing RTG course
        if ($user->activeRatingCourses()->exists() && $course->type === 'RTG') {
            return [false, 'You already have an active RTG course.'];
        }

        // Check subdivision restrictions
        if ($user->subdivision === 'GER' && $course->type === 'GST') {
            return [false, 'You are not allowed to enter the waiting list for a visitor course.'];
        }

        if ($user->subdivision !== 'GER' && $course->type === 'RTG') {
            return [false, 'You are not allowed to enter the waiting list for a rating course.'];
        }

        // Check familiarisation requirements
        if ($course->familiarisation_sector_id && 
            Familiarisation::where('user_id', $user->id)
                ->where('familiarisation_sector_id', $course->familiarisation_sector_id)
                ->exists()) {
            return [false, 'You already have a familiarisation for this course.'];
        }

        // Check endorsement requirements
        $endorsementGroups = $course->endorsementGroups();
        if ($endorsementGroups->isNotEmpty()) {
            $userEndorsements = $this->getUserEndorsements($user->vatsim_id);
            $hasAllEndorsements = $endorsementGroups->every(function ($group) use ($userEndorsements) {
                return $userEndorsements->contains($group);
            });

            if ($hasAllEndorsements && $course->type === 'EDMT') {
                return [false, 'You already have the required endorsements for this course.'];
            }
        }

        // Check roster status
        if (!in_array($user->vatsim_id, $roster) && 
            $user->subdivision === 'GER' && 
            $course->type !== 'RST') {
            return [false, 'You are not on the roster.'];
        }

        if (in_array($user->vatsim_id, $roster) && $course->type === 'RST') {
            return [false, 'You are already on the roster.'];
        }

        // Check S3 rating change requirements
        if ($user->rating === 3 && 
            $course->type === 'RTG' && 
            $course->position === 'APP') {
            $minDays = (int) config('services.training.s3_rating_change_days', 90);
            
            if ($user->last_rating_change && 
                now()->diffInDays($user->last_rating_change) < $minDays) {
                return [false, 'Your last rating change was less than 3 months ago. You cannot join an S3 course yet.'];
            }
        }

        return [true, ''];
    }

    /**
     * Get roster from VatEUD with caching - MADE PUBLIC
     */
    public function getRoster(): array
    {
        return Cache::remember('vateud:roster', now()->addMinutes(60), function () {
            try {
                $response = Http::withHeaders([
                    'X-API-KEY' => config('services.vateud.token'),
                    'Accept' => 'application/json',
                    'User-Agent' => 'VATGER Training System',
                ])->get('https://core.vateud.net/api/facility/roster');

                if ($response->successful()) {
                    $data = $response->json();
                    return $data['data']['controllers'] ?? [];
                }
            } catch (\Exception $e) {
                Log::error('Failed to fetch roster from VatEUD', ['error' => $e->getMessage()]);
            }

            return [];
        });
    }

    /**
     * Get user endorsements - MADE PUBLIC
     */
    public function getUserEndorsements(int $vatsimId): \Illuminate\Support\Collection
    {
        return Cache::remember("user_endorsements:{$vatsimId}", now()->addHours(1), function () use ($vatsimId) {
            try {
                $tier1 = $this->vatEudService->getTier1Endorsements();
                return collect($tier1)
                    ->where('user_cid', $vatsimId)
                    ->pluck('position');
            } catch (\Exception $e) {
                Log::error('Failed to fetch user endorsements', [
                    'vatsim_id' => $vatsimId,
                    'error' => $e->getMessage()
                ]);
                return collect();
            }
        });
    }

    /**
     * Check if user has minimum required activity hours for a course
     */
    public function hasMinimumActivity(Course $course, User $user): bool
    {
        if ($course->type !== 'RTG' || in_array($course->position, ['GND', 'TWR'])) {
            return true;
        }

        $minHours = config('services.training.min_hours', 25);
        $activityHours = $this->getActivityHours($course, $user);

        return $activityHours >= $minHours;
    }

    /**
     * Get activity hours for a user on a specific position
     */
    public function getActivityHours(Course $course, User $user): float
    {
        try {
            // This would integrate with your existing VATSIM activity service
            // For now, return a placeholder - you'll need to adapt your activity calculation logic
            return 0.0;
        } catch (\Exception $e) {
            Log::error('Failed to get activity hours', [
                'course_id' => $course->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return 0.0;
        }
    }

    /**
     * Check if user is on the roster
     */
    public function isUserOnRoster(int $vatsimId): bool
    {
        try {
            $roster = $this->getRoster();
            return in_array($vatsimId, $roster);
        } catch (\Exception $e) {
            Log::warning('Failed to check roster status', [
                'vatsim_id' => $vatsimId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}