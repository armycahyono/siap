<?php

namespace App\Observers;

use App\Models\User;
use App\Services\CbtPasswordSyncService;

class UserObserver
{
    /**
     * Handle the User "saving" event.
     * Record when password is changed.
     */
    public function saving(User $user): void
    {
        if ($user->isDirty('password') && ! $user->isDirty('password_changed_at')) {
            $user->password_changed_at = now();
        }
    }

    /**
     * Handle the User "updated" event.
     * If the password field was changed, push the new hash to CBT.
     */
    public function updated(User $user): void
    {
        if ($user->wasChanged('password')) {
            app(CbtPasswordSyncService::class)->syncFromSiap($user);
        }
    }
}
