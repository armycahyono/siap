<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('piket_incidents', function (Blueprint $table) {
            $table->string('academic_year', 20)->nullable()->index()->after('incident_date');
        });

        // Backfill academic_year from associated piket_journal where available
        try {
            if (DB::getDriverName() === 'mysql') {
                DB::statement('
                    UPDATE piket_incidents
                    JOIN piket_journals ON piket_incidents.piket_journal_id = piket_journals.id
                    SET piket_incidents.academic_year = piket_journals.academic_year
                    WHERE piket_incidents.academic_year IS NULL
                ');
            } else {
                $journals = DB::table('piket_journals')->get(['id', 'academic_year']);
                foreach ($journals as $j) {
                    DB::table('piket_incidents')
                        ->where('piket_journal_id', $j->id)
                        ->whereNull('academic_year')
                        ->update(['academic_year' => $j->academic_year]);
                }
            }
        } catch (Throwable $e) {
            // Ignore backfill failure on empty or specialized tables
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('piket_incidents', function (Blueprint $table) {
            $table->dropColumn('academic_year');
        });
    }
};
