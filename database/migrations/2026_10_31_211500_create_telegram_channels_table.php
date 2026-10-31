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
        if (! Schema::hasTable('telegram_channels')) {
            Schema::create('telegram_channels', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name', 150);
                $table->string('chat_id', 100)->nullable()->index();
                $table->text('description')->nullable();
                $table->string('invite_link', 255)->nullable();
                $table->json('allowed_roles')->nullable();
                $table->json('enabled_commands')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            // Seed 4 grup utama yang dibutuhkan
            $now = now();
            DB::table('telegram_channels')->insert([
                [
                    'code' => 'discipline',
                    'name' => 'Grup Kedisiplinan & BK',
                    'chat_id' => '-1003981909476',
                    'description' => 'Saluran koordinasi penanganan kedisiplinan, keterlambatan, perizinan siswa meja piket, dan rekam kasus pelanggaran siswa.',
                    'invite_link' => null,
                    'allowed_roles' => json_encode(['super_admin', 'kepala_sekolah', 'waka_kesiswaan', 'guru_bk', 'tim_tatib', 'wali_kelas', 'piket_officer']),
                    'enabled_commands' => json_encode(['/poin', '/kasus', '/rekap', '/bantuan']),
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'code' => 'piket',
                    'name' => 'Grup Piket Harian',
                    'chat_id' => null,
                    'description' => 'Saluran operasional harian meja piket sekolah: pengumuman petugas piket hari H, pemantauan KBM, penanganan kelas kosong (inval), dan buku tamu.',
                    'invite_link' => null,
                    'allowed_roles' => json_encode(['super_admin', 'kepala_sekolah', 'waka_kurikulum', 'waka_kesiswaan', 'piket_officer', 'guru']),
                    'enabled_commands' => json_encode(['/piket', '/jadwal', '/inval', '/tamu', '/rekap', '/bantuan']),
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'code' => 'activities',
                    'name' => 'Grup Kegiatan Sekolah & Kaldik',
                    'chat_id' => null,
                    'description' => 'Saluran informasi dan pengingat agenda sekolah: kalender pendidikan (kaldik), jadwal PTS/PAS/Asesmen, rapat dinas, dan upacara/hari besar.',
                    'invite_link' => null,
                    'allowed_roles' => json_encode(['super_admin', 'kepala_sekolah', 'waka_kurikulum', 'waka_kesiswaan', 'waka_sarpras', 'waka_humas', 'guru']),
                    'enabled_commands' => json_encode(['/agenda', '/jadwal', '/bantuan']),
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'code' => 'gtk',
                    'name' => 'Grup GTK SMK PGRI Kras',
                    'chat_id' => null,
                    'description' => 'Saluran komunikasi umum dan pengumuman kedinasan resmi untuk seluruh Dewan Guru dan Tenaga Kependidikan SMK PGRI Kras.',
                    'invite_link' => null,
                    'allowed_roles' => json_encode(['super_admin', 'kepala_sekolah', 'waka_kurikulum', 'waka_kesiswaan', 'waka_sarpras', 'waka_humas', 'guru', 'staf', 'tu']),
                    'enabled_commands' => json_encode(['/agenda', '/jadwal', '/id', '/bantuan']),
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_channels');
    }
};
