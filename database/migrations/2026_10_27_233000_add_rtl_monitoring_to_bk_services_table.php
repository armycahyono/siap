<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bk_services', function (Blueprint $table) {
            $table->foreignId('follow_up_assignee_id')->nullable()->constrained('users')->nullOnDelete()->after('follow_up_plan');
            $table->date('follow_up_target_date')->nullable()->after('follow_up_assignee_id');
            $table->string('follow_up_status', 50)->default('belum_dilaksanakan')->index()->after('follow_up_target_date');
            $table->text('follow_up_evaluation_notes')->nullable()->after('follow_up_status');
            $table->timestamp('follow_up_evaluated_at')->nullable()->after('follow_up_evaluation_notes');
        });
    }

    public function down(): void
    {
        Schema::table('bk_services', function (Blueprint $table) {
            $table->dropForeign(['follow_up_assignee_id']);
            $table->dropColumn([
                'follow_up_assignee_id',
                'follow_up_target_date',
                'follow_up_status',
                'follow_up_evaluation_notes',
                'follow_up_evaluated_at',
            ]);
        });
    }
};
