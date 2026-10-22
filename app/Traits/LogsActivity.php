<?php

namespace App\Traits;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait LogsActivity
{
    /**
     * Boot the trait and register Eloquent lifecycle listeners.
     */
    public static function bootLogsActivity(): void
    {
        static::created(function (Model $model): void {
            if (! static::shouldLogActivity()) {
                return;
            }

            try {
                $title = method_exists($model, 'getActivityLogTitle')
                    ? $model->getActivityLogTitle()
                    : class_basename($model).' #'.$model->getKey();

                $attributes = static::filterLogAttributes($model->attributesToArray());

                ActivityLogger::log(
                    action: 'create',
                    description: "Menambahkan data {$title}.",
                    subject: $model,
                    properties: ! empty($attributes) ? ['attributes' => $attributes] : null,
                );
            } catch (\Throwable) {
                // Silently ignore to avoid disrupting main user request
            }
        });

        static::updated(function (Model $model): void {
            if (! static::shouldLogActivity()) {
                return;
            }

            try {
                $dirty = $model->getDirty();
                $diff = [];

                foreach ($dirty as $key => $newValue) {
                    if (static::isIgnoredAttribute($key, $model)) {
                        continue;
                    }

                    $oldValue = $model->getOriginal($key);

                    // Skip if value didn't actually change
                    if ($oldValue === $newValue) {
                        continue;
                    }

                    $diff[$key] = [
                        'lama' => $oldValue,
                        'baru' => $newValue,
                    ];
                }

                if (empty($diff)) {
                    return;
                }

                $title = method_exists($model, 'getActivityLogTitle')
                    ? $model->getActivityLogTitle()
                    : class_basename($model).' #'.$model->getKey();

                ActivityLogger::log(
                    action: 'update',
                    description: "Memperbarui data {$title}.",
                    subject: $model,
                    properties: [
                        'perubahan' => $diff,
                    ],
                );
            } catch (\Throwable) {
                // Silently ignore to avoid disrupting main user request
            }
        });

        static::deleted(function (Model $model): void {
            if (! static::shouldLogActivity()) {
                return;
            }

            try {
                $title = method_exists($model, 'getActivityLogTitle')
                    ? $model->getActivityLogTitle()
                    : class_basename($model).' #'.$model->getKey();

                $attributes = static::filterLogAttributes($model->attributesToArray());

                ActivityLogger::log(
                    action: 'delete',
                    description: "Menghapus data {$title}.",
                    subject: $model,
                    properties: ! empty($attributes) ? ['attributes' => $attributes] : null,
                );
            } catch (\Throwable) {
                // Silently ignore to avoid disrupting main user request
            }
        });
    }

    /**
     * Determine whether the activity should be logged.
     */
    protected static function shouldLogActivity(): bool
    {
        if (! ActivityLogger::$enabled) {
            return false;
        }

        // Only log when there is an authenticated user performing the action
        // This avoids logging background seeders and unauthenticated test setups
        return Auth::check();
    }

    /**
     * Filter sensitive and system attributes from attribute arrays.
     */
    protected static function filterLogAttributes(array $attributes): array
    {
        $ignored = [
            'password',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'qr_token',
            'updated_at',
            'created_at',
            'deleted_at',
        ];

        return array_filter($attributes, function ($key) use ($ignored) {
            return ! in_array($key, $ignored, true);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Check if a specific attribute key should be ignored from diffs.
     */
    protected static function isIgnoredAttribute(string $key, Model $model): bool
    {
        $ignored = [
            'password',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'qr_token',
            'updated_at',
            'created_at',
            'deleted_at',
        ];

        if (in_array($key, $ignored, true)) {
            return true;
        }

        if (in_array($key, $model->getHidden(), true)) {
            return true;
        }

        return false;
    }
}
