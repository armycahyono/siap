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
        Schema::table('users', function (Blueprint $table) {
            $table->string('nigs', 50)->nullable()->after('nuptk');
            $table->string('nigs_fix', 50)->nullable()->after('nigs');
            $table->string('numgs', 50)->nullable()->after('nigs_fix');
            $table->string('nik', 50)->nullable()->after('numgs');
            $table->string('birth_info')->nullable()->after('nik'); // Tempat, Tgl Lahir
            $table->string('birth_year', 10)->nullable()->after('birth_info');
            $table->string('gtk_status', 30)->nullable()->after('birth_year'); // GTY, GTT, PTY, PTT
            $table->string('gender', 10)->nullable()->after('gtk_status'); // L, P
            $table->string('position')->nullable()->after('gender'); // Jabatan
            $table->string('tmt_year', 10)->nullable()->after('position');
            $table->string('faculty')->nullable()->after('tmt_year');
            $table->string('education_level', 50)->nullable()->after('faculty');
            $table->string('major')->nullable()->after('education_level');
            $table->string('graduation_year', 10)->nullable()->after('major');
            $table->text('address')->nullable()->after('graduation_year');
            $table->string('city', 100)->nullable()->after('address');
            $table->string('postal_code', 20)->nullable()->after('city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'nigs',
                'nigs_fix',
                'numgs',
                'nik',
                'birth_info',
                'birth_year',
                'gtk_status',
                'gender',
                'position',
                'tmt_year',
                'faculty',
                'education_level',
                'major',
                'graduation_year',
                'address',
                'city',
                'postal_code',
            ]);
        });
    }
};
