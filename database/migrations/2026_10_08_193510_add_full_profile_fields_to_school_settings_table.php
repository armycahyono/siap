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
            // 2. Legalitas dan Status Sekolah
            $table->date('established_date')->nullable()->after('school_website');
            $table->string('establishment_decree')->nullable()->after('established_date');
            $table->string('operational_decree')->nullable()->after('establishment_decree');
            $table->date('operational_decree_valid_until')->nullable()->after('operational_decree');

            // 3. Akreditasi
            $table->string('accreditation_grade', 50)->nullable()->after('operational_decree_valid_until');
            $table->string('accreditation_year', 20)->nullable()->after('accreditation_grade');
            $table->string('accreditation_decree')->nullable()->after('accreditation_year');
            $table->date('accreditation_date')->nullable()->after('accreditation_decree');
            $table->date('accreditation_valid_until')->nullable()->after('accreditation_date');
            $table->string('accreditation_institution', 100)->nullable()->after('accreditation_valid_until');

            // 4. Visi, Misi dan Tujuan
            $table->text('vision')->nullable()->after('accreditation_institution');
            $table->text('mission')->nullable()->after('vision');
            $table->text('goals')->nullable()->after('mission');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->dropColumn([
                'established_date',
                'establishment_decree',
                'operational_decree',
                'operational_decree_valid_until',
                'accreditation_grade',
                'accreditation_year',
                'accreditation_decree',
                'accreditation_date',
                'accreditation_valid_until',
                'accreditation_institution',
                'vision',
                'mission',
                'goals',
            ]);
        });
    }
};
