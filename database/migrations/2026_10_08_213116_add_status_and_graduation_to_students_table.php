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
        Schema::table('students', function (Blueprint $table) {
            $table->string('status', 20)->default('aktif')->after('classroom_id')->index();
            $table->string('graduation_year', 20)->nullable()->after('status')->index();
            $table->string('last_classroom_name', 100)->nullable()->after('graduation_year');
            $table->string('entry_year', 20)->nullable()->after('last_classroom_name');
        });

        // Sync existing rows
        DB::table('students')
            ->where('is_active', false)
            ->update(['status' => 'keluar']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['status', 'graduation_year', 'last_classroom_name', 'entry_year']);
        });
    }
};
