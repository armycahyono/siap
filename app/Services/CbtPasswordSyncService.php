<?php

namespace App\Services;

use App\Models\Cbt\CbtUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CbtPasswordSyncService
{
    /**
     * Guard to prevent bidirectional sync loops.
     */
    public static bool $isSyncing = false;

    /**
     * Push the SIAP user's password hash to the corresponding CBT user.
     */
    public function syncFromSiap(User $user): bool
    {
        if (self::$isSyncing) {
            return false;
        }

        try {
            self::$isSyncing = true;

            $cbtUser = null;
            if (! empty($user->username)) {
                $cbtUser = CbtUser::where('username', $user->username)->first();
            }

            if (! $cbtUser && ! empty($user->email)) {
                $cbtUser = CbtUser::where('email', $user->email)->first();
            }

            if (! $cbtUser) {
                return false;
            }

            // If the hash is already the same, skip to avoid redundant DB write
            if ($cbtUser->password === $user->password) {
                return true;
            }

            $cbtUser->password = $user->password;
            $cbtUser->save();

            Log::info("Synced password from SIAP to CBT for username: {$user->username}");

            return true;
        } catch (Throwable $e) {
            Log::warning("Failed syncing password from SIAP to CBT for user {$user->id}: ".$e->getMessage());

            return false;
        } finally {
            self::$isSyncing = false;
        }
    }

    /**
     * Push the CBT user's password hash back to the SIAP user.
     */
    public function syncFromCbt(CbtUser $cbtUser): bool
    {
        if (self::$isSyncing) {
            return false;
        }

        try {
            self::$isSyncing = true;

            $user = null;
            if (! empty($cbtUser->username)) {
                $user = User::where('username', $cbtUser->username)->first();
            }

            if (! $user && ! empty($cbtUser->email)) {
                $user = User::where('email', $cbtUser->email)->first();
            }

            if (! $user) {
                return false;
            }

            // If already matches, skip
            if ($user->password === $cbtUser->password) {
                return true;
            }

            // Directly update raw hash to prevent any potential double-hashing by casts
            DB::table('users')->where('id', $user->id)->update([
                'password' => $cbtUser->password,
                'password_changed_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info("Synced password from CBT to SIAP for username: {$cbtUser->username}");

            return true;
        } catch (Throwable $e) {
            Log::warning("Failed syncing password from CBT to SIAP for CBT user {$cbtUser->id}: ".$e->getMessage());

            return false;
        } finally {
            self::$isSyncing = false;
        }
    }
}
