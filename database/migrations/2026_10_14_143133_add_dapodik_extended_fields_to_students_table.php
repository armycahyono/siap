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
            // Kontak & Domisili
            $table->string('email', 150)->nullable()->after('phone_student');
            $table->string('postal_code', 15)->nullable()->after('address');
            $table->string('residence_type', 50)->nullable()->after('postal_code');
            $table->string('transportation', 50)->nullable()->after('residence_type');
            $table->decimal('distance_to_school_km', 6, 2)->nullable()->after('transportation');
            $table->decimal('latitude', 11, 8)->nullable()->after('distance_to_school_km');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');

            // Data Keluarga & Fisik
            $table->string('family_card_no', 30)->nullable()->after('nik');
            $table->string('birth_certificate_no', 50)->nullable()->after('birth_date');
            $table->unsignedTinyInteger('child_order')->nullable()->after('birth_certificate_no');
            $table->unsignedTinyInteger('siblings_count')->nullable()->after('child_order');
            $table->unsignedSmallInteger('weight_kg')->nullable()->after('siblings_count');
            $table->unsignedSmallInteger('height_cm')->nullable()->after('weight_kg');
            $table->unsignedSmallInteger('head_circumference_cm')->nullable()->after('height_cm');
            $table->string('special_needs', 100)->nullable()->after('head_circumference_cm');

            // Akademik & Asal Sekolah
            $table->string('school_origin', 150)->nullable()->after('entry_year');
            $table->string('national_exam_no', 50)->nullable()->after('school_origin');
            $table->string('diploma_serial_no', 50)->nullable()->after('national_exam_no');
            $table->string('skhun_no', 50)->nullable()->after('diploma_serial_no');

            // Kesejahteraan / Bansos (KIP, PIP, KPS, KKS, Bank)
            $table->boolean('is_pip_eligible')->default(false)->after('skhun_no');
            $table->string('pip_reason', 255)->nullable()->after('is_pip_eligible');
            $table->boolean('kip_recipient')->default(false)->after('pip_reason');
            $table->string('kip_number', 50)->nullable()->after('kip_recipient');
            $table->string('kip_name', 150)->nullable()->after('kip_number');
            $table->string('kks_number', 50)->nullable()->after('kip_name');
            $table->boolean('kps_recipient')->default(false)->after('kks_number');
            $table->string('kps_number', 50)->nullable()->after('kps_recipient');
            $table->string('bank_name', 50)->nullable()->after('kps_number');
            $table->string('bank_account_no', 50)->nullable()->after('bank_name');
            $table->string('bank_account_name', 150)->nullable()->after('bank_account_no');

            // Rincian Tambahan Orang Tua / Wali
            $table->string('father_birth_year', 10)->nullable()->after('father_name');
            $table->string('father_education', 50)->nullable()->after('father_birth_year');
            $table->string('father_income', 50)->nullable()->after('father_job');
            $table->string('father_nik', 30)->nullable()->after('father_income');

            $table->string('mother_birth_year', 10)->nullable()->after('mother_name');
            $table->string('mother_education', 50)->nullable()->after('mother_birth_year');
            $table->string('mother_income', 50)->nullable()->after('mother_job');
            $table->string('mother_nik', 30)->nullable()->after('mother_income');

            $table->string('guardian_birth_year', 10)->nullable()->after('guardian_name');
            $table->string('guardian_education', 50)->nullable()->after('guardian_birth_year');
            $table->string('guardian_income', 50)->nullable()->after('guardian_job');
            $table->string('guardian_nik', 30)->nullable()->after('guardian_income');

            // Seluruh 66 Kolom Mentah Disimpan Lengkap dalam JSON
            $table->json('dapodik_payload')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'email',
                'postal_code',
                'residence_type',
                'transportation',
                'distance_to_school_km',
                'latitude',
                'longitude',
                'family_card_no',
                'birth_certificate_no',
                'child_order',
                'siblings_count',
                'weight_kg',
                'height_cm',
                'head_circumference_cm',
                'special_needs',
                'school_origin',
                'national_exam_no',
                'diploma_serial_no',
                'skhun_no',
                'is_pip_eligible',
                'pip_reason',
                'kip_recipient',
                'kip_number',
                'kip_name',
                'kks_number',
                'kps_recipient',
                'kps_number',
                'bank_name',
                'bank_account_no',
                'bank_account_name',
                'father_birth_year',
                'father_education',
                'father_income',
                'father_nik',
                'mother_birth_year',
                'mother_education',
                'mother_income',
                'mother_nik',
                'guardian_birth_year',
                'guardian_education',
                'guardian_income',
                'guardian_nik',
                'dapodik_payload',
            ]);
        });
    }
};
