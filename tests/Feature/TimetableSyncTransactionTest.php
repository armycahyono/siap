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
use App\Models\TimetableItem;
use App\Models\TimetableUpload;
use App\Models\User;
use App\Services\TimetableParserService;
use App\Services\TimetableSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class TimetableSyncTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $teacher;

    protected Classroom $classroom;

    protected Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $adminRole = Role::firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'description' => 'Administrator', 'is_system' => true]
        );

        $teacherRole = Role::firstOrCreate(
            ['slug' => 'guru'],
            ['name' => 'Guru', 'description' => 'Guru Pengampu', 'is_system' => false]
        );

        $this->admin = User::firstOrCreate(
            ['username' => 'admin_sync_test'],
            [
                'name' => 'Admin Sync Test',
                'email' => 'admin_sync@example.com',
                'password' => bcrypt('password'),
                'role_id' => $adminRole->id,
                'is_active' => true,
            ]
        );

        $this->teacher = User::firstOrCreate(
            ['username' => 'budi_sync_test'],
            [
                'name' => 'ILHAM BUDI SANTOSO',
                'short_name' => 'BUDI',
                'email' => 'budi_sync@example.com',
                'password' => bcrypt('password'),
                'role_id' => $teacherRole->id,
                'is_active' => true,
            ]
        );

        $dept = Department::firstOrCreate(
            ['code' => 'TKR'],
            ['name' => 'Teknik Kendaraan Ringan Otomotif']
        );

        $grade = GradeLevel::firstOrCreate(
            ['level' => 'X'],
            ['name' => 'Tingkat X', 'order_index' => 1, 'is_active' => true]
        );

        $this->classroom = Classroom::firstOrCreate(
            ['name' => 'X TKR 1', 'academic_year' => '2026/2027'],
            [
                'grade_level_id' => $grade->id,
                'department_id' => $dept->id,
                'is_active' => true,
            ]
        );

        $this->subject = Subject::firstOrCreate(
            ['code' => 'KONSJUR TKR'],
            [
                'name' => 'Konsentrasi Keahlian TKR',
                'category' => 'Kejuruan',
            ]
        );

        SchoolSetting::firstOrCreate(
            ['id' => 1],
            [
                'school_name' => 'SMK PGRI KRAS',
                'academic_year' => '2026/2027',
                'semester' => 'Ganjil',
                'threshold_a' => 90,
                'threshold_b' => 80,
                'threshold_c' => 70,
            ]
        );
    }

    public function test_process_schedule_data_persists_schedules_and_assignments_atomically_on_success(): void
    {
        $upload = TimetableUpload::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'title' => 'Upload Success Test',
            'original_filename' => 'jadwal.pdf',
            'file_path' => 'timetables/test.pdf',
            'file_size' => 1024,
            'uploaded_by' => $this->admin->id,
            'is_active' => true,
        ]);

        $scheduleData = [
            'X TKR 1' => [
                'Senin' => [
                    [
                        'subject' => 'KONSJUR TKR',
                        'teacher' => 'BUDI',
                        'start' => 1,
                        'end' => 2,
                    ],
                ],
            ],
        ];

        $result = TimetableSyncService::processScheduleData($scheduleData, $upload);

        $this->assertEquals(1, $result['total_classes']);
        $this->assertEquals(1, $result['total_lessons']);
        $this->assertEquals(1, $result['total_assignments']);

        $this->assertDatabaseHas('lesson_schedules', [
            'timetable_upload_id' => $upload->id,
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'day' => 'Senin',
            'start_period' => 1,
            'end_period' => 2,
        ]);

        $this->assertDatabaseHas('teacher_subject_assignments', [
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'hours_per_week' => 2,
        ]);

        $upload->refresh();
        $this->assertEquals(1, $upload->total_classes);
        $this->assertEquals(1, $upload->total_lessons);
    }

    public function test_process_schedule_data_rolls_back_when_exception_occurs_midway(): void
    {
        $oldSchedule = LessonSchedule::create([
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Selasa',
            'start_period' => 3,
            'end_period' => 4,
            'start_time' => '08:20:00',
            'end_time' => '09:40:00',
        ]);

        $oldAssignment = TeacherSubjectAssignment::create([
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'hours_per_week' => 2,
            'notes' => 'Jadwal lama tetap harus utuh',
        ]);

        $upload = TimetableUpload::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'title' => 'Upload Failure Rollback Test',
            'original_filename' => 'jadwal.pdf',
            'file_path' => 'timetables/test.pdf',
            'file_size' => 1024,
            'uploaded_by' => $this->admin->id,
            'total_classes' => 0,
            'total_lessons' => 0,
            'is_active' => false,
        ]);

        $scheduleData = [
            'X TKR 1' => [
                'Senin' => [
                    [
                        'subject' => 'KONSJUR TKR',
                        'teacher' => 'BUDI',
                        'start' => 1,
                        'end' => 2,
                    ],
                ],
            ],
        ];

        // Simulate failure on LessonSchedule creation for this specific upload
        LessonSchedule::creating(function (LessonSchedule $model) use ($upload) {
            if ($model->timetable_upload_id === $upload->id) {
                throw new RuntimeException('Simulasi kegagalan database saat sinkronisasi jadwal.');
            }
        });

        $exceptionCaught = false;
        try {
            TimetableSyncService::processScheduleData($scheduleData, $upload);
        } catch (RuntimeException $e) {
            $exceptionCaught = true;
            $this->assertStringContainsString('Simulasi kegagalan database', $e->getMessage());
        }

        $this->assertTrue($exceptionCaught, 'RuntimeException harus dilemparkan dari dalam transaksi.');

        // Verify Rollback: Old schedule and old assignment are still intact
        $this->assertDatabaseHas('lesson_schedules', [
            'id' => $oldSchedule->id,
            'day' => 'Selasa',
            'start_period' => 3,
        ]);

        $this->assertDatabaseHas('teacher_subject_assignments', [
            'id' => $oldAssignment->id,
            'hours_per_week' => 2,
        ]);

        // Verify Rollback: No new schedule was saved
        $this->assertDatabaseMissing('lesson_schedules', [
            'timetable_upload_id' => $upload->id,
        ]);

        // Verify Rollback: Upload metadata was not updated
        $upload->refresh();
        $this->assertEquals(0, $upload->total_classes);
        $this->assertEquals(0, $upload->total_lessons);
    }

    public function test_apply_translation_to_live_persists_all_or_nothing_on_success(): void
    {
        $oldUpload = TimetableUpload::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'title' => 'Arsip Jadwal Lama',
            'original_filename' => 'old.pdf',
            'file_path' => 'timetables/old.pdf',
            'file_size' => 1024,
            'uploaded_by' => $this->admin->id,
            'is_active' => true,
        ]);

        LessonSchedule::create([
            'timetable_upload_id' => $oldUpload->id,
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Jumat',
            'start_period' => 1,
            'end_period' => 2,
            'start_time' => '07:00:00',
            'end_time' => '08:20:00',
        ]);

        $newUpload = TimetableUpload::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'title' => 'Arsip Jadwal Baru',
            'original_filename' => 'new.pdf',
            'file_path' => 'timetables/new.pdf',
            'file_size' => 1024,
            'uploaded_by' => $this->admin->id,
            'is_active' => false,
        ]);

        TimetableItem::create([
            'timetable_upload_id' => $newUpload->id,
            'class_name' => 'X TKR 1',
            'classroom_id' => $this->classroom->id,
            'day' => 'Senin',
            'start_period' => 1,
            'end_period' => 3,
            'start_time' => '07:00:00',
            'end_time' => '09:00:00',
            'subject_code' => 'KONSJUR TKR',
            'subject_id' => $this->subject->id,
            'teacher_nickname' => 'BUDI',
            'teacher_id' => $this->teacher->id,
            'is_valid' => true,
        ]);

        $result = TimetableSyncService::applyTranslationToLive($newUpload);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['total_classes']);
        $this->assertEquals(1, $result['total_lessons']);
        $this->assertEquals(1, $result['total_assignments']);

        // Verify active status switched
        $this->assertFalse($oldUpload->fresh()->is_active);
        $this->assertTrue($newUpload->fresh()->is_active);

        // Verify new schedule created and old schedule replaced
        $this->assertDatabaseMissing('lesson_schedules', [
            'timetable_upload_id' => $oldUpload->id,
            'day' => 'Jumat',
        ]);

        $this->assertDatabaseHas('lesson_schedules', [
            'timetable_upload_id' => $newUpload->id,
            'day' => 'Senin',
            'start_period' => 1,
            'end_period' => 3,
        ]);

        // Verify assignments updated
        $this->assertDatabaseHas('teacher_subject_assignments', [
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'hours_per_week' => 3,
        ]);
    }

    public function test_apply_translation_to_live_rolls_back_when_exception_occurs_midway(): void
    {
        $oldUpload = TimetableUpload::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'title' => 'Arsip Jadwal Aktif Tetap Aman',
            'original_filename' => 'old_active.pdf',
            'file_path' => 'timetables/old_active.pdf',
            'file_size' => 1024,
            'uploaded_by' => $this->admin->id,
            'is_active' => true,
        ]);

        $oldSchedule = LessonSchedule::create([
            'timetable_upload_id' => $oldUpload->id,
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Kamis',
            'start_period' => 1,
            'end_period' => 2,
            'start_time' => '07:00:00',
            'end_time' => '08:20:00',
        ]);

        $oldAssignment = TeacherSubjectAssignment::create([
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'hours_per_week' => 2,
            'notes' => 'Penugasan lama yang tidak boleh hilang',
        ]);

        $newUpload = TimetableUpload::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'title' => 'Arsip Jadwal Gagal',
            'original_filename' => 'fail.pdf',
            'file_path' => 'timetables/fail.pdf',
            'file_size' => 1024,
            'uploaded_by' => $this->admin->id,
            'is_active' => false,
        ]);

        TimetableItem::create([
            'timetable_upload_id' => $newUpload->id,
            'class_name' => 'X TKR 1',
            'classroom_id' => $this->classroom->id,
            'day' => 'Senin',
            'start_period' => 1,
            'end_period' => 2,
            'start_time' => '07:00:00',
            'end_time' => '08:20:00',
            'subject_code' => 'KONSJUR TKR',
            'subject_id' => $this->subject->id,
            'teacher_nickname' => 'BUDI',
            'teacher_id' => $this->teacher->id,
            'is_valid' => true,
        ]);

        // Simulate failure during TeacherSubjectAssignment creation
        TeacherSubjectAssignment::creating(function (TeacherSubjectAssignment $model) use ($newUpload) {
            if (str_contains($model->notes ?? '', $newUpload->title)) {
                throw new RuntimeException('Simulasi kegagalan saat menyusun ulang TeacherSubjectAssignment.');
            }
        });

        $exceptionCaught = false;
        try {
            TimetableSyncService::applyTranslationToLive($newUpload);
        } catch (RuntimeException $e) {
            $exceptionCaught = true;
            $this->assertStringContainsString('Simulasi kegagalan saat menyusun ulang', $e->getMessage());
        }

        $this->assertTrue($exceptionCaught, 'RuntimeException harus dilemparkan dari applyTranslationToLive.');

        // Verify Rollback: Old schedules and old assignments remain intact!
        $this->assertDatabaseHas('lesson_schedules', [
            'id' => $oldSchedule->id,
            'timetable_upload_id' => $oldUpload->id,
            'day' => 'Kamis',
        ]);

        $this->assertDatabaseHas('teacher_subject_assignments', [
            'id' => $oldAssignment->id,
            'notes' => 'Penugasan lama yang tidak boleh hilang',
        ]);

        // Verify Rollback: No new schedule for new upload exists
        $this->assertDatabaseMissing('lesson_schedules', [
            'timetable_upload_id' => $newUpload->id,
        ]);

        // Verify Rollback: Upload active statuses were NOT switched
        $this->assertTrue($oldUpload->fresh()->is_active, 'Upload lama harus tetap aktif karena proses gagal.');
        $this->assertFalse($newUpload->fresh()->is_active, 'Upload baru harus tetap tidak aktif.');
    }

    public function test_timetable_upload_controller_apply_translation_handles_failure_gracefully_without_500(): void
    {
        $oldUpload = TimetableUpload::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'title' => 'Arsip Jadwal Sebelum Request',
            'original_filename' => 'req_old.pdf',
            'file_path' => 'timetables/req_old.pdf',
            'file_size' => 1024,
            'uploaded_by' => $this->admin->id,
            'is_active' => true,
        ]);

        $oldSchedule = LessonSchedule::create([
            'timetable_upload_id' => $oldUpload->id,
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Rabu',
            'start_period' => 1,
            'end_period' => 2,
            'start_time' => '07:00:00',
            'end_time' => '08:20:00',
        ]);

        $newUpload = TimetableUpload::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'title' => 'Arsip Jadwal Request Error',
            'original_filename' => 'req_fail.pdf',
            'file_path' => 'timetables/req_fail.pdf',
            'file_size' => 1024,
            'uploaded_by' => $this->admin->id,
            'is_active' => false,
        ]);

        TimetableItem::create([
            'timetable_upload_id' => $newUpload->id,
            'class_name' => 'X TKR 1',
            'classroom_id' => $this->classroom->id,
            'day' => 'Senin',
            'start_period' => 1,
            'end_period' => 2,
            'start_time' => '07:00:00',
            'end_time' => '08:20:00',
            'subject_code' => 'KONSJUR TKR',
            'subject_id' => $this->subject->id,
            'teacher_nickname' => 'BUDI',
            'teacher_id' => $this->teacher->id,
            'is_valid' => true,
        ]);

        // Force exception when inserting lesson schedule
        LessonSchedule::creating(function (LessonSchedule $model) use ($newUpload) {
            if ($model->timetable_upload_id === $newUpload->id) {
                throw new RuntimeException('Error database tak terduga.');
            }
        });

        $response = $this->actingAs($this->admin)
            ->from(route('kurikulum.timetables.translation', $newUpload))
            ->post(route('kurikulum.timetables.translation.apply', $newUpload));

        // Should redirect back with error flash instead of 500 error
        $response->assertRedirect(route('kurikulum.timetables.translation', $newUpload));
        $response->assertSessionHas('error');

        // Verify Rollback
        $this->assertDatabaseHas('lesson_schedules', [
            'id' => $oldSchedule->id,
            'day' => 'Rabu',
        ]);
        $this->assertTrue($oldUpload->fresh()->is_active);
        $this->assertFalse($newUpload->fresh()->is_active);
    }

    public function test_lesson_schedule_controller_upload_preserves_old_state_when_sync_fails(): void
    {
        Storage::fake('public');

        $oldUpload = TimetableUpload::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'title' => 'Arsip Jadwal Aktif Sebelum Upload',
            'original_filename' => 'old_upload.pdf',
            'file_path' => 'timetables/old_upload.pdf',
            'file_size' => 1024,
            'uploaded_by' => $this->admin->id,
            'is_active' => true,
        ]);

        $oldSchedule = LessonSchedule::create([
            'timetable_upload_id' => $oldUpload->id,
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Senin',
            'start_period' => 1,
            'end_period' => 2,
            'start_time' => '07:00:00',
            'end_time' => '08:20:00',
        ]);

        // Force exception during processScheduleData by mocking failure in LessonSchedule::creating
        $this->mock(TimetableParserService::class, function ($mock) {
            $mock->shouldReceive('parsePdf')->andReturn([
                'X TKR 1' => [
                    'Kamis' => [
                        [
                            'subject' => 'KONSJUR TKR',
                            'teacher' => 'BUDI',
                            'start' => 1,
                            'end' => 2,
                        ],
                    ],
                ],
            ]);
        });

        LessonSchedule::creating(function (LessonSchedule $model) {
            if ($model->day === 'Kamis') {
                throw new RuntimeException('Crash saat import jadwal baru via upload PDF.');
            }
        });

        // Fake PDF file
        $file = UploadedFile::fake()->create('jadwal_baru.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->admin)
            ->from(route('kurikulum.schedules.index'))
            ->post(route('kurikulum.schedules.upload'), [
                'pdf_file' => $file,
                'title' => 'Upload Jadwal Gagal',
            ]);

        // Controller catches error and redirects back with warning
        $response->assertRedirect(route('kurikulum.schedules.index'));
        $response->assertSessionHas('warning');

        // Verify old schedule and old upload remain intact and active
        $this->assertDatabaseHas('lesson_schedules', [
            'id' => $oldSchedule->id,
            'day' => 'Senin',
        ]);
        $this->assertTrue($oldUpload->fresh()->is_active);

        // Verify newly uploaded archive is deactivated
        $failedUpload = TimetableUpload::where('title', 'Upload Jadwal Gagal')->first();
        $this->assertNotNull($failedUpload);
        $this->assertFalse($failedUpload->is_active);
    }
}
