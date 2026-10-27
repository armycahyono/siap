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
            $table->boolean('allows_multiple')->default(false)->after('category');
        });

        // Set jabatan staf/layanan operasional menjadi Jamak (bisa lebih dari 1 orang)
        DB::table('structural_positions')
            ->whereIn('code', ['SATPAM', 'STAF_TU', 'PENJAGA', 'TOOLMAN_TKJ', 'TOOLMAN_TKR', 'KEBUN'])
            ->orWhere(function ($query) {
                $query->where('category', 'Tenaga Kependidikan & Layanan')
                    ->whereNotIn('code', ['KA_TU', 'KS', 'BENDAHARA']);
            })
            ->update(['allows_multiple' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('structural_positions', function (Blueprint $table) {
            $table->dropColumn('allows_multiple');
        });
    }
};
