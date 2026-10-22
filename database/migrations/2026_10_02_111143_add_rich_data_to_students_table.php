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
        Schema::table('students', function (Blueprint $table) {
            $table->string('nik', 30)->nullable()->after('nis')->index();
            $table->string('birth_place', 100)->nullable()->after('gender');
            $table->date('birth_date')->nullable()->after('birth_place');
            $table->string('religion', 50)->nullable()->after('birth_date');
            $table->text('address')->nullable()->after('religion');

            // Data Orang Tua / Ayah
            $table->string('father_name', 255)->nullable()->after('address');
            $table->string('father_job', 100)->nullable()->after('father_name');
            $table->string('father_phone', 50)->nullable()->after('father_job');

            // Data Orang Tua / Ibu
            $table->string('mother_name', 255)->nullable()->after('father_phone');
            $table->string('mother_job', 100)->nullable()->after('mother_name');
            $table->string('mother_phone', 50)->nullable()->after('mother_job');

            // Data Wali
            $table->string('guardian_name', 255)->nullable()->after('mother_phone');
            $table->string('guardian_job', 100)->nullable()->after('guardian_name');
            $table->string('guardian_phone', 50)->nullable()->after('guardian_job');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'nik',
                'birth_place',
                'birth_date',
                'religion',
                'address',
                'father_name',
                'father_job',
                'father_phone',
                'mother_name',
                'mother_job',
                'mother_phone',
                'guardian_name',
                'guardian_job',
                'guardian_phone',
            ]);
        });
    }
};
