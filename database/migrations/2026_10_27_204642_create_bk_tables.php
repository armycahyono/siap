<?php

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
        // 1. Status Tindak Lanjut Rujukan Kasus Piket ke BK
        Schema::create('bk_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piket_incident_id')->unique()->constrained('piket_incidents')->cascadeOnDelete();
            $table->enum('status', ['menunggu', 'diproses', 'selesai'])->default('menunggu')->index();
            $table->text('notes')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // 2. Pencatatan Layanan Bimbingan & Konseling
        Schema::create('bk_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piket_incident_id')->nullable()->constrained('piket_incidents')->nullOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('academic_year', 20)->index();
            $table->date('service_date')->index();
            $table->enum('service_type', ['pribadi', 'sosial', 'belajar', 'karier'])->index();
            $table->text('summary');
            $table->text('result');
            $table->text('follow_up_plan')->nullable();
            $table->foreignId('counselor_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // 3. Catatan Konseling Rahasia (Terpisah dari Operasional Layanan)
        Schema::create('bk_confidential_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bk_service_id')->unique()->constrained('bk_services')->cascadeOnDelete();
            $table->text('notes');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bk_confidential_notes');
        Schema::dropIfExists('bk_services');
        Schema::dropIfExists('bk_referrals');
    }
};
