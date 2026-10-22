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
        Schema::create('structural_positions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->string('category')->nullable();
            $table->integer('order')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('teacher_structural_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('structural_position_id')->constrained('structural_positions')->cascadeOnDelete();
            $table->string('academic_year', 50)->default('2026/2027');
            $table->string('sk_number')->nullable();
            $table->date('sk_date')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['teacher_id', 'academic_year'], 'tsa_teacher_year_idx');
            $table->index(['structural_position_id', 'academic_year'], 'tsa_pos_year_idx');
        });

        $positions = [
            ['name' => 'Kepala Sekolah', 'code' => 'KS', 'category' => 'Pimpinan', 'order' => 1],
            ['name' => 'Waka Kurikulum', 'code' => 'WAKA_KUR', 'category' => 'Wakil Kepala Sekolah', 'order' => 2],
            ['name' => 'Waka Sarpras', 'code' => 'WAKA_SARPRAS', 'category' => 'Wakil Kepala Sekolah', 'order' => 3],
            ['name' => 'Waka Kesiswaan', 'code' => 'WAKA_KESISWAAN', 'category' => 'Wakil Kepala Sekolah', 'order' => 4],
            ['name' => 'Waka Humas', 'code' => 'WAKA_HUMAS', 'category' => 'Wakil Kepala Sekolah', 'order' => 5],
            ['name' => 'Bendahara Sekolah', 'code' => 'BENDAHARA', 'category' => 'Tata Kelola & Keuangan', 'order' => 6],
            ['name' => 'Kaprogli TKR', 'code' => 'KAPRO_TKR', 'category' => 'Kepala Program Keahlian', 'order' => 7],
            ['name' => 'Kaprogli TKJ', 'code' => 'KAPRO_TKJ', 'category' => 'Kepala Program Keahlian', 'order' => 8],
            ['name' => 'Kaprogli AKL', 'code' => 'KAPRO_AKL', 'category' => 'Kepala Program Keahlian', 'order' => 9],
            ['name' => 'Ka. Perpus', 'code' => 'KA_PERPUS', 'category' => 'Kepala Unit & Fasilitas', 'order' => 10],
            ['name' => 'Ka. Bengkel', 'code' => 'KA_BENGKEL', 'category' => 'Kepala Unit & Fasilitas', 'order' => 11],
            ['name' => 'Ka. Lab Komputer', 'code' => 'KA_LAB_KOMP', 'category' => 'Kepala Unit & Fasilitas', 'order' => 12],
            ['name' => 'Ka. TU', 'code' => 'KA_TU', 'category' => 'Tata Kelola & Keuangan', 'order' => 13],
        ];

        $now = now();
        foreach ($positions as $pos) {
            DB::table('structural_positions')->insert([
                'name' => $pos['name'],
                'code' => $pos['code'],
                'category' => $pos['category'],
                'order' => $pos['order'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_structural_assignments');
        Schema::dropIfExists('structural_positions');
    }
};
