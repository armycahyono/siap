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
        Schema::create('activity_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('color', 30)->default('#2563eb');
            $table->string('badge_class', 50)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_kaldik')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('order_index')->default(0);
            $table->timestamps();
        });

        $now = now();
        $defaults = [
            [
                'name' => 'Kegiatan Khusus SMK (Vokasi)',
                'code' => 'smk_khusus',
                'color' => '#0284c7',
                'badge_class' => 'bg-azure text-white',
                'description' => 'PKL / Prakerin, Kunjungan Industri (KI), UKK, Guru Tamu DUDI, Penyelarasan Kurikulum',
                'is_kaldik' => false,
                'is_active' => true,
                'order_index' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Asesmen & Ujian Akademik',
                'code' => 'akademik_ujian',
                'color' => '#2563eb',
                'badge_class' => 'bg-blue text-white',
                'description' => 'ANBK, ASAS (Akhir Semester), ASAT (Kenaikan Kelas), ASAJ (Akhir Jenjang), KTS / STS',
                'is_kaldik' => false,
                'is_active' => true,
                'order_index' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Supervisi & Penjaminan Mutu Guru',
                'code' => 'supervisi',
                'color' => '#4f46e5',
                'badge_class' => 'bg-indigo text-white',
                'description' => 'Supervisi Akademik, Telaah RPP / Modul Ajar, Pra/Observasi/Pasca Observasi Kelas',
                'is_kaldik' => false,
                'is_active' => true,
                'order_index' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Kalender Pendidikan Resmi (Kaldik)',
                'code' => 'kaldik',
                'color' => '#dc2626',
                'badge_class' => 'bg-danger text-white',
                'description' => 'Hari Pertama Masuk Sekolah, MPLS, Libur Semester 1 & 2, Libur Hari Besar Nasional',
                'is_kaldik' => true,
                'is_active' => true,
                'order_index' => 4,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Kesiswaan & Pengembangan Karakter',
                'code' => 'kesiswaan',
                'color' => '#16a34a',
                'badge_class' => 'bg-success text-white',
                'description' => 'Pondok Ramadhan, Peringatan Hari Besar, Purnawiyata / Wisuda, Ekstrakurikuler',
                'is_kaldik' => false,
                'is_active' => true,
                'order_index' => 5,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Rapat Kerja & Manajemen Sekolah',
                'code' => 'manajemen',
                'color' => '#f59e0b',
                'badge_class' => 'bg-warning text-dark',
                'description' => 'Rapat Kerja Awal Tahun, Workshop IHT, Evaluasi Kinerja Guru & Tendik',
                'is_kaldik' => false,
                'is_active' => true,
                'order_index' => 6,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Kegiatan Umum Lainnya',
                'code' => 'lainnya',
                'color' => '#64748b',
                'badge_class' => 'bg-secondary text-white',
                'description' => 'Agenda penugasan atau kegiatan sekolah insidental lainnya',
                'is_kaldik' => false,
                'is_active' => true,
                'order_index' => 7,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('activity_categories')->insert($defaults);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_categories');
    }
};
