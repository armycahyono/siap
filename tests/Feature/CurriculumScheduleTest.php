<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\LessonSchedule;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\TimetableUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CurriculumScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Classroom $classroom;

    protected Subject $subject;

    protected User $teacher;

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

        $dept = Department::create([
            'code' => 'TKR',
            'name' => 'Teknik Kendaraan Ringan Otomotif',
        ]);

        $grade = GradeLevel::create([
            'level' => 'X',
            'name' => 'Tingkat X',
            'order_index' => 1,
            'is_active' => true,
        ]);

        $this->classroom = Classroom::create([
            'name' => 'X TKR 1',
            'grade_level_id' => $grade->id,
            'department_id' => $dept->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $this->subject = Subject::create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'category' => 'Umum',
        ]);

        SchoolSetting::create([
            'school_name' => 'SMK PGRI KRAS',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'threshold_a' => 90,
            'threshold_b' => 80,
            'threshold_c' => 70,
        ]);
    }

    public function test_can_view_lesson_schedules_index_class_mode(): void
    {
        $response = $this->actingAs($this->admin)->get(route('kurikulum.schedules.index', [
            'view' => 'class',
            'classroom_id' => $this->classroom->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Jadwal Pelajaran');
        $response->assertSee('X TKR 1');
    }

    public function test_can_view_lesson_schedules_index_teacher_mode(): void
    {
        $response = $this->actingAs($this->admin)->get(route('kurikulum.schedules.index', [
            'view' => 'teacher',
            'teacher_id' => $this->teacher->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Jadwal Mengajar');
        $response->assertSee($this->teacher->name);
    }

    public function test_can_store_manual_lesson_schedule(): void
    {
        $response = $this->actingAs($this->admin)->post(route('kurikulum.schedules.store'), [
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'day' => 'Senin',
            'start_period' => 1,
            'end_period' => 3,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('lesson_schedules', [
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'day' => 'Senin',
            'start_period' => 1,
            'end_period' => 3,
        ]);

        $this->assertDatabaseHas('teacher_subject_assignments', [
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'hours_per_week' => 3,
        ]);
    }

    public function test_colliding_manual_lesson_schedule_is_rejected(): void
    {
        LessonSchedule::create([
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Senin',
            'start_period' => 1,
            'end_period' => 3,
        ]);

        $response = $this->actingAs($this->admin)->post(route('kurikulum.schedules.store'), [
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'day' => 'Senin',
            'start_period' => 2,
            'end_period' => 4,
        ]);

        $response->assertSessionHas('error');
    }

    public function test_can_delete_lesson_schedule(): void
    {
        $schedule = LessonSchedule::create([
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Senin',
            'start_period' => 1,
            'end_period' => 2,
        ]);

        TeacherSubjectAssignment::create([
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'hours_per_week' => 2,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('kurikulum.schedules.destroy', $schedule));

        $response->assertRedirect();
        $this->assertDatabaseMissing('lesson_schedules', ['id' => $schedule->id]);
        $this->assertDatabaseMissing('teacher_subject_assignments', [
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
        ]);
    }

    public function test_can_view_timetables_archive_index(): void
    {
        TimetableUpload::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'title' => 'Jadwal Gasal 2026',
            'original_filename' => 'jadwal.pdf',
            'file_path' => 'timetables/jadwal.pdf',
            'file_size' => 1024,
            'uploaded_by' => $this->admin->id,
            'total_classes' => 17,
            'total_lessons' => 325,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('kurikulum.timetables.index'));

        $response->assertStatus(200);
        $response->assertSee('Arsip Dokumen Jadwal PDF');
        $response->assertSee('Jadwal Gasal 2026');
    }

    public function test_can_preview_timetable_pdf(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('timetables/jadwal.pdf', '%PDF-1.4 dummy pdf');

        $upload = TimetableUpload::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'title' => 'Jadwal Gasal 2026',
            'original_filename' => 'jadwal.pdf',
            'file_path' => 'timetables/jadwal.pdf',
            'file_size' => 1024,
            'uploaded_by' => $this->admin->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('kurikulum.timetables.preview', $upload));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_can_access_print_schedule_for_all_classes(): void
    {
        LessonSchedule::create([
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Senin',
            'start_period' => 1,
            'end_period' => 2,
            'start_time' => '07:00',
            'end_time' => '08:30',
        ]);

        $response = $this->actingAs($this->admin)->get(route('kurikulum.schedules.print', [
            'view' => 'class',
            'target' => 'all',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Pratinjau Cetak Jadwal Pelajaran');
        $response->assertSee('JADWAL PELAJARAN KELAS: '.$this->classroom->name);
    }

    public function test_can_access_print_schedule_for_specific_class(): void
    {
        $this->classroom->update(['homeroom_teacher_id' => $this->teacher->id]);

        LessonSchedule::create([
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Senin',
            'start_period' => 1,
            'end_period' => 2,
            'start_time' => '07:00',
            'end_time' => '08:30',
        ]);

        $response = $this->actingAs($this->admin)->get(route('kurikulum.schedules.print', [
            'view' => 'class',
            'target' => 'specific',
            'classroom_id' => $this->classroom->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('JADWAL PELAJARAN KELAS: '.$this->classroom->name);
        $response->assertSee('Tahun Pelajaran:');
        $response->assertDontSee('Semester:');
        // Wali kelas uses full name
        $response->assertSee('Wali Kelas:</strong> '.$this->teacher->name, false);
        // Inside timetable grid, teacher uses formatted short name
        $response->assertSee($this->teacher->formatted_short_name);
    }

    public function test_can_access_print_schedule_for_all_teachers(): void
    {
        $response = $this->actingAs($this->admin)->get(route('kurikulum.schedules.print', [
            'view' => 'teacher',
            'target' => 'all',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Pratinjau Cetak Jadwal Pelajaran');
        $response->assertSee('Jadwal Guru');
    }

    public function test_can_access_print_schedule_for_specific_teacher(): void
    {
        LessonSchedule::create([
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Senin',
            'start_period' => 1,
            'end_period' => 2,
            'start_time' => '07:00',
            'end_time' => '08:30',
        ]);

        $response = $this->actingAs($this->admin)->get(route('kurikulum.schedules.print', [
            'view' => 'teacher',
            'target' => 'specific',
            'teacher_id' => $this->teacher->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('JADWAL MENGAJAR GURU: '.$this->teacher->name);
    }

    public function test_teacher_mode_only_shows_teachers_with_teaching_hours(): void
    {
        $teacherWithoutHours = User::create([
            'name' => 'Guru Tanpa Jam',
            'username' => 'tanpajam',
            'email' => 'tanpajam@test.com',
            'password' => bcrypt('password'),
            'role_id' => $this->teacher->role_id,
            'is_active' => true,
        ]);

        LessonSchedule::create([
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Senin',
            'start_period' => 1,
            'end_period' => 2,
            'start_time' => '07:00',
            'end_time' => '08:30',
        ]);

        $response = $this->actingAs($this->admin)->get(route('kurikulum.schedules.index', [
            'view' => 'teacher',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
        ]));

        $response->assertStatus(200);
        $response->assertSee('id="teacher-'.$this->teacher->id.'"', false);
        $response->assertDontSee('id="teacher-'.$teacherWithoutHours->id.'"', false);

        $viewTeachers = $response->viewData('teachers');
        $this->assertTrue($viewTeachers->contains('id', $this->teacher->id));
        $this->assertFalse($viewTeachers->contains('id', $teacherWithoutHours->id));
        $this->assertArrayNotHasKey($teacherWithoutHours->id, $response->viewData('allGrids'));
    }

    public function test_schedules_are_loaded_by_academic_year_regardless_of_semester(): void
    {
        LessonSchedule::create([
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'academic_year' => '2026/2027',
            'semester' => 'Genap',
            'day' => 'Senin',
            'start_period' => 3,
            'end_period' => 4,
            'start_time' => '08:30',
            'end_time' => '10:00',
        ]);

        $response = $this->actingAs($this->admin)->get(route('kurikulum.schedules.index', [
            'view' => 'class',
            'classroom_id' => $this->classroom->id,
            'academic_year' => '2026/2027',
        ]));

        $response->assertStatus(200);
        $grid = $response->viewData('scheduleGrid');
        $this->assertNotEmpty($grid['Senin'][3]);
        $this->assertEquals($this->subject->code, $grid['Senin'][3]['title']);
    }

    public function test_can_customize_penetapan_date_and_city_when_printing_schedule(): void
    {
        LessonSchedule::create([
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Senin',
            'start_period' => 1,
            'end_period' => 2,
            'start_time' => '07:00',
            'end_time' => '08:30',
        ]);

        $response = $this->actingAs($this->admin)->get(route('kurikulum.schedules.print', [
            'view' => 'class',
            'target' => 'specific',
            'classroom_id' => $this->classroom->id,
            'penetapan_date' => '2026-07-13',
            'penetapan_city' => 'Kras-Kediri',
        ]));

        $response->assertStatus(200);
        $response->assertSee('13 Juli 2026');
        $response->assertSee('Kras-Kediri');
    }

    public function test_teacher_can_only_see_relevant_classrooms_in_class_schedule_index(): void
    {
        // Classroom 1 is taught by $this->teacher
        LessonSchedule::create([
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Senin',
            'start_period' => 1,
            'end_period' => 2,
        ]);

        // Classroom 2 is an unrelated classroom
        $otherClassroom = Classroom::create([
            'name' => 'XII TBSM 2',
            'grade_level_id' => $this->classroom->grade_level_id,
            'department_id' => $this->classroom->department_id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->teacher)->get(route('kurikulum.schedules.index', [
            'view' => 'class',
        ]));

        $response->assertStatus(200);
        // Sees relevant class
        $response->assertSee($this->classroom->name);
        // Does not see unrelated class
        $response->assertDontSee('XII TBSM 2');
    }

    public function test_teacher_cannot_view_unrelated_classroom_via_query_param(): void
    {
        $otherClassroom = Classroom::create([
            'name' => 'XII TBSM 9',
            'grade_level_id' => $this->classroom->grade_level_id,
            'department_id' => $this->classroom->department_id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->teacher)->get(route('kurikulum.schedules.index', [
            'view' => 'class',
            'classroom_id' => $otherClassroom->id,
        ]));

        $response->assertStatus(200);
        $this->assertNull($response->viewData('selectedClassroom'));
        $response->assertDontSee('Jadwal Pelajaran Kelas: XII TBSM 9');
    }

    public function test_teacher_can_only_see_own_schedule_in_teacher_schedule_index(): void
    {
        $otherTeacher = User::create([
            'name' => 'Guru Lain S.Pd',
            'username' => 'gurulain',
            'email' => 'gurulain@test.com',
            'password' => bcrypt('password'),
            'role_id' => $this->teacher->role_id,
            'is_active' => true,
        ]);

        LessonSchedule::create([
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $otherTeacher->id,
            'subject_id' => $this->subject->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Selasa',
            'start_period' => 1,
            'end_period' => 2,
        ]);

        // Attempting to view teacher mode with other teacher ID
        $response = $this->actingAs($this->teacher)->get(route('kurikulum.schedules.index', [
            'view' => 'teacher',
            'teacher_id' => $otherTeacher->id,
        ]));

        $response->assertStatus(200);
        // Must show logged-in teacher only
        $this->assertEquals($this->teacher->id, $response->viewData('selectedTeacher')->id);
        $response->assertSee($this->teacher->name);
        $response->assertDontSee('Guru Lain S.Pd');
    }

    public function test_teacher_can_only_print_relevant_classrooms_and_own_schedule(): void
    {
        LessonSchedule::create([
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Senin',
            'start_period' => 1,
            'end_period' => 2,
        ]);

        $otherClassroom = Classroom::create([
            'name' => 'XII RPL 3',
            'grade_level_id' => $this->classroom->grade_level_id,
            'department_id' => $this->classroom->department_id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        // 1. Teacher prints class target=all -> only sees relevant classroom
        $resAll = $this->actingAs($this->teacher)->get(route('kurikulum.schedules.print', [
            'view' => 'class',
            'target' => 'all',
        ]));
        $resAll->assertStatus(200);
        $resAll->assertSee($this->classroom->name);
        $resAll->assertDontSee('XII RPL 3');

        // 2. Teacher tries to print specific unauthorized class -> 403
        $resUnauthorized = $this->actingAs($this->teacher)->get(route('kurikulum.schedules.print', [
            'view' => 'class',
            'target' => 'specific',
            'classroom_id' => $otherClassroom->id,
        ]));
        $resUnauthorized->assertStatus(403);

        // 3. Teacher prints teacher view -> only own sheet
        $resTeacher = $this->actingAs($this->teacher)->get(route('kurikulum.schedules.print', [
            'view' => 'teacher',
            'target' => 'all',
        ]));
        $resTeacher->assertStatus(200);
        $sheets = $resTeacher->viewData('sheets');
        $this->assertCount(1, $sheets);
        $this->assertEquals($this->teacher->id, $sheets[0]['entity']->id);
    }

    public function test_teacher_cannot_mutate_schedules(): void
    {
        $responseStore = $this->actingAs($this->teacher)->post(route('kurikulum.schedules.store'), [
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'day' => 'Kamis',
            'start_period' => 1,
            'end_period' => 2,
        ]);
        $responseStore->assertStatus(403);

        $schedule = LessonSchedule::create([
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Senin',
            'start_period' => 1,
            'end_period' => 2,
        ]);

        $responseDestroy = $this->actingAs($this->teacher)->delete(route('kurikulum.schedules.destroy', $schedule));
        $responseDestroy->assertStatus(403);
    }
}
