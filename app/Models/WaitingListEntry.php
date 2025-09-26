<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitingListEntry extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'date_added',
        'activity',
        'hours_updated',
        'remarks',
    ];

    protected $casts = [
        'date_added' => 'datetime',
        'activity' => 'float',
        'hours_updated' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function getWaitingTimeAttribute(): string
    {
        $now = now();
        $diffInDays = $this->date_added->diffInDays($now);

        if ($diffInDays === 0) {
            return 'Today';
        } elseif ($diffInDays === 1) {
            return '1 day';
        } elseif ($diffInDays < 7) {
            return "{$diffInDays} days";
        } else {
            $weeks = intval($diffInDays / 7);
            $remainingDays = $diffInDays % 7;
            
            if ($weeks === 1) {
                $time = '1 week';
                if ($remainingDays > 0) {
                    $time .= ", {$remainingDays}d";
                }
            } else {
                $time = "{$weeks} weeks";
                if ($remainingDays > 0) {
                    $time .= ", {$remainingDays}d";
                }
            }
            
            return $time;
        }
    }

    public function getPositionInQueueAttribute(): int
    {
        return static::where('course_id', $this->course_id)
            ->where('date_added', '<', $this->date_added)
            ->count() + 1;
    }

    public function __toString(): string
    {
        return "{$this->user->name} - {$this->course->name}";
    }
}