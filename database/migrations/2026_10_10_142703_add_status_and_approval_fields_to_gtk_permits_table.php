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
        Schema::table('gtk_permits', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('document_path')->index();
            $table->text('approval_note')->nullable()->after('approved_by');
            $table->timestamp('approved_at')->nullable()->after('approval_note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gtk_permits', function (Blueprint $table) {
            $table->dropColumn(['status', 'approval_note', 'approved_at']);
        });
    }
};
