<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Supervision;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TestSupervisionSeeder extends Seeder
{
    /**
     * Seed a minimal supervision record for feature tests.
     * This seeder runs only in the testing environment.
     */
    public function run(): void
    {
        // Retrieve the test users created by TestUserSeeder.
        $waka = User::where('username', 'waka')->first();
        $guru = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first();

        if (! $waka || ! $guru) {
            return; // Safety: do nothing if users are missing.
        }

        // Use the active academic year as defined in AcademicYearSeeder.
        $activeYear = AcademicYear::where('is_active', true)->first();
        if (! $activeYear) {
            return;
        }

        Supervision::firstOrCreate(
            [
                'teacher_id' => $guru->id,
                'supervisor_id' => $waka->id,
                'academic_year' => $activeYear->name,
            ],
            [
                'semester' => $activeYear->semester,
                'schedule_date' => now()->toDateString(),
                'schedule_time' => '08:00 - 09:30 WIB',
                'subject' => 'Test Supervision Subject',
                'class_name' => 'X Test Class',
                'status' => 'terjadwal',
                'qr_token' => Str::random(12),
            ]
        );
    }
}
