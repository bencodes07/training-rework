<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'vatsim_id',
        'first_name',
        'last_name',
        'email',
        'subdivision',
        'rating',
        'last_rating_change',
        'is_staff',
        'is_superuser',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'last_rating_change' => 'datetime',
        'is_staff' => 'boolean',
        'is_superuser' => 'boolean',
        'rating' => 'integer',
        'vatsim_id' => 'integer',
    ];

    /**
     * Get the route key for the model.
     * This makes the model use vatsim_id for route model binding instead of id
     */
    public function getRouteKeyName()
    {
        return 'vatsim_id';
    }

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Check if user is a mentor (has any mentor role)
     */
    public function isMentor(): bool
    {
        return $this->hasAnyRole(['EDGG Mentor', 'EDMM Mentor', 'EDWW Mentor']);
    }

    /**
     * Check if user is ATD or VATGER leadership
     */
    public function isLeadership(): bool
    {
        return $this->hasAnyRole(['ATD Leitung', 'VATGER Leitung']);
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->whereIn('name', $roles)->exists();
    }

    /**
     * Many-to-many relationship with roles
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /**
     * Scope to filter mentors
     */
    public function scopeMentors($query)
    {
        return $query->whereHas('roles', function ($q) {
            $q->whereIn('name', ['EDGG Mentor', 'EDMM Mentor', 'EDWW Mentor']);
        });
    }

    /**
     * Scope to filter leadership
     */
    public function scopeLeadership($query)
    {
        return $query->whereHas('roles', function ($q) {
            $q->whereIn('name', ['ATD Leitung', 'VATGER Leitung']);
        });
    }

    /**
     * Get the username attribute (for compatibility with Django)
     * In Django, username was the VATSIM ID
     */
    public function getUsernameAttribute(): int
    {
        return $this->vatsim_id;
    }
}