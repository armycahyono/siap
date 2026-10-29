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
            if (! Schema::hasColumn('gtk_permits', 'assignment_document_path')) {
                $table->string('assignment_document_path')->nullable()->after('document_path');
            }
            if (! Schema::hasColumn('gtk_permits', 'assignment_document_name')) {
                $table->string('assignment_document_name')->nullable()->after('assignment_document_path');
            }
        });

        Schema::table('piket_inval_classes', function (Blueprint $table) {
            if (! Schema::hasColumn('piket_inval_classes', 'assignment_file_path')) {
                $table->string('assignment_file_path')->nullable()->after('assignment_material');
            }
            if (! Schema::hasColumn('piket_inval_classes', 'assignment_file_name')) {
                $table->string('assignment_file_name')->nullable()->after('assignment_file_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gtk_permits', function (Blueprint $table) {
            if (Schema::hasColumn('gtk_permits', 'assignment_document_name')) {
                $table->dropColumn('assignment_document_name');
            }
            if (Schema::hasColumn('gtk_permits', 'assignment_document_path')) {
                $table->dropColumn('assignment_document_path');
            }
        });

        Schema::table('piket_inval_classes', function (Blueprint $table) {
            if (Schema::hasColumn('piket_inval_classes', 'assignment_file_name')) {
                $table->dropColumn('assignment_file_name');
            }
            if (Schema::hasColumn('piket_inval_classes', 'assignment_file_path')) {
                $table->dropColumn('assignment_file_path');
            }
        });
    }
};
