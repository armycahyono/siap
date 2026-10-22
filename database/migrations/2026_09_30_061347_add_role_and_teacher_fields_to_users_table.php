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
            $table->string('username')->unique()->after('name');
            $table->foreignId('role_id')->nullable()->after('password')->constrained('roles')->nullOnDelete();
            $table->string('nip', 50)->nullable()->after('role_id');
            $table->string('nuptk', 50)->nullable()->after('nip');
            $table->string('subject')->nullable()->after('nuptk'); // Mata pelajaran
            $table->string('expertise_program')->nullable()->after('subject'); // Konsentrasi Keahlian (e.g. TKJ, TKR, DKV, dll.)
            $table->string('target_class')->nullable()->after('expertise_program'); // Kelas (e.g. X TKJ 1)
            $table->string('phone', 30)->nullable()->after('target_class');
            $table->string('avatar')->nullable()->after('phone');
            $table->boolean('is_active')->default(true)->after('avatar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn([
                'username',
                'role_id',
                'nip',
                'nuptk',
                'subject',
                'expertise_program',
                'target_class',
                'phone',
                'avatar',
                'is_active',
            ]);
        });
    }
};
