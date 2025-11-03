<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    public static function log(
        string $action,
        ?Model $subject = null,
        ?string $description = null,
        array $properties = [],
        ?int $userId = null
    ): ActivityLog {
        return ActivityLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'model_type' => $subject ? get_class($subject) : null,
            'model_id' => $subject?->id,
            'properties' => $properties,
            'description' => $description,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    public static function waitingListJoined(Model $course, Model $user): void
    {
        self::log(
            'waiting_list.joined',
            $course,
            "{$user->name} joined waiting list for {$course->name}",
            [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'user_id' => $user->id,
                'user_name' => $user->name,
            ],
            $user->id
        );
    }

    public static function waitingListLeft(Model $course, Model $user): void
    {
        self::log(
            'waiting_list.left',
            $course,
            "{$user->name} left waiting list for {$course->name}",
            [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'user_id' => $user->id,
                'user_name' => $user->name,
            ],
            $user->id
        );
    }

    public static function trainingStarted(Model $course, Model $trainee, Model $mentor): void
    {
        self::log(
            'training.started',
            $course,
            "{$mentor->name} started training for {$trainee->name} in {$course->name}",
            [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'trainee_id' => $trainee->id,
                'trainee_name' => $trainee->name,
                'mentor_id' => $mentor->id,
                'mentor_name' => $mentor->name,
            ],
            $mentor->id
        );
    }

    public static function endorsementGranted(string $position, Model $trainee, Model $mentor, string $type = 'tier1'): void
    {
        self::log(
            "endorsement.{$type}.granted",
            $trainee,
            "{$mentor->name} granted {$position} endorsement to {$trainee->name}",
            [
                'position' => $position,
                'trainee_id' => $trainee->id,
                'trainee_name' => $trainee->name,
                'mentor_id' => $mentor->id,
                'mentor_name' => $mentor->name,
                'type' => $type,
            ],
            $mentor->id
        );
    }

    public static function endorsementRemoved(string $position, Model $trainee, Model $mentor, string $reason = null): void
    {
        self::log(
            'endorsement.removed',
            $trainee,
            "{$mentor->name} removed {$position} endorsement from {$trainee->name}",
            [
                'position' => $position,
                'trainee_id' => $trainee->id,
                'trainee_name' => $trainee->name,
                'mentor_id' => $mentor->id,
                'mentor_name' => $mentor->name,
                'reason' => $reason,
            ],
            $mentor->id
        );
    }

    public static function soloGranted(string $position, Model $trainee, Model $mentor, string $expiryDate): void
    {
        self::log(
            'solo.granted',
            $trainee,
            "{$mentor->name} granted solo endorsement for {$position} to {$trainee->name} (expires: {$expiryDate})",
            [
                'position' => $position,
                'trainee_id' => $trainee->id,
                'trainee_name' => $trainee->name,
                'mentor_id' => $mentor->id,
                'mentor_name' => $mentor->name,
                'expiry_date' => $expiryDate,
            ],
            $mentor->id
        );
    }

    public static function soloExtended(string $position, Model $trainee, Model $mentor, string $newExpiryDate): void
    {
        self::log(
            'solo.extended',
            $trainee,
            "{$mentor->name} extended solo endorsement for {$position} for {$trainee->name} (new expiry: {$newExpiryDate})",
            [
                'position' => $position,
                'trainee_id' => $trainee->id,
                'trainee_name' => $trainee->name,
                'mentor_id' => $mentor->id,
                'mentor_name' => $mentor->name,
                'new_expiry_date' => $newExpiryDate,
            ],
            $mentor->id
        );
    }

    public static function soloRemoved(string $position, Model $trainee, Model $mentor): void
    {
        self::log(
            'solo.removed',
            $trainee,
            "{$mentor->name} removed solo endorsement for {$position} from {$trainee->name}",
            [
                'position' => $position,
                'trainee_id' => $trainee->id,
                'trainee_name' => $trainee->name,
                'mentor_id' => $mentor->id,
                'mentor_name' => $mentor->name,
            ],
            $mentor->id
        );
    }

    public static function traineeClaimed(Model $course, Model $trainee, Model $mentor): void
    {
        self::log(
            'trainee.claimed',
            $course,
            "{$mentor->name} claimed {$trainee->name} for {$course->name}",
            [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'trainee_id' => $trainee->id,
                'trainee_name' => $trainee->name,
                'mentor_id' => $mentor->id,
                'mentor_name' => $mentor->name,
            ],
            $mentor->id
        );
    }

    public static function traineeUnclaimed(Model $course, Model $trainee, Model $mentor): void
    {
        self::log(
            'trainee.unclaimed',
            $course,
            "{$mentor->name} unclaimed {$trainee->name} from {$course->name}",
            [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'trainee_id' => $trainee->id,
                'trainee_name' => $trainee->name,
                'mentor_id' => $mentor->id,
                'mentor_name' => $mentor->name,
            ],
            $mentor->id
        );
    }

    public static function traineeAssigned(Model $course, Model $trainee, Model $newMentor, Model $assigningMentor): void
    {
        self::log(
            'trainee.assigned',
            $course,
            "{$assigningMentor->name} assigned {$trainee->name} to {$newMentor->name} for {$course->name}",
            [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'trainee_id' => $trainee->id,
                'trainee_name' => $trainee->name,
                'new_mentor_id' => $newMentor->id,
                'new_mentor_name' => $newMentor->name,
                'assigning_mentor_id' => $assigningMentor->id,
                'assigning_mentor_name' => $assigningMentor->name,
            ],
            $assigningMentor->id
        );
    }

    public static function courseFinished(Model $course, Model $trainee, Model $mentor): void
    {
        self::log(
            'course.finished',
            $course,
            "{$mentor->name} marked {$course->name} as finished for {$trainee->name}",
            [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'trainee_id' => $trainee->id,
                'trainee_name' => $trainee->name,
                'mentor_id' => $mentor->id,
                'mentor_name' => $mentor->name,
            ],
            $mentor->id
        );
    }

    public static function traineeRemoved(Model $course, Model $trainee, Model $mentor): void
    {
        self::log(
            'trainee.removed',
            $course,
            "{$mentor->name} removed {$trainee->name} from {$course->name}",
            [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'trainee_id' => $trainee->id,
                'trainee_name' => $trainee->name,
                'mentor_id' => $mentor->id,
                'mentor_name' => $mentor->name,
            ],
            $mentor->id
        );
    }

    public static function traineeReactivated(Model $course, Model $trainee, Model $mentor): void
    {
        self::log(
            'trainee.reactivated',
            $course,
            "{$mentor->name} reactivated {$trainee->name} for {$course->name}",
            [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'trainee_id' => $trainee->id,
                'trainee_name' => $trainee->name,
                'mentor_id' => $mentor->id,
                'mentor_name' => $mentor->name,
            ],
            $mentor->id
        );
    }

    public static function mentorAdded(Model $course, Model $newMentor, Model $addingUser): void
    {
        self::log(
            'mentor.added',
            $course,
            "{$addingUser->name} added {$newMentor->name} as mentor for {$course->name}",
            [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'new_mentor_id' => $newMentor->id,
                'new_mentor_name' => $newMentor->name,
                'adding_user_id' => $addingUser->id,
                'adding_user_name' => $addingUser->name,
            ],
            $addingUser->id
        );
    }

    public static function mentorRemoved(Model $course, Model $removedMentor, Model $removingUser): void
    {
        self::log(
            'mentor.removed',
            $course,
            "{$removingUser->name} removed {$removedMentor->name} as mentor from {$course->name}",
            [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'removed_mentor_id' => $removedMentor->id,
                'removed_mentor_name' => $removedMentor->name,
                'removing_user_id' => $removingUser->id,
                'removing_user_name' => $removingUser->name,
            ],
            $removingUser->id
        );
    }

    public static function remarksUpdated(Model $course, Model $trainee, Model $mentor, string $newRemarks): void
    {
        self::log(
            'remarks.updated',
            $course,
            "{$mentor->name} updated remarks for {$trainee->name} in {$course->name}",
            [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'trainee_id' => $trainee->id,
                'trainee_name' => $trainee->name,
                'mentor_id' => $mentor->id,
                'mentor_name' => $mentor->name,
                'remarks_length' => strlen($newRemarks),
            ],
            $mentor->id
        );
    }

    /**
     * Log trainee added to course
     */
    public static function traineeAddedToCourse(
        Model $course,
        Model $trainee,
        Model $mentor,
        bool $wasReactivated = false
    ): void {
        self::log(
            'trainee.added_to_course',
            $course,
            "{$mentor->name} added {$trainee->name} to {$course->name}" .
            ($wasReactivated ? " (reactivated)" : ""),
            [
                'trainee_id' => $trainee->id,
                'trainee_name' => $trainee->name,
                'course_id' => $course->id,
                'course_name' => $course->name,
                'mentor_id' => $mentor->id,
                'mentor_name' => $mentor->name,
                'was_reactivated' => $wasReactivated,
            ],
            $mentor->id
        );
    }

    /**
     * Log familiarisation added
     */
    public static function familiarisationAdded(
        Model $trainee,
        string $sectorName,
        int $sectorId,
        string $fir,
        Model $mentor,
        ?Model $course = null,
        bool $viaCourseCompletion = false
    ): void {
        $description = "{$mentor->name} granted {$sectorName} ({$fir}) familiarisation to {$trainee->name}";
        if ($viaCourseCompletion && $course) {
            $description .= " via {$course->name} completion";
        }

        $properties = [
            'trainee_id' => $trainee->id,
            'trainee_name' => $trainee->name,
            'sector_id' => $sectorId,
            'sector_name' => $sectorName,
            'fir' => $fir,
            'mentor_id' => $mentor->id,
            'mentor_name' => $mentor->name,
            'via_course_completion' => $viaCourseCompletion,
        ];

        if ($course) {
            $properties['course_id'] = $course->id;
            $properties['course_name'] = $course->name;
        }

        self::log(
            'familiarisation.added',
            $trainee,
            $description,
            $properties,
            $mentor->id
        );
    }
}