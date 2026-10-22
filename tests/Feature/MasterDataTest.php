<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\SchoolSetting;
use App\Models\StructuralPosition;
use App\Models\Subject;
use App\Models\TeacherStructuralAssignment;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_waka_can_view_and_create_department(): void
    {
        $waka = User::where('username', 'waka')->first();

        $response = $this->actingAs($waka)->get(route('departments.index'));
        $response->assertStatus(200);
        $response->assertSee('Data Pokok: Jurusan');

        $response = $this->actingAs($waka)->post(route('departments.store'), [
            'code' => 'DKV',
            'name' => 'Desain Komunikasi Visual',
            'description' => 'Program Keahlian Desain Komunikasi Visual',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('departments.index'));
        $this->assertDatabaseHas('departments', ['code' => 'DKV']);
    }

    public function test_waka_can_view_and_create_subject(): void
    {
        $waka = User::where('username', 'waka')->first();

        $response = $this->actingAs($waka)->get(route('subjects.index'));
        $response->assertStatus(200);
        $response->assertSee('Data Pokok: Mata Pelajaran');

        $response = $this->actingAs($waka)->post(route('subjects.store'), [
            'code' => 'KIM',
            'name' => 'Kimia Terapan Kejuruan',
            'category' => 'Umum',
            'order_index' => 99,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('subjects.index'));
        $this->assertDatabaseHas('subjects', ['code' => 'KIM']);
    }

    public function test_waka_can_view_and_create_classroom(): void
    {
        $waka = User::where('username', 'waka')->first();
        $grade = GradeLevel::first();
        $dept = Department::first();

        $response = $this->actingAs($waka)->get(route('classrooms.index'));
        $response->assertStatus(200);
        $response->assertSee('Data Pokok: Tingkat');

        $response = $this->actingAs($waka)->post(route('classrooms.store'), [
            'name' => 'X TKR 3',
            'grade_level_id' => $grade->id,
            'department_id' => $dept->id,
            'academic_year' => '2025/2026',
        ]);

        $response->assertRedirect(route('classrooms.index', ['academic_year' => '2025/2026']));
        $this->assertDatabaseHas('classrooms', ['name' => 'X TKR 3']);
    }

    public function test_waka_can_assign_homeroom_teacher(): void
    {
        $waka = User::where('username', 'waka')->first();
        $classroom = Classroom::first();
        $teacher = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first();

        $response = $this->actingAs($waka)->post(route('teacher-assignments.update-homeroom', $classroom), [
            'homeroom_teacher_id' => $teacher->id,
        ]);

        $response->assertRedirect(route('teacher-assignments.index', ['tab' => 'walikelas', 'academic_year' => $classroom->academic_year]));
        $this->assertEquals($teacher->id, $classroom->fresh()->homeroom_teacher_id);
    }

    public function test_one_teacher_can_be_assigned_multiple_subjects(): void
    {
        $waka = User::where('username', 'waka')->first();
        $teacher = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first();
        $subject1 = Subject::first();
        $subject2 = Subject::skip(1)->first();
        $classroom = Classroom::first();

        // Assign subject 1
        $response1 = $this->actingAs($waka)->post(route('teacher-assignments.store-subject'), [
            'teacher_id' => $teacher->id,
            'subject_id' => $subject1->id,
            'classroom_id' => $classroom->id,
            'academic_year' => '2025/2026',
            'semester' => 'Semua',
            'hours_per_week' => 4,
            'notes' => 'Tugas Mapel 1',
        ]);
        $response1->assertRedirect();

        // Assign subject 2 to SAME teacher
        $response2 = $this->actingAs($waka)->post(route('teacher-assignments.store-subject'), [
            'teacher_id' => $teacher->id,
            'subject_id' => $subject2->id,
            'classroom_id' => $classroom->id,
            'academic_year' => '2025/2026',
            'semester' => 'Semua',
            'hours_per_week' => 3,
            'notes' => 'Tugas Mapel 2',
        ]);
        $response2->assertRedirect();

        // Verify teacher has at least 2 distinct subjects
        $assignedSubjects = TeacherSubjectAssignment::where('teacher_id', $teacher->id)->pluck('subject_id')->unique();
        $this->assertGreaterThanOrEqual(2, $assignedSubjects->count());
    }

    public function test_mapel_tab_displays_all_teaching_teachers_with_classes_and_detail_modal(): void
    {
        $waka = User::where('username', 'waka')->first();
        $teacher = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first();
        $subject = Subject::first();
        $classroom = Classroom::first();

        TeacherSubjectAssignment::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'hours_per_week' => 4,
            'semester' => 'Ganjil',
            'academic_year' => '2026/2027',
        ]);

        $response = $this->actingAs($waka)->get(route('teacher-assignments.index', ['tab' => 'mapel']));

        $response->assertStatus(200);
        $response->assertSee('Rekap Beban Mengajar Guru');
        $response->assertSee($teacher->name);
        $response->assertSee($classroom->name);
        $response->assertSee('Lihat Detail Penugasan');
        $response->assertSee("modalDetailTeacher_{$teacher->id}");
    }

    public function test_waka_can_view_and_assign_and_remove_supervisor(): void
    {
        $waka = User::where('username', 'waka')->first();
        $teacher = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first();

        // 1. View supervisor tab
        $response = $this->actingAs($waka)->get(route('teacher-assignments.index', ['tab' => 'supervisor']));
        $response->assertStatus(200);
        $response->assertSee('Tim Supervisor Akademik');

        // 2. Assign teacher as supervisor
        $response = $this->actingAs($waka)->post(route('teacher-assignments.assign-supervisor'), [
            'teacher_id' => $teacher->id,
            'position_title' => 'Koordinator Supervisor Senior',
        ]);

        $response->assertRedirect(route('teacher-assignments.index', ['tab' => 'supervisor', 'academic_year' => '2026/2027']));
        $teacher->refresh();
        $this->assertEquals('supervisor', $teacher->role->slug);
        $this->assertEquals('Koordinator Supervisor Senior', $teacher->position);

        // 3. Remove supervisor role
        $response = $this->actingAs($waka)->delete(route('teacher-assignments.remove-supervisor', $teacher));
        $response->assertRedirect(route('teacher-assignments.index', ['tab' => 'supervisor', 'academic_year' => '2026/2027']));
        $teacher->refresh();
        $this->assertEquals('guru', $teacher->role->slug);
    }

    public function test_supervisor_assignment_is_scoped_by_academic_year(): void
    {
        $waka = User::where('username', 'waka')->first();
        $teachers = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->take(2)->get();
        $teacherA = $teachers[0];
        $teacherB = $teachers[1];

        // 1. Assign Teacher A to 2025/2026
        $this->actingAs($waka)->post(route('teacher-assignments.assign-supervisor'), [
            'teacher_id' => $teacherA->id,
            'academic_year' => '2025/2026',
            'position_title' => 'Supervisor Tim 25/26',
        ]);

        // 2. Assign Teacher B to 2026/2027
        $this->actingAs($waka)->post(route('teacher-assignments.assign-supervisor'), [
            'teacher_id' => $teacherB->id,
            'academic_year' => '2026/2027',
            'position_title' => 'Supervisor Tim 26/27',
        ]);

        // 3. View 2025/2026 -> Teacher A is in supervisors, Teacher B is NOT
        $response25 = $this->actingAs($waka)->get(route('teacher-assignments.index', [
            'tab' => 'supervisor',
            'academic_year' => '2025/2026',
        ]));
        $response25->assertStatus(200);
        $supervisors25 = $response25->viewData('supervisors');
        $this->assertTrue($supervisors25->contains('id', $teacherA->id));
        $this->assertFalse($supervisors25->contains('id', $teacherB->id));

        // 4. View 2026/2027 -> Teacher B is in supervisors, Teacher A is NOT
        $response26 = $this->actingAs($waka)->get(route('teacher-assignments.index', [
            'tab' => 'supervisor',
            'academic_year' => '2026/2027',
        ]));
        $response26->assertStatus(200);
        $supervisors26 = $response26->viewData('supervisors');
        $this->assertTrue($supervisors26->contains('id', $teacherB->id));
        $this->assertFalse($supervisors26->contains('id', $teacherA->id));

        // 5. Remove Teacher A from 2025/2026
        $this->actingAs($waka)->delete(route('teacher-assignments.remove-supervisor', [
            'user' => $teacherA->id,
            'academic_year' => '2025/2026',
        ]));

        $this->assertDatabaseMissing('teacher_supervisor_assignments', [
            'teacher_id' => $teacherA->id,
            'academic_year' => '2025/2026',
        ]);
    }

    public function test_waka_can_manage_school_profile_and_rubrics_predicates(): void
    {
        $waka = User::where('username', 'waka')->first();

        // 1. View settings page (Profil Sekolah)
        $response = $this->actingAs($waka)->get(route('settings.index'));
        $response->assertStatus(200);
        $response->assertSee('1. Identitas Sekolah');
        $response->assertSee('3. Akreditasi Sekolah');
        $response->assertDontSee('Pimpinan & Manajemen Sekolah');

        // 2. Update profile
        $response = $this->actingAs($waka)->post(route('settings.update'), [
            'school_name' => 'SMK PGRI KRAS KEDIRI',
            'school_npsn' => '20539999',
            'vision' => 'Unggul dalam Prestasi dan Budi Pekerti.',
        ]);

        $response->assertRedirect();
        $setting = SchoolSetting::getSetting();
        $this->assertEquals('SMK PGRI KRAS KEDIRI', $setting->school_name);
        $this->assertEquals('20539999', $setting->school_npsn);

        // 3. Update predicate thresholds in Rubrics
        $response = $this->actingAs($waka)->post(route('rubrics.update-predicates'), [
            'grade_a_min' => 88,
            'grade_b_min' => 78,
            'grade_c_min' => 65,
        ]);
        $response->assertRedirect();
        $setting->refresh();
        $this->assertEquals(88, $setting->grade_a_min);
        $this->assertEquals(78, $setting->grade_b_min);
        $this->assertEquals(65, $setting->grade_c_min);
    }

    public function test_waka_can_view_and_manage_structural_positions(): void
    {
        $waka = User::where('username', 'waka')->first();

        // 1. View index
        $response = $this->actingAs($waka)->get(route('structural-positions.index'));
        $response->assertStatus(200);
        $response->assertSee('Data Pokok: Jabatan Struktural');
        $response->assertSee('Kepala Sekolah');
        $response->assertSee('Waka Kurikulum');
        $response->assertSee('Kaprogli TKR');
        $response->assertSee('Ka. TU');

        // 2. Create new position
        $response = $this->actingAs($waka)->post(route('structural-positions.store'), [
            'name' => 'Koordinator Lab Multimedia',
            'code' => 'KOOR_LAB_MM',
            'category' => 'Kepala Unit & Fasilitas',
            'order' => 14,
            'is_active' => 1,
        ]);
        $response->assertRedirect(route('structural-positions.index'));
        $this->assertDatabaseHas('structural_positions', ['code' => 'KOOR_LAB_MM']);
    }

    public function test_waka_can_assign_structural_position(): void
    {
        $waka = User::where('username', 'waka')->first();
        $teacher = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first();
        $position = StructuralPosition::where('code', 'KAPRO_TKJ')->first();

        // 1. View assignment tab
        $response = $this->actingAs($waka)->get(route('teacher-assignments.index', ['tab' => 'struktural']));
        $response->assertStatus(200);
        $response->assertSee('Penugasan Struktural');

        // 2. Assign teacher to structural position
        $response = $this->actingAs($waka)->post(route('teacher-assignments.store-structural'), [
            'teacher_id' => $teacher->id,
            'structural_position_id' => $position->id,
            'academic_year' => '2026/2027',
            'sk_number' => '421.5/028/SMK-PK/2026',
            'notes' => 'Kepala Program Keahlian TKJ',
        ]);
        $response->assertRedirect(route('teacher-assignments.index', ['tab' => 'struktural', 'academic_year' => '2026/2027']));
        $this->assertDatabaseHas('teacher_structural_assignments', [
            'teacher_id' => $teacher->id,
            'structural_position_id' => $position->id,
            'sk_number' => '421.5/028/SMK-PK/2026',
        ]);

        // 3. Delete assignment
        $assignment = TeacherStructuralAssignment::where('teacher_id', $teacher->id)->first();
        $response = $this->actingAs($waka)->delete(route('teacher-assignments.destroy-structural', $assignment));
        $response->assertRedirect(route('teacher-assignments.index', ['tab' => 'struktural', 'academic_year' => '2026/2027']));
        $this->assertDatabaseMissing('teacher_structural_assignments', ['id' => $assignment->id]);
    }

    public function test_school_settings_can_detect_and_sync_structural_officials(): void
    {
        $waka = User::where('username', 'waka')->first();
        $guru = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first();
        $ksPos = StructuralPosition::where('code', 'KS')->first();

        // 1. Assign teacher as Kepala Sekolah in structural positions
        TeacherStructuralAssignment::create([
            'teacher_id' => $guru->id,
            'structural_position_id' => $ksPos->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        // 2. Verify SchoolSetting retrieves official from jabatan struktural
        $setting = SchoolSetting::getSetting();
        $principalOfficial = $setting->getPrincipalOfficial();
        $this->assertNotNull($principalOfficial);
        $this->assertEquals($guru->id, $principalOfficial->teacher_id);

        // 3. View structural positions index and verify Tanda Tangan column exists
        $response = $this->actingAs($waka)->get(route('structural-positions.index'));
        $response->assertStatus(200);
        $response->assertSee('Tanda Tangan');

        // 4. Upload signature image to structural position
        Storage::fake('public');
        $file = UploadedFile::fake()->image('ttd_ks.png');

        $response = $this->actingAs($waka)->put(route('structural-positions.update', $ksPos), [
            'name' => $ksPos->name,
            'code' => $ksPos->code,
            'signature_image' => $file,
        ]);

        $response->assertRedirect(route('structural-positions.index'));
        $ksPos->refresh();
        $this->assertNotNull($ksPos->signature_image_path);
        Storage::disk('public')->assertExists($ksPos->signature_image_path);
    }

    public function test_waka_can_view_teachers_list_without_errors(): void
    {
        $waka = User::where('username', 'waka')->first();

        $response = $this->actingAs($waka)->get(route('teachers.index'));
        $response->assertStatus(200);
        $response->assertSee('Manajemen Data Guru');
    }

    public function test_waka_can_search_teachers_list(): void
    {
        $waka = User::where('username', 'waka')->first();

        $response = $this->actingAs($waka)->get(route('teachers.index', ['search' => 'Sukarni']));
        $response->assertStatus(200);
        $response->assertSee('Sukarni');
    }

    public function test_waka_can_download_homeroom_template(): void
    {
        $waka = User::where('username', 'waka')->first();

        $response = $this->actingAs($waka)->get(route('teacher-assignments.template-homeroom', [
            'academic_year' => '2026/2027',
        ]));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('template_wali_kelas_2026-2027.csv', $response->headers->get('content-disposition'));
    }

    public function test_waka_can_import_homeroom_data_via_csv(): void
    {
        $waka = User::where('username', 'waka')->first();
        $guru = User::where('nigs_fix', '41709')->first(); // Indra Yustiawan
        $classroom = Classroom::where('name', 'X TKR 1')->first();

        // Reset homeroom first
        $classroom->update(['homeroom_teacher_id' => null]);

        $csvContent = "\xEF\xBB\xBFNama Kelas,Nama Wali Kelas,NIGS\n";
        $csvContent .= "X TKR 1,\"Indra Yustiawan, S.Pd\",41709\n";

        $file = UploadedFile::fake()->createWithContent('wali_kelas.csv', $csvContent);

        $response = $this->actingAs($waka)->post(route('teacher-assignments.import-homeroom'), [
            'file' => $file,
            'academic_year' => '2026/2027',
        ]);

        $response->assertRedirect(route('teacher-assignments.index', ['tab' => 'walikelas', 'academic_year' => '2026/2027']));
        $response->assertSessionHas('success');

        $classroom->refresh();
        $this->assertEquals($guru->id, $classroom->homeroom_teacher_id);
    }

    public function test_waka_can_import_homeroom_data_via_xlsx(): void
    {
        $waka = User::where('username', 'waka')->first();
        $guru = User::where('nigs_fix', '42009')->first(); // Afif Bey Ridhotulloh
        $classroom = Classroom::where('name', 'X TKR 2')->first();

        $classroom->update(['homeroom_teacher_id' => null]);

        $zip = new \ZipArchive;
        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
            <Default Extension="xml" ContentType="application/xml"/>
            <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
            <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
            <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
            <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
        </Types>');
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="2" uniqueCount="2">
            <si><t>X TKR 2</t></si>
            <si><t>Afif Bey Ridhotulloh</t></si>
        </sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
            <sheetData>
                <row r="1">
                    <c r="A1" t="s"><v>0</v></c>
                    <c r="B1" t="s"><v>1</v></c>
                </row>
            </sheetData>
        </worksheet>');
        $zip->close();

        $file = new UploadedFile($tmpFile, 'test_wali.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->actingAs($waka)->post(route('teacher-assignments.import-homeroom'), [
            'file' => $file,
            'academic_year' => '2026/2027',
        ]);

        $response->assertRedirect(route('teacher-assignments.index', ['tab' => 'walikelas', 'academic_year' => '2026/2027']));
        $response->assertSessionHas('success');

        $classroom->refresh();
        $this->assertEquals($guru->id, $classroom->homeroom_teacher_id);

        @unlink($tmpFile);
    }

    public function test_waka_can_download_supervisor_template(): void
    {
        $waka = User::where('username', 'waka')->first();

        $response = $this->actingAs($waka)->get(route('teacher-assignments.template-supervisor', [
            'academic_year' => '2026/2027',
        ]));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('template_tim_supervisi_2026-2027.csv', $response->headers->get('content-disposition'));
    }

    public function test_waka_can_import_supervisor_data(): void
    {
        $waka = User::where('username', 'waka')->first();
        $guru = User::where('nigs_fix', '41709')->first(); // Indra Yustiawan

        $csvContent = "\xEF\xBB\xBFNama Guru,NIGS,Keterangan\n";
        $csvContent .= "\"Indra Yustiawan, M.Pd.\",41709,\"Koordinator Supervisi\"\n";

        $file = UploadedFile::fake()->createWithContent('supervisor.csv', $csvContent);

        $response = $this->actingAs($waka)->post(route('teacher-assignments.import-supervisor'), [
            'file' => $file,
            'academic_year' => '2026/2027',
        ]);

        $response->assertRedirect(route('teacher-assignments.index', ['tab' => 'supervisor', 'academic_year' => '2026/2027']));
        $response->assertSessionHas('success');

        $guru->refresh();
        $this->assertEquals('supervisor', $guru->role->slug);
        $this->assertEquals('Koordinator Supervisi', $guru->position);
    }

    public function test_waka_can_download_structural_template(): void
    {
        $waka = User::where('username', 'waka')->first();

        $response = $this->actingAs($waka)->get(route('teacher-assignments.template-structural', [
            'academic_year' => '2026/2027',
        ]));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('template_penugasan_struktural_2026-2027.csv', $response->headers->get('content-disposition'));
    }

    public function test_waka_can_import_structural_data(): void
    {
        $waka = User::where('username', 'waka')->first();
        $guru = User::where('nigs_fix', '42009')->first(); // Afif Bey Ridhotulloh
        $pos = StructuralPosition::where('code', 'KA_BENGKEL')->first();

        $csvContent = "\xEF\xBB\xBFNama Jabatan,Nama Guru,NIGS,Nomor SK,Tanggal SK,Catatan\n";
        $csvContent .= "\"Ka. Bengkel\",\"Afif Bey Ridhotulloh\",42009,\"421.5/SK-01/2026\",\"2026-07-15\",\"Kepala Bengkel Otomotif\"\n";

        $file = UploadedFile::fake()->createWithContent('struktural.csv', $csvContent);

        $response = $this->actingAs($waka)->post(route('teacher-assignments.import-structural'), [
            'file' => $file,
            'academic_year' => '2026/2027',
        ]);

        $response->assertRedirect(route('teacher-assignments.index', ['tab' => 'struktural', 'academic_year' => '2026/2027']));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('teacher_structural_assignments', [
            'teacher_id' => $guru->id,
            'structural_position_id' => $pos->id,
            'academic_year' => '2026/2027',
            'sk_number' => '421.5/SK-01/2026',
        ]);
    }
}
