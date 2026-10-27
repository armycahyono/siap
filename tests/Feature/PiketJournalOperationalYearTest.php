<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\PiketJournal;
use App\Models\Role;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\AcademicYearContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * D3-B.5 — Verifikasi bahwa jurnal piket baru selalu menggunakan tahun operasional
 * (AcademicYearContext::getOperationalYear()), bukan session viewing year.
 *
 * Aturan identitas jurnal:
 *   - piket_journals.date memiliki UNIQUE constraint.
 *   - Satu jurnal per tanggal kalender; academic_year BUKAN bagian dari identitas.
 *   - firstOrCreate(['date' => ...]) — hanya 'date' sebagai kriteria pencarian.
 *   - 'academic_year' hanya relevan saat membuat baris baru (nilai pembuatan).
 */
class PiketJournalOperationalYearTest extends TestCase
{
    use RefreshDatabase;

    protected User $piketUser;

    protected Classroom $classroom;

    protected Student $student;

    protected Subject $subject;

    /** Tahun ajaran operasional (is_active = true) sesuai AcademicYearSeeder. */
    protected string $operationalYear = '2026/2027';

    /** Tahun historis yang disimulasikan via session. */
    protected string $historicYear = '2024/2025';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        Http::fake();

        // Pastikan AcademicYearSeeder sudah menyediakan tahun aktif.
        $this->assertNotNull(
            AcademicYear::where('is_active', true)->first(),
            'Seeder harus menyediakan AcademicYear dengan is_active = true.'
        );

        $superAdminRole = Role::where('slug', 'super_admin')->firstOrFail();

        $this->piketUser = User::firstOrCreate(
            ['username' => 'piket_op_test'],
            [
                'name' => 'Piket Operasional Test',
                'short_name' => 'PiketOp',
                'email' => 'piket_op_test@example.com',
                'password' => bcrypt('password'),
                'role_id' => $superAdminRole->id,
                'is_active' => true,
                'telegram_chat_id' => '999000999',
            ]
        );

        $grade = GradeLevel::firstOrCreate(['name' => 'XI'], ['level' => 11]);
        $dept = Department::firstOrCreate(['code' => 'RPL'], ['name' => 'Rekayasa Perangkat Lunak']);

        $this->classroom = Classroom::firstOrCreate(
            ['name' => 'XI RPL 1 OY Test'],
            [
                'academic_year' => $this->operationalYear,
                'grade_level_id' => $grade->id,
                'department_id' => $dept->id,
                'homeroom_teacher_id' => $this->piketUser->id,
                'is_active' => true,
            ]
        );

        $this->student = Student::firstOrCreate(
            ['nis' => 'OYT001'],
            [
                'name' => 'Siswa OY Test',
                'nisn' => '0000000001',
                'classroom_id' => $this->classroom->id,
                'is_active' => true,
            ]
        );

        $this->subject = Subject::firstOrCreate(
            ['code' => 'OYT01'],
            ['name' => 'Mata Pelajaran OY Test', 'is_active' => true]
        );
    }

    // =========================================================================
    // Helper: normalise date string untuk assertDatabaseHas
    // SQLite menyimpan date sebagai datetime string; gunakan whereDate untuk
    // perbandingan yang aman. Kita bungkus assertion via query langsung.
    // =========================================================================

    /**
     * Cari jurnal berdasarkan tanggal (tanpa bergantung format datetime SQLite).
     */
    private function findJournalByDate(string $date): ?PiketJournal
    {
        return PiketJournal::whereDate('date', $date)->first();
    }

    // =========================================================================
    // 1-3: PiketJournalController::today()
    // =========================================================================

    /**
     * T1: Session berisi tahun historis, tetapi jurnal today() menggunakan tahun operasional.
     */
    public function test_today_creates_journal_with_operational_year_ignoring_historic_session(): void
    {
        Carbon::setTestNow('2026-10-27 08:00:00');

        $response = $this->actingAs($this->piketUser)
            ->withSession(['active_academic_year' => $this->historicYear])
            ->get(route('piket.journals.today'));

        $response->assertRedirect();

        $journal = $this->findJournalByDate('2026-10-27');
        $this->assertNotNull($journal, 'Jurnal harus dibuat untuk tanggal hari ini.');
        $this->assertEquals(
            $this->operationalYear,
            $journal->academic_year,
            'academic_year jurnal harus berisi tahun operasional, bukan session historis.'
        );

        Carbon::setTestNow();
    }

    /**
     * T2: Session kosong, tetapi today() tetap menggunakan tahun operasional.
     */
    public function test_today_creates_journal_with_operational_year_when_session_is_absent(): void
    {
        Carbon::setTestNow('2026-10-28 08:00:00');

        $response = $this->actingAs($this->piketUser)
            ->withSession([])
            ->get(route('piket.journals.today'));

        $response->assertRedirect();

        $journal = $this->findJournalByDate('2026-10-28');
        $this->assertNotNull($journal, 'Jurnal harus dibuat meski session kosong.');
        $this->assertEquals(
            $this->operationalYear,
            $journal->academic_year,
            'academic_year jurnal harus berisi tahun operasional.'
        );

        Carbon::setTestNow();
    }

    /**
     * T3: Jurnal yang sudah ada tidak diubah atau diduplikat ketika session berbeda.
     *
     * Skenario: today() dipanggil dua kali pada tanggal yang sama dengan session berbeda.
     * Panggilan pertama membuat jurnal dengan tahun operasional.
     * Panggilan kedua (session historis) harus mengembalikan jurnal lama — tidak membuat baru.
     *
     * Catatan: unique constraint ada pada 'date'. firstOrCreate hanya mencari by 'date'.
     */
    public function test_existing_journal_not_duplicated_on_second_today_call(): void
    {
        Carbon::setTestNow('2026-11-18 08:00:00');

        // Panggilan pertama — membuat jurnal dengan tahun operasional.
        $this->actingAs($this->piketUser)
            ->withSession([])
            ->get(route('piket.journals.today'));

        $firstJournal = $this->findJournalByDate('2026-11-18');
        $this->assertNotNull($firstJournal, 'Jurnal harus dibuat pada panggilan pertama.');
        $this->assertEquals($this->operationalYear, $firstJournal->academic_year);

        // Panggilan kedua dengan session historis — harus menggunakan jurnal yang sama.
        $this->actingAs($this->piketUser)
            ->withSession(['active_academic_year' => $this->historicYear])
            ->get(route('piket.journals.today'));

        // Tetap hanya satu jurnal.
        $this->assertEquals(
            1,
            PiketJournal::whereDate('date', '2026-11-18')->count(),
            'Tidak boleh ada jurnal duplikat untuk tanggal yang sama.'
        );

        // Jurnal pertama tidak berubah.
        $this->assertEquals(
            $this->operationalYear,
            PiketJournal::find($firstJournal->id)?->academic_year,
            'academic_year jurnal yang sudah ada tidak boleh berubah.'
        );

        Carbon::setTestNow();
    }

    // =========================================================================
    // 4: PiketPermitController::store() — izin siswa
    // =========================================================================

    /**
     * T4: Izin siswa tersimpan dan jurnal baru menggunakan tahun operasional,
     *     meskipun session mengandung tahun historis.
     */
    public function test_permit_store_links_to_journal_with_operational_year(): void
    {
        Carbon::setTestNow('2026-11-10 09:00:00');

        $payload = [
            'permit_type' => 'terlambat',
            'permit_date' => '2026-11-10',
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'classroom_id' => $this->classroom->id,
            'reason' => 'Bangun kesiangan',
            'entry_time' => '08:30',
            'period_assigned' => '2',
        ];

        $response = $this->actingAs($this->piketUser)
            ->withSession(['active_academic_year' => $this->historicYear])
            ->post(route('piket.permits.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $journal = $this->findJournalByDate('2026-11-10');
        $this->assertNotNull($journal, 'Jurnal harus dibuat untuk tanggal permit.');
        $this->assertEquals(
            $this->operationalYear,
            $journal->academic_year,
            'academic_year jurnal yang dibuat oleh permit harus berisi tahun operasional.'
        );

        // Permit terhubung ke jurnal yang benar.
        $this->assertDatabaseHas('piket_student_permits', [
            'piket_journal_id' => $journal->id,
            'student_id' => $this->student->id,
            'permit_type' => 'terlambat',
        ]);

        Carbon::setTestNow();
    }

    // =========================================================================
    // 5: PiketIncidentController::store() — insiden
    // =========================================================================

    /**
     * T5: Insiden tersimpan dan jurnal baru menggunakan tahun operasional.
     */
    public function test_incident_store_links_to_journal_with_operational_year(): void
    {
        Carbon::setTestNow('2026-11-11 10:00:00');

        $payload = [
            'incident_date' => '2026-11-11',
            'incident_time' => '10:00',
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'classroom_id' => $this->classroom->id,
            'category' => 'kedisiplinan',
            'description' => 'Siswa tidak memakai seragam lengkap.',
            'action_taken' => 'Ditegur dan dicatat.',
            'follow_up_target' => 'selesai_di_piket',
        ];

        $response = $this->actingAs($this->piketUser)
            ->withSession(['active_academic_year' => $this->historicYear])
            ->post(route('piket.incidents.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $journal = $this->findJournalByDate('2026-11-11');
        $this->assertNotNull($journal, 'Jurnal harus dibuat untuk tanggal insiden.');
        $this->assertEquals(
            $this->operationalYear,
            $journal->academic_year,
            'academic_year jurnal yang dibuat oleh insiden harus berisi tahun operasional.'
        );

        $this->assertDatabaseHas('piket_incidents', [
            'piket_journal_id' => $journal->id,
            'student_id' => $this->student->id,
            'category' => 'kedisiplinan',
        ]);

        Carbon::setTestNow();
    }

    // =========================================================================
    // 6: PiketInvalController::store() — guru inval
    // =========================================================================

    /**
     * T6: Data guru inval tersimpan dan jurnal baru menggunakan tahun operasional.
     */
    public function test_inval_store_links_to_journal_with_operational_year(): void
    {
        Carbon::setTestNow('2026-11-12 07:30:00');

        $absentTeacher = User::firstOrCreate(
            ['username' => 'guru_absent_oy'],
            [
                'name' => 'Guru Absen OY',
                'short_name' => 'GuruAbsen',
                'email' => 'guru_absent_oy@example.com',
                'password' => bcrypt('password'),
                'role_id' => Role::where('slug', 'guru')->first()->id,
                'is_active' => true,
            ]
        );

        $payload = [
            'date' => '2026-11-12',
            'classroom_id' => $this->classroom->id,
            'absent_teacher_id' => $absentTeacher->id,
            'subject_id' => $this->subject->id,
            'period_start' => 1,
            'period_end' => 2,
            'reason' => 'Sakit',
        ];

        $response = $this->actingAs($this->piketUser)
            ->withSession(['active_academic_year' => $this->historicYear])
            ->post(route('piket.inval.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $journal = $this->findJournalByDate('2026-11-12');
        $this->assertNotNull($journal, 'Jurnal harus dibuat untuk tanggal inval.');
        $this->assertEquals(
            $this->operationalYear,
            $journal->academic_year,
            'academic_year jurnal yang dibuat oleh inval harus berisi tahun operasional.'
        );

        $this->assertDatabaseHas('piket_inval_classes', [
            'piket_journal_id' => $journal->id,
            'classroom_id' => $this->classroom->id,
            'absent_teacher_id' => $absentTeacher->id,
        ]);

        Carbon::setTestNow();
    }

    // =========================================================================
    // 7: PiketGuestController::store() — buku tamu
    // =========================================================================

    /**
     * T7: Entri buku tamu tersimpan dan jurnal baru menggunakan tahun operasional.
     */
    public function test_guest_store_links_to_journal_with_operational_year(): void
    {
        Carbon::setTestNow('2026-11-13 11:00:00');

        $payload = [
            'visit_date' => '2026-11-13',
            'check_in_time' => '11:00',
            'guest_name' => 'Tamu OY Test',
            'purpose' => 'Bertemu kepala sekolah',
            'meet_with' => 'Kepala Sekolah',
        ];

        $response = $this->actingAs($this->piketUser)
            ->withSession(['active_academic_year' => $this->historicYear])
            ->post(route('piket.guests.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $journal = $this->findJournalByDate('2026-11-13');
        $this->assertNotNull($journal, 'Jurnal harus dibuat untuk tanggal kunjungan tamu.');
        $this->assertEquals(
            $this->operationalYear,
            $journal->academic_year,
            'academic_year jurnal yang dibuat oleh buku tamu harus berisi tahun operasional.'
        );

        $this->assertDatabaseHas('piket_guests', [
            'piket_journal_id' => $journal->id,
            'guest_name' => 'Tamu OY Test',
        ]);

        Carbon::setTestNow();
    }

    // =========================================================================
    // 8: Kegagalan AcademicYearContext — tidak ada fallback literal
    // =========================================================================

    /**
     * T8a: Jika tidak ada AcademicYear aktif, service melempar RuntimeException.
     */
    public function test_no_active_year_throws_runtime_exception(): void
    {
        AcademicYear::query()->update(['is_active' => false]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Active academic year is not configured.');

        app(AcademicYearContext::class)->getOperationalYear();
    }

    /**
     * T8b: Request ke today() harus menghasilkan error (500) ketika tidak ada tahun aktif.
     *      Tidak boleh ada redirect ke jurnal palsu atau penggunaan fallback literal.
     */
    public function test_today_fails_explicitly_when_no_active_academic_year(): void
    {
        AcademicYear::query()->update(['is_active' => false]);

        Carbon::setTestNow('2026-11-14 08:00:00');

        $countBefore = PiketJournal::whereDate('date', '2026-11-14')->count();

        $response = $this->actingAs($this->piketUser)
            ->withSession([])
            ->get(route('piket.journals.today'));

        // Harus melempar exception — tidak ada redirect ke jurnal palsu.
        $response->assertStatus(500);

        // Tidak ada jurnal baru yang dibuat dengan fallback.
        $this->assertEquals(
            $countBefore,
            PiketJournal::whereDate('date', '2026-11-14')->count(),
            'Tidak boleh ada jurnal baru dibuat ketika tahun operasional tidak tersedia.'
        );

        Carbon::setTestNow();
    }

    // =========================================================================
    // 9: Perlindungan data historis
    // =========================================================================

    /**
     * T9: academic_year pada jurnal historis yang tersimpan tidak dimodifikasi
     *     oleh operasi tulis baru pada tanggal berbeda.
     */
    public function test_historic_journal_academic_year_is_not_modified(): void
    {
        // Buat jurnal historis secara langsung — tanggal masa lalu (pasti tidak ada di seeder).
        $historicJournal = PiketJournal::create([
            'date' => '2024-09-10',
            'academic_year' => $this->historicYear,
            'semester' => 'Ganjil',
            'status' => 'selesai',
            'coordinator_id' => $this->piketUser->id,
        ]);

        Carbon::setTestNow('2026-11-17 08:00:00');

        // today() dipanggil untuk tanggal berbeda dari jurnal historis.
        $this->actingAs($this->piketUser)
            ->withSession(['active_academic_year' => $this->operationalYear])
            ->get(route('piket.journals.today'));

        // Ambil ulang dari DB — academic_year tidak berubah.
        $refreshed = PiketJournal::find($historicJournal->id);
        $this->assertEquals(
            $this->historicYear,
            $refreshed->academic_year,
            'academic_year jurnal historis tidak boleh diubah oleh operasi hari ini.'
        );
        $this->assertEquals('selesai', $refreshed->status, 'Status jurnal historis tidak boleh berubah.');

        Carbon::setTestNow();
    }
}
