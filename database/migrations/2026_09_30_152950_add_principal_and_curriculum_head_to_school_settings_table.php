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
            $table->foreignId('principal_id')->nullable()->after('school_website')->constrained('users')->nullOnDelete();
            $table->foreignId('curriculum_head_id')->nullable()->after('principal_nip')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('principal_id');
            $table->dropConstrainedForeignId('curriculum_head_id');
        });
    }
};
