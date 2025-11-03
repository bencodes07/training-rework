<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait LogsActivity
{
    public static function bootLogsActivity()
    {
        static::created(function ($model) {
            $model->logActivity('created');
        });

        static::updated(function ($model) {
            $model->logActivity('updated');
        });

        static::deleted(function ($model) {
            $model->logActivity('deleted');
        });
    }

    protected function logActivity(string $action, ?string $description = null, array $extraProperties = [])
    {
        if (!$this->shouldLogActivity($action)) {
            return;
        }

        $properties = array_merge([
            'attributes' => $this->attributesToLog($action),
            'old' => $action === 'updated' ? $this->getOriginal() : null,
        ], $extraProperties);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $this->getActivityAction($action),
            'model_type' => get_class($this),
            'model_id' => $this->id,
            'properties' => $properties,
            'description' => $description ?? $this->getActivityDescription($action),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    protected function shouldLogActivity(string $action): bool
    {
        if (property_exists($this, 'logOnly') && !in_array($action, $this->logOnly)) {
            return false;
        }

        if (property_exists($this, 'logExcept') && in_array($action, $this->logExcept)) {
            return false;
        }

        return true;
    }

    protected function attributesToLog(string $action): array
    {
        $attributes = $this->getAttributes();

        if (property_exists($this, 'logAttributes')) {
            return array_intersect_key($attributes, array_flip($this->logAttributes));
        }

        if (property_exists($this, 'hidden')) {
            return array_diff_key($attributes, array_flip($this->hidden));
        }

        return $attributes;
    }

    protected function getActivityAction(string $action): string
    {
        $modelName = class_basename($this);
        return strtolower($modelName) . '.' . $action;
    }

    protected function getActivityDescription(string $action): string
    {
        $modelName = class_basename($this);
        $userName = Auth::user()?->name ?? 'System';

        return match($action) {
            'created' => "{$userName} created {$modelName} #{$this->id}",
            'updated' => "{$userName} updated {$modelName} #{$this->id}",
            'deleted' => "{$userName} deleted {$modelName} #{$this->id}",
            default => "{$userName} performed {$action} on {$modelName} #{$this->id}",
        };
    }

    public function activityLogs()
    {
        return $this->morphMany(ActivityLog::class, 'model');
    }
}