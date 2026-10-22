<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('teacher_supervisor_assignments');

        Schema::create('teacher_supervisor_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('academic_year', 50)->default('2026/2027');
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('position_title')->nullable();
            $table->string('sk_number')->nullable();
            $table->date('sk_date')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['teacher_id', 'academic_year'], 'tsa_teacher_academic_year_unique');
            $table->index(['teacher_id', 'academic_year'], 'tsa_teacher_academic_year_idx');
            $table->index('academic_year', 'tsa_academic_year_idx');
        });

        // Backfill data untuk tahun pelajaran aktif saat ini (2026/2027) dari users dengan role supervisor / waka_kurikulum
        $academicYear26 = '2026/2027';
        $academicYearId26 = DB::table('academic_years')->where('name', $academicYear26)->value('id');

        $existingSupervisors = DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->whereIn('roles.slug', ['supervisor', 'waka_kurikulum'])
            ->where('users.is_active', true)
            ->select('users.id as teacher_id', 'users.position', 'roles.slug as role_slug')
            ->get();

        $now = now();
        foreach ($existingSupervisors as $spv) {
            $positionTitle = $spv->role_slug === 'waka_kurikulum' ? 'Koordinator Tim Supervisi' : ($spv->position ?: 'Supervisor');
            DB::table('teacher_supervisor_assignments')->updateOrInsert(
                [
                    'teacher_id' => $spv->teacher_id,
                    'academic_year' => $academicYear26,
                ],
                [
                    'academic_year_id' => $academicYearId26,
                    'position_title' => $positionTitle,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_supervisor_assignments');
    }
};
