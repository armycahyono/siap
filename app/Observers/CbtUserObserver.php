<?php

namespace App\Observers;

use App\Models\Cbt\CbtUser;
use App\Services\CbtPasswordSyncService;

class CbtUserObserver
{
    /**
     * Handle the CbtUser "updated" event.
     * If the password field was changed in CBT, push the new hash to SIAP.
     */
    public function updated(CbtUser $cbtUser): void
    {
        if ($cbtUser->wasChanged('password')) {
            app(CbtPasswordSyncService::class)->syncFromCbt($cbtUser);
        }
    }
}
