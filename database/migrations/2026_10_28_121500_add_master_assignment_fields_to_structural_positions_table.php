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
        Schema::table('structural_positions', function (Blueprint $table) {
            $table->string('type', 30)->default('struktural')->after('category')->index();
            $table->unsignedTinyInteger('default_tenure_years')->default(1)->after('type');
            $table->string('scope_type', 30)->default('school')->after('default_tenure_years')->index();
        });

        // Pastikan seluruh data yang sudah ada memiliki nilai default yang aman dan konsisten
        DB::table('structural_positions')
            ->whereNull('type')
            ->orWhere('type', '')
            ->update([
                'type' => 'struktural',
                'default_tenure_years' => 1,
                'scope_type' => 'school',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('structural_positions', function (Blueprint $table) {
            $table->dropColumn(['type', 'default_tenure_years', 'scope_type']);
        });
    }
};
