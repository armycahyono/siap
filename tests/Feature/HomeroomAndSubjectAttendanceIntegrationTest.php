<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Classroom;
use App\Models\CurriculumEvent;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\PiketStudentPermit;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeroomAndSubjectAttendanceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected Classroom $classroom;

    protected Student $student;

    protected Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        SchoolSetting::create([
            'school_name' => 'SMK PGRI KRAS',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
        ]);

        $roleTeacher = Role::create([
            'name' => 'Guru',
            'slug' => 'guru',
        ]);

        $this->teacher = User::factory()->create([
            'username' => 'guru_budi',
            'role_id' => $roleTeacher->id,
            'name' => 'Budi Santoso, S.Pd',
        ]);

        $gradeLevel = GradeLevel::create(['name' => 'X', 'level' => 10]);
        $department = Department::create(['name' => 'TKJ', 'code' => 'TKJ']);

        $this->classroom = Classroom::create([
            'name' => 'X TKJ 1',
            'academic_year' => '2026/2027',
            'grade_level_id' => $gradeLevel->id,
            'department_id' => $department->id,
            'homeroom_teacher_id' => $this->teacher->id,
            'is_active' => true,
        ]);

        $this->student = Student::create([
            'name' => 'Ahmad Dani',
            'classroom_id' => $this->classroom->id,
            'gender' => 'L',
            'is_active' => true,
        ]);

        $this->subject = Subject::create([
            'name' => 'Matematika',
            'code' => 'MTK',
        ]);
    }

    public function test_homeroom_teacher_can_report_student_permit(): void
    {
        $this->actingAs($this->teacher);

        $response = $this->post(route('attendance.homeroom-permits.store'), [
            'classroom_id' => $this->classroom->id,
            'student_id' => $this->student->id,
            'permit_category' => 'sakit',
            'permit_date' => now()->toDateString(),
            'reason' => 'Demam tinggi dan flu berat',
        ]);

        $response->assertRedirect(route('attendance.homeroom-permits.index'));

        $this->assertDatabaseHas('piket_student_permits', [
            'student_id' => $this->student->id,
            'classroom_id' => $this->classroom->id,
            'permit_type' => 'izin_tidak_masuk',
            'permit_category' => 'sakit',
            'reporter_type' => 'wali_kelas',
            'reason' => 'Demam tinggi dan flu berat',
        ]);
    }

    public function test_subject_attendance_sheet_displays_piket_permit_and_machine_scan(): void
    {
        $this->actingAs($this->teacher);

        // Buat izin dari meja piket / wali kelas
        PiketStudentPermit::create([
            'permit_number' => 'IZN-20261007-001',
            'permit_date' => now()->toDateString(),
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'classroom_id' => $this->classroom->id,
            'permit_type' => 'izin_tidak_masuk',
            'permit_category' => 'sakit',
            'reason' => 'Sakit demam',
            'reporter_type' => 'wali_kelas',
            'given_by' => $this->teacher->id,
            'status' => 'diberikan',
        ]);

        // Buat scan mesin absensi
        AttendanceLog::create([
            'device_sn' => 'SN-DEV-01',
            'pin' => '1001',
            'user_type' => 'student',
            'student_id' => $this->student->id,
            'name' => $this->student->name,
            'scanned_at' => Carbon::parse(now()->toDateString().' 07:15:00'),
            'status' => '0',
            'verify_type' => 'face',
        ]);

        $response = $this->get(route('attendance.subject.take', [
            'date' => now()->toDateString(),
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->subject->id,
            'start_period' => 1,
            'end_period' => 2,
        ]));

        $response->assertOk();
        $response->assertSee($this->student->name);
        $response->assertSee('Sakit (Tidak Masuk)');
        $response->assertSee('Wali Kelas');
        $response->assertSee('Tap: 07:15');
    }

    public function test_curriculum_event_learning_impact_affects_subject_attendance(): void
    {
        $this->actingAs($this->teacher);

        // Buat kegiatan PKL di kalender yang berdampak pada tingkat 10 (atau kelas X TKJ 1)
        CurriculumEvent::create([
            'academic_year' => '2026/2027',
            'title' => 'Praktik Kerja Lapangan (PKL) Industri',
            'category' => 'kegiatan_kurikulum',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'is_all_day' => true,
            'target_audience' => 'tingkat',
            'target_grade_levels' => [10],
            'learning_impact' => 'bebas_kbm_kegiatan',
            'teacher_instructions' => 'Siswa melaksanakan PKL di bengkel/industri mitra',
        ]);

        $response = $this->get(route('attendance.subject.take', [
            'date' => now()->toDateString(),
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->subject->id,
            'start_period' => 1,
            'end_period' => 2,
        ]));

        $response->assertOk();
        $response->assertSee('Praktik Kerja Lapangan (PKL) Industri');
        $response->assertSee('Bebas KBM');
        $response->assertSee('Tandai Semua Dispen');
    }
}
