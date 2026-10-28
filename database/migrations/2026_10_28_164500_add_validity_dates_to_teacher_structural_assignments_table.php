<?php

use App\Models\TeacherStructuralAssignment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teacher_structural_assignments', function (Blueprint $table) {
            $table->date('valid_from')->nullable()->after('sk_date')->index();
            $table->date('valid_until')->nullable()->after('valid_from')->index();
        });

        // Backfill data lama non-destruktif
        TeacherStructuralAssignment::query()->each(function ($asgn) {
            $from = $asgn->sk_date ?? ($asgn->created_at ? $asgn->created_at->toDateString() : null);
            if ($from && empty($asgn->valid_from)) {
                $asgn->update(['valid_from' => $from]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teacher_structural_assignments', function (Blueprint $table) {
            $table->dropIndex(['valid_from']);
            $table->dropIndex(['valid_until']);
            $table->dropColumn(['valid_from', 'valid_until']);
        });
    }
};
