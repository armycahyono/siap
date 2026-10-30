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
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->index(['user_type', 'scanned_at'], 'idx_attendance_logs_user_type_scanned_at');
        });

        Schema::table('piket_student_permits', function (Blueprint $table) {
            $table->index(['permit_date', 'permit_type'], 'idx_piket_permits_date_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('piket_student_permits', function (Blueprint $table) {
            $table->dropIndex('idx_piket_permits_date_type');
        });

        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropIndex('idx_attendance_logs_user_type_scanned_at');
        });
    }
};
