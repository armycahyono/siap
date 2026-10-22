<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For users where NIK was stored in nip (length = 16 or starts with 350):
        // Ensure nik is filled, then clear nip
        DB::table('users')
            ->whereNotNull('nip')
            ->where(function ($q) {
                $q->whereRaw('LENGTH(nip) = 16')
                    ->orWhere('nip', 'like', '350%');
            })
            ->get()
            ->each(function ($user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'nik' => $user->nik ?: $user->nip,
                        'nip' => null,
                    ]);
            });

        // For users where NIGS was stored in nuptk (length <= 6):
        // Ensure nigs_fix is filled, then clear nuptk
        DB::table('users')
            ->whereNotNull('nuptk')
            ->whereRaw('LENGTH(nuptk) <= 6')
            ->get()
            ->each(function ($user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'nigs_fix' => $user->nigs_fix ?: $user->nuptk,
                        'nuptk' => null,
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reversible if needed
    }
};
