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
        Schema::table('school_settings', function (Blueprint $table) {
            $table->decimal('school_latitude', 10, 8)->nullable()->default(-7.95590200)->after('school_address');
            $table->decimal('school_longitude', 11, 8)->nullable()->default(111.97259400)->after('school_latitude');
            $table->unsignedInteger('school_radius_meters')->default(100)->after('school_longitude');
            $table->boolean('geotag_attendance_enabled')->default(true)->after('school_radius_meters');
            $table->boolean('geotag_require_selfie')->default(false)->after('geotag_attendance_enabled');
            $table->boolean('geotag_strict_radius')->default(true)->after('geotag_require_selfie');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->dropColumn([
                'school_latitude',
                'school_longitude',
                'school_radius_meters',
                'geotag_attendance_enabled',
                'geotag_require_selfie',
                'geotag_strict_radius',
            ]);
        });
    }
};
