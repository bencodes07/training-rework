<?php

namespace App\Enums;

enum ActivityAction: string
{
    case WAITING_LIST_JOINED = 'waiting_list.joined';
    case WAITING_LIST_LEFT = 'waiting_list.left';
    case WAITING_LIST_ENTRY_CREATED = 'waitinglistentry.created';
    case WAITING_LIST_ENTRY_DELETED = 'waitinglistentry.deleted';
    
    case TRAINING_STARTED = 'training.started';
    case COURSE_FINISHED = 'course.finished';
    
    case ENDORSEMENT_TIER1_GRANTED = 'endorsement.tier1.granted';
    case ENDORSEMENT_TIER2_GRANTED = 'endorsement.tier2.granted';
    case ENDORSEMENT_REMOVED = 'endorsement.removed';
    case ENDORSEMENT_NOTIFIED = 'endorsement.notified';
    case ENDORSEMENT_DELETED = 'endorsement.deleted';
    
    case SOLO_GRANTED = 'solo.granted';
    case SOLO_EXTENDED = 'solo.extended';
    case SOLO_REMOVED = 'solo.removed';
    
    case TRAINEE_CLAIMED = 'trainee.claimed';
    case TRAINEE_UNCLAIMED = 'trainee.unclaimed';
    case TRAINEE_ASSIGNED = 'trainee.assigned';
    case TRAINEE_REMOVED = 'trainee.removed';
    case TRAINEE_REACTIVATED = 'trainee.reactivated';
    case TRAINEE_ADDED_TO_COURSE = 'trainee.added_to_course';
    
    case MENTOR_ADDED = 'mentor.added';
    case MENTOR_REMOVED = 'mentor.removed';
    
    case REMARKS_UPDATED = 'remarks.updated';
    
    case FAMILIARISATION_ADDED = 'familiarisation.added';

    case TRAININGLOG_ADDED = 'traininglog.added';
    case TRAININGLOG_UPDATED = 'traininglog.updated';
    case TRAININGLOG_REMOVED = 'traininglog.removed';
    
    case CREATED = 'created';
    case UPDATED = 'updated';
    case DELETED = 'deleted';

    public function getLabel(): string
    {
        return match($this) {
            self::WAITING_LIST_JOINED => 'Joined Waiting List',
            self::WAITING_LIST_LEFT => 'Left Waiting List',
            self::WAITING_LIST_ENTRY_CREATED => 'Create Waiting List Entry',
            self::WAITING_LIST_ENTRY_DELETED => 'Deleted Waiting List Entry',
            
            self::TRAINING_STARTED => 'Training Started',
            self::COURSE_FINISHED => 'Course Finished',
            
            self::ENDORSEMENT_TIER1_GRANTED => 'Tier 1 Endorsement Granted',
            self::ENDORSEMENT_TIER2_GRANTED => 'Tier 2 Endorsement Granted',
            self::ENDORSEMENT_REMOVED => 'Endorsement Removed',
            self::ENDORSEMENT_NOTIFIED => 'Notified Removal',
            self::ENDORSEMENT_DELETED => 'Endorsement Deleted',
            
            self::SOLO_GRANTED => 'Solo Endorsement Granted',
            self::SOLO_EXTENDED => 'Solo Endorsement Extended',
            self::SOLO_REMOVED => 'Solo Endorsement Removed',
            
            self::TRAINEE_CLAIMED => 'Trainee Claimed',
            self::TRAINEE_UNCLAIMED => 'Trainee Unclaimed',
            self::TRAINEE_ASSIGNED => 'Trainee Assigned',
            self::TRAINEE_REMOVED => 'Trainee Removed',
            self::TRAINEE_REACTIVATED => 'Trainee Reactivated',
            self::TRAINEE_ADDED_TO_COURSE => 'Trainee Added to Course',
            
            self::MENTOR_ADDED => 'Mentor Added',
            self::MENTOR_REMOVED => 'Mentor Removed',
            
            self::REMARKS_UPDATED => 'Remarks Updated',
            
            self::FAMILIARISATION_ADDED => 'Familiarisation Added',

            self::TRAININGLOG_ADDED => 'Training Log Added',
            self::TRAININGLOG_UPDATED => 'Training Log Updated',
            self::TRAININGLOG_REMOVED => 'Training Log Removed',
            
            self::CREATED => 'Created',
            self::UPDATED => 'Updated',
            self::DELETED => 'Deleted',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::WAITING_LIST_JOINED,
            self::TRAINING_STARTED,
            self::ENDORSEMENT_TIER1_GRANTED,
            self::ENDORSEMENT_TIER2_GRANTED,
            self::SOLO_GRANTED,
            self::TRAINEE_CLAIMED,
            self::TRAINEE_ADDED_TO_COURSE,
            self::TRAINEE_REACTIVATED,
            self::MENTOR_ADDED,
            self::FAMILIARISATION_ADDED,
            self::CREATED => 'success',
            
            self::WAITING_LIST_LEFT,
            self::ENDORSEMENT_REMOVED,
            self::ENDORSEMENT_DELETED,
            self::SOLO_REMOVED,
            self::TRAINEE_REMOVED,
            self::MENTOR_REMOVED,
            self::DELETED => 'danger',
            
            self::SOLO_EXTENDED,
            self::TRAINEE_ASSIGNED,
            self::TRAINEE_UNCLAIMED,
            self::REMARKS_UPDATED,
            self::COURSE_FINISHED,
            self::UPDATED => 'warning',
            
            default => 'info',
        };
    }

    public static function fromString(string $action): ?self
    {
        return self::tryFrom($action);
    }

    public static function getLabels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $action) => [$action->value => $action->getLabel()])
            ->toArray();
    }

    public static function getFilterOptions(): array
    {
        return [
            'waiting_list' => 'Waiting List',
            'training' => 'Training',
            'endorsement' => 'Endorsement',
            'solo' => 'Solo',
            'trainee' => 'Trainee',
            'mentor' => 'Mentor',
            'course' => 'Course',
            'remarks' => 'Remarks',
            'familiarisation' => 'Familiarisation',
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
        ];
    }
}