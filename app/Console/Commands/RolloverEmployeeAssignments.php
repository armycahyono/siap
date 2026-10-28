<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\UserRoleAssignment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RolloverEmployeeAssignments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'siap:rollover-assignments
                            {from : Tahun ajaran asal (misal: 2026/2027)}
                            {to : Tahun ajaran tujuan (misal: 2027/2028)}
                            {--close-previous : Tutup resmi penugasan di tahun ajaran asal}
                            {--sk= : Nomor SK baru untuk penugasan}
                            {--start-date= : Tanggal mulai berlaku penugasan baru (YYYY-MM-DD)}
                            {--end-date= : Tanggal akhir berlaku penugasan baru (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Salin / rollover penugasan pegawai antar tahun ajaran secara aman tanpa duplikasi akun';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $fromYear = $this->argument('from');
        $toYear = $this->argument('to');
        $closePrevious = (bool) $this->option('close-previous');
        $newSk = $this->option('sk');
        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');

        if ($fromYear === $toYear) {
            $this->error('Tahun ajaran asal dan tujuan tidak boleh sama.');

            return self::FAILURE;
        }

        $this->info("Memulai rollover penugasan pegawai dari {$fromYear} ke {$toYear}...");

        $targetAy = AcademicYear::where('name', $toYear)->first();

        $activeAssignments = UserRoleAssignment::where('status', true)
            ->where('academic_year', $fromYear)
            ->get();

        if ($activeAssignments->isEmpty()) {
            $this->warn("Tidak ditemukan penugasan aktif untuk tahun ajaran {$fromYear}.");

            return self::SUCCESS;
        }

        $this->info("Ditemukan {$activeAssignments->count()} penugasan aktif pada tahun ajaran {$fromYear}.");

        $createdCount = 0;
        $closedCount = 0;

        DB::transaction(function () use (
            $activeAssignments,
            $fromYear,
            $toYear,
            $targetAy,
            $closePrevious,
            $newSk,
            $startDate,
            $endDate,
            &$createdCount,
            &$closedCount
        ) {
            $today = now()->toDateString();
            $closingDate = $startDate ? Carbon::parse($startDate)->subDay()->toDateString() : $today;

            foreach ($activeAssignments as $assignment) {
                // Check if target assignment already exists
                $exists = UserRoleAssignment::where('user_id', $assignment->user_id)
                    ->where('role_id', $assignment->role_id)
                    ->where('academic_year', $toYear)
                    ->when($assignment->context_type, fn ($q) => $q->where('context_type', $assignment->context_type)->where('context_id', $assignment->context_id))
                    ->exists();

                if (! $exists) {
                    UserRoleAssignment::create([
                        'user_id' => $assignment->user_id,
                        'role_id' => $assignment->role_id,
                        'department_id' => $assignment->department_id,
                        'academic_year_id' => $targetAy?->id,
                        'academic_year' => $toYear,
                        'context_type' => $assignment->context_type,
                        'context_id' => $assignment->context_id,
                        'valid_from' => $startDate,
                        'valid_until' => $endDate,
                        'status' => true,
                        'sk_number' => $newSk ?: $assignment->sk_number,
                        'notes' => 'Rollover dari TA '.$fromYear.($assignment->notes ? ' - '.$assignment->notes : ''),
                        'assigned_by' => $assignment->assigned_by,
                    ]);
                    $createdCount++;
                }

                if ($closePrevious) {
                    $assignment->update([
                        'status' => false,
                        'valid_until' => $closingDate,
                        'notes' => trim(($assignment->notes ?? '')." [Ditutup resmi via rollover ke {$toYear}]"),
                    ]);
                    $closedCount++;
                }
            }
        });

        $this->info("Selesai! Berhasil membuat {$createdCount} penugasan baru untuk Tahun Ajaran {$toYear}.");
        if ($closePrevious) {
            $this->info("Berhasil menutup resmi {$closedCount} penugasan pada Tahun Ajaran {$fromYear}.");
        }

        return self::SUCCESS;
    }
}
