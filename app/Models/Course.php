<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = [
        'name',
        'trainee_display_name',
        'description',
        'airport_name',
        'airport_icao',
        'solo_station',
        'mentor_group_id',
        'min_rating',
        'max_rating',
        'type',
        'position',
        'moodle_course_ids',
        'familiarisation_sector_id',
    ];

    protected $casts = [
        'min_rating' => 'integer',
        'max_rating' => 'integer',
        'type' => 'string',
        'position' => 'string',
        'moodle_course_ids' => 'array',
    ];

    // Relationships
    public function mentorGroup(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'mentor_group_id');
    }

    public function familiarisationSector(): BelongsTo
    {
        return $this->belongsTo(FamiliarisationSector::class);
    }

    public function activeTrainees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_trainees');
    }

    public function mentors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_mentors');
    }

    public function waitingListEntries(): HasMany
    {
        return $this->hasMany(WaitingListEntry::class);
    }

    public function endorsementGroups()
    {
        // This returns the endorsement group names as a collection
        return collect(
            \DB::table('course_endorsement_groups')
                ->where('course_id', $this->id)
                ->pluck('endorsement_group_name')
        );
    }

    // Helper methods
    public function getTypeDisplayAttribute(): string
    {
        return match($this->type) {
            'EDMT' => 'Endorsement',
            'RTG' => 'Rating',
            'GST' => 'Visitor',
            'FAM' => 'Familiarisation',
            'RST' => 'Roster Reentry',
        };
    }

    public function getPositionDisplayAttribute(): string
    {
        return match($this->position) {
            'GND' => 'Ground',
            'TWR' => 'Tower',
            'APP' => 'Approach',
            'CTR' => 'Centre',
        };
    }

    public function __toString(): string
    {
        return "{$this->airport_name} {$this->position_display} - {$this->type_display}";
    }

    // Scopes
    public function scopeForRating($query, int $rating)
    {
        return $query->where('min_rating', '<=', $rating)
                    ->where('max_rating', '>=', $rating);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeAvailableFor($query, User $user)
    {
        return $query->whereDoesntHave('activeTrainees', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });
    }
}