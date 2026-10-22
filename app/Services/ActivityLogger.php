<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    public static bool $enabled = true;

    /**
     * Execute a callback without recording any activity logs.
     */
    public static function withoutLogging(callable $callback): mixed
    {
        $previous = static::$enabled;
        static::$enabled = false;

        try {
            return $callback();
        } finally {
            static::$enabled = $previous;
        }
    }

    /**
     * Record a user activity log entry.
     */
    public static function log(
        string $action,
        string $description,
        ?Model $subject = null,
        ?array $properties = null,
        ?User $user = null
    ): ?UserActivityLog {
        if (! static::$enabled) {
            return null;
        }

        try {
            $actor = $user ?? Auth::user();

            return UserActivityLog::create([
                'user_id' => $actor?->id,
                'user_name' => $actor?->name ?? 'Sistem / Tamu',
                'user_username' => $actor?->username,
                'role_name' => $actor?->role?->name ?? ($actor ? 'User' : 'System'),
                'action' => $action,
                'description' => $description,
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id' => $subject?->getKey(),
                'properties' => $properties,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        } catch (\Throwable) {
            // Silently ignore log errors to avoid blocking main user flow
            return null;
        }
    }
}
