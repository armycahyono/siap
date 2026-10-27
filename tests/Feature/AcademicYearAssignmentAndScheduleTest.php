<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\LessonSchedule;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\StructuralPosition;
use App\Models\Subject;
use App\Models\TeacherStructuralAssignment;
use App\Models\TeacherSubjectAssignment;
use App\Models\TeacherSupervisorAssignment;
use App\Models\TimetableUpload;
use App\Models\User;
use App\Services\TimetableSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AcademicYearAssignmentAndScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $teacher;

    protected Subject $subject;

    protected Classroom $classroom;

    protected AcademicYear $year2026;

    protected AcademicYear $year2024;

    protected AcademicYear $year2027;

    protected StructuralPosition $structuralPosition;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['slug' => 'super_admin'], [
            'name' => 'Super Admin',
            'description' => 'Administrator',
            'is_system' => true,
        ]);

        $teacherRole = Role::firstOrCreate(['slug' => 'guru'], [
            'name' => 'Guru',
            'description' => 'Guru Pengampu',
            'is_system' => false,
        ]);

        Role::firstOrCreate(['slug' => 'supervisor'], [
            'name' => 'Supervisor',
            'description' => 'Supervisor Pembelajaran',
            'is_system' => false,
        ]);

        $this->year2024 = AcademicYear::create([
            'name' => '2024/2025',
            'semester' => 'Ganjil',
            'start_date' => '2024-07-15',
            'end_date' => '2025-06-20',
            'is_active' => false,
        ]);

        $this->year2026 = AcademicYear::create([
            'name' => '2026/2027',
            'semester' => 'Ganjil',
            'start_date' => '2026-07-15',
            'end_date' => '2027-06-20',
            'is_active' => true,
        ]);

        $this->year2027 = AcademicYear::create([
            'name' => '2027/2028',
            'semester' => 'Ganjil',
            'start_date' => '2027-07-15',
            'end_date' => '2028-06-20',
            'is_active' => false,
        ]);

        SchoolSetting::create([
            'school_name' => 'SMK Negeri 1 Test',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
        ]);

        $this->admin = User::create([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $this->teacher = User::create([
            'name' => 'Budi Santoso',
            'username' => 'budi',
            'email' => 'budi@test.com',
            'password' => bcrypt('password'),
            'role_id' => $teacherRole->id,
            'is_active' => true,
        ]);

        // Pastikan GradeLevel 1 (X), 2 (XI), 3 (XII) tersedia untuk sinkronisasi rombel
        GradeLevel::firstOrCreate(['id' => 1], ['name' => 'X', 'level' => 10]);
        GradeLevel::firstOrCreate(['id' => 2], ['name' => 'XI', 'level' => 11]);
        GradeLevel::firstOrCreate(['id' => 3], ['name' => 'XII', 'level' => 12]);

        $dept = Department::firstOrCreate(['code' => 'TKR'], ['name' => 'Teknik Kendaraan Ringan']);

        $this->classroom = Classroom::create([
            'name' => 'X TKR 1',
            'academic_year' => '2026/2027',
            'academic_year_id' => $this->year2026->id,
            'department_id' => $dept->id,
            'grade_level_id' => 1,
            'is_active' => true,
        ]);

        $this->subject = Subject::create([
            'code' => 'MAT-X',
            'name' => 'Matematika X',
            'category' => 'Umum',
            'is_active' => true,
        ]);

        $this->structuralPosition = StructuralPosition::create([
            'name' => 'Kepala Bengkel TKR',
            'category' => 'Jurusan',
            'allows_multiple' => false,
            'is_active' => true,
        ]);
    }

    /**
     * Skenario 1 — Sesi arsip tidak mencemari penugasan baru.
     */
    public function test_archive_session_does_not_contaminate_new_subject_assignment(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['active_academic_year' => '2024/2025'])
            ->post(route('teacher-assignments.store-subject'), [
                'teacher_id' => $this->teacher->id,
                'subject_id' => $this->subject->id,
                'classroom_id' => $this->classroom->id,
                'semester' => 'Ganjil',
                'hours_per_week' => 4,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $assignment = TeacherSubjectAssignment::where('teacher_id', $this->teacher->id)
            ->where('subject_id', $this->subject->id)
            ->first();

        $this->assertNotNull($assignment);
        // Harus menggunakan tahun operasional 2026/2027, bukan sesi 2024/2025
        $this->assertEquals('2026/2027', $assignment->academic_year);
    }

    public function test_archive_session_does_not_contaminate_new_structural_assignment(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['active_academic_year' => '2024/2025'])
            ->post(route('teacher-assignments.store-structural'), [
                'teacher_id' => $this->teacher->id,
                'structural_position_id' => $this->structuralPosition->id,
                'sk_number' => 'SK/001/2026',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $assignment = TeacherStructuralAssignment::where('teacher_id', $this->teacher->id)
            ->where('structural_position_id', $this->structuralPosition->id)
            ->first();

        $this->assertNotNull($assignment);
        $this->assertEquals('2026/2027', $assignment->academic_year);
    }

    public function test_archive_session_does_not_contaminate_supervisor_assignment(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['active_academic_year' => '2024/2025'])
            ->post(route('teacher-assignments.assign-supervisor'), [
                'teacher_id' => $this->teacher->id,
                'position_title' => 'Supervisor Akademik',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $assignment = TeacherSupervisorAssignment::where('teacher_id', $this->teacher->id)->first();

        $this->assertNotNull($assignment);
        $this->assertEquals('2026/2027', $assignment->academic_year);
        $this->assertEquals($this->year2026->id, $assignment->academic_year_id);
    }

    /**
     * Skenario 2 — Tahun target eksplisit tetap dihormati.
     */
    public function test_explicit_valid_target_academic_year_is_honored_for_subject_assignment(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('teacher-assignments.store-subject'), [
                'teacher_id' => $this->teacher->id,
                'subject_id' => $this->subject->id,
                'classroom_id' => $this->classroom->id,
                'academic_year' => '2027/2028',
                'semester' => 'Ganjil',
                'hours_per_week' => 2,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $assignment = TeacherSubjectAssignment::where('teacher_id', $this->teacher->id)
            ->where('subject_id', $this->subject->id)
            ->first();

        $this->assertNotNull($assignment);
        // Harus menggunakan tahun target eksplisit 2027/2028, bukan ditimpa ke operasional
        $this->assertEquals('2027/2028', $assignment->academic_year);
    }

    public function test_updating_structural_assignment_retains_existing_year_when_not_specified(): void
    {
        $assignment = TeacherStructuralAssignment::create([
            'teacher_id' => $this->teacher->id,
            'structural_position_id' => $this->structuralPosition->id,
            'academic_year' => '2024/2025',
            'sk_number' => 'OLD/SK/2024',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->withSession(['active_academic_year' => '2026/2027'])
            ->post(route('teacher-assignments.store-structural'), [
                'assignment_id' => $assignment->id,
                'teacher_id' => $this->teacher->id,
                'structural_position_id' => $this->structuralPosition->id,
                'sk_number' => 'UPDATED/SK/2024',
                // academic_year tidak dikirim, pembaruan harus mempertahankan tahun rekaman
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $assignment->refresh();
        $this->assertEquals('2024/2025', $assignment->academic_year);
        $this->assertEquals('UPDATED/SK/2024', $assignment->sk_number);
    }

    /**
     * Skenario 3 — Sinkronisasi jadwal tahun berikutnya.
     */
    public function test_timetable_sync_for_next_academic_year_uses_correct_name_and_foreign_key(): void
    {
        $targetYear = '2027/2028';

        // Jalankan sinkronisasi rombel untuk tahun 2027/2028
        $classrooms = TimetableSyncService::syncClassrooms($targetYear);

        $this->assertNotEmpty($classrooms);
        $sampleClass = $classrooms['X TKR 1'] ?? null;
        $this->assertNotNull($sampleClass);
        $this->assertEquals('X TKR 1', $sampleClass->name);
        $this->assertEquals($targetYear, $sampleClass->academic_year);
        $this->assertEquals($this->year2027->id, $sampleClass->academic_year_id);

        // Pastikan rombel tahun operasional 2026/2027 tidak tertimpa/terhapus
        $originalClass = Classroom::where('name', 'X TKR 1')
            ->where('academic_year', '2026/2027')
            ->first();
        $this->assertNotNull($originalClass);
        $this->assertEquals($this->year2026->id, $originalClass->academic_year_id);
    }

    /**
     * Skenario 4 — Tahun target tidak valid ditolak secara terkendali.
     */
    public function test_unregistered_academic_year_in_sync_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Tahun ajaran '1999/2000' tidak terdaftar.");

        TimetableSyncService::syncClassrooms('1999/2000');
    }

    public function test_unregistered_academic_year_in_assignment_request_is_rejected_by_validation(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('teacher-assignments.store-subject'), [
                'teacher_id' => $this->teacher->id,
                'subject_id' => $this->subject->id,
                'classroom_id' => $this->classroom->id,
                'academic_year' => '1999/2000', // Tidak ada di tabel academic_years
                'semester' => 'Ganjil',
                'hours_per_week' => 4,
            ]);

        $response->assertSessionHasErrors('academic_year');
    }

    public function test_unregistered_academic_year_in_schedule_store_is_rejected_by_validation(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('kurikulum.schedules.store'), [
                'classroom_id' => $this->classroom->id,
                'teacher_id' => $this->teacher->id,
                'subject_id' => $this->subject->id,
                'day' => 'Senin',
                'start_period' => 1,
                'end_period' => 2,
                'academic_year' => '1999/2000', // Tidak ada di tabel academic_years
            ]);

        $response->assertSessionHasErrors('academic_year');
    }

    /**
     * Skenario 5 — Sesi arsip tidak mengubah tahun target upload dan sinkronisasi.
     */
    public function test_archive_session_does_not_override_timetable_upload_target(): void
    {
        $upload = TimetableUpload::create([
            'academic_year' => '2027/2028',
            'semester' => 'Ganjil',
            'title' => 'Jadwal 2027/2028',
            'original_filename' => 'jadwal_2027.pdf',
            'file_path' => 'timetables/jadwal_2027.pdf',
            'uploaded_by' => $this->admin->id,
            'is_active' => false,
        ]);

        $this->teacher->update(['short_name' => 'BUDI']);

        $scheduleData = [
            'X TKR 1' => [
                'Senin' => [
                    [
                        'subject' => 'PAI',
                        'teacher' => 'BUDI',
                        'start' => 1,
                        'end' => 2,
                    ],
                ],
            ],
        ];

        // Jalankan proses jadwal dengan sesi tampilan berada di tahun arsip 2024/2025
        session(['active_academic_year' => '2024/2025']);

        $result = TimetableSyncService::processScheduleData($scheduleData, $upload);

        $this->assertGreaterThan(0, $result['total_classes']);

        // Pastikan LessonSchedule dibuat untuk 2027/2028, BUKAN 2024/2025 atau 2026/2027
        $schedule = LessonSchedule::where('timetable_upload_id', $upload->id)->first();
        $this->assertNotNull($schedule);
        $this->assertEquals('2027/2028', $schedule->academic_year);
    }
}
