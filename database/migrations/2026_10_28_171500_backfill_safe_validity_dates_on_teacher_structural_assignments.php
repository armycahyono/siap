<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Backfill aman: Menyelaraskan valid_from dengan sk_date yang sudah tercatat
     * tanpa mengarang atau memaksakan valid_until, agar data lama ditangani secara eksplisit.
     */
    public function up(): void
    {
        DB::table('teacher_structural_assignments')
            ->whereNull('valid_from')
            ->whereNotNull('sk_date')
            ->update([
                'valid_from' => DB::raw('sk_date'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive: tidak membatalkan backfill data
    }
};
