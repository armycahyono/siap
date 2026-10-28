<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('piket_incidents', function (Blueprint $table) {
            $table->string('location', 255)->nullable()->after('classroom_id');
            $table->string('verification_status', 50)->default('menunggu_verifikasi')->index()->after('status');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete()->after('verification_status');
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->text('verification_notes')->nullable()->after('verified_at');
            $table->text('disciplinary_sanction')->nullable()->after('verification_notes');
            $table->boolean('parental_involvement')->default(false)->after('disciplinary_sanction');
            $table->text('parental_involvement_notes')->nullable()->after('parental_involvement');
        });
    }

    public function down(): void
    {
        Schema::table('piket_incidents', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropColumn([
                'location',
                'verification_status',
                'verified_by',
                'verified_at',
                'verification_notes',
                'disciplinary_sanction',
                'parental_involvement',
                'parental_involvement_notes',
            ]);
        });
    }
};
