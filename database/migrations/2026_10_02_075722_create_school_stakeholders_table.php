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
        Schema::create('school_stakeholders', function (Blueprint $table) {
            $table->id();
            $table->string('category'); // yayasan, komite, pengawas, dinas
            $table->string('name');
            $table->string('position');
            $table->string('institution')->nullable();
            $table->string('nip', 30)->nullable();
            $table->string('nik', 30)->nullable();
            $table->string('rank_group', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 100)->nullable();
            $table->boolean('is_signatory')->default(true);
            $table->string('endorsement_title', 50)->nullable();
            $table->string('signature_image_path')->nullable();
            $table->integer('order_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['category', 'is_active']);
            $table->index('order_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_stakeholders');
    }
};
