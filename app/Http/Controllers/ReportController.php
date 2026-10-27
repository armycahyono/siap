<?php

namespace App\Http\Controllers;

use App\Models\SchoolSetting;
use App\Models\Supervision;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use chillerlan\QRCode\QRCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Ensure current user is authorized to view/print/download this supervision report.
     */
    protected function authorizeReportAccess(Supervision $supervision): void
    {
        $user = Auth::user();

        if ($user->hasRole('super_admin') || $user->isWakaKurikulum() || $user->isPrincipal()) {
            return;
        }

        if ($supervision->teacher_id !== $user->id && $supervision->supervisor_id !== $user->id) {
            abort(403, 'Anda tidak memiliki hak akses untuk mencetak atau mengunduh laporan supervisi ini.');
        }
    }

    /**
     * Print View for browser printing (A4 multi-page formal).
     */
    public function print(Supervision $supervision): View
    {
        $this->authorizeReportAccess($supervision);

        $supervision->load(['teacher', 'supervisor', 'principal', 'photos', 'scores.indicator.phase']);
        $setting = SchoolSetting::getSetting();

        // Auto-capture snapshot if already approved but not snapshotted yet
        if (! $supervision->hasSnapshot() && in_array($supervision->status, ['disetujui', 'selesai'])) {
            $supervision->captureSnapshot(true);
        }

        $token = $supervision->ensureQrToken();
        $verifyUrl = route('verify.supervision', $token);

        // Generate QR code data URI
        $qrCodeData = (new QRCode)->render($verifyUrl);

        $rubricPhases = $supervision->getRubricPhases();
        $schoolInfo = $supervision->getSnapshotSchool();

        $teacher = $supervision->snapshot_data['teacher'] ?? [
            'name' => $supervision->teacher?->name,
            'nip' => $supervision->teacher?->nip,
            'nuptk' => $supervision->teacher?->nuptk,
            'nigs' => $supervision->teacher?->nigs,
        ];
        $teacher['nip'] = Supervision::sanitizeNip($teacher['nip'] ?? null);
        $teacher['nuptk'] = Supervision::sanitizeNuptk($teacher['nuptk'] ?? null);
        $teacher['nigs'] = $teacher['nigs'] ?? $supervision->teacher?->nigs;

        $supervisor = $supervision->snapshot_data['supervisor'] ?? [
            'name' => $supervision->supervisor?->name,
            'nip' => $supervision->supervisor?->nip,
            'nuptk' => $supervision->supervisor?->nuptk,
            'nigs' => $supervision->supervisor?->nigs,
        ];
        $supervisor['nip'] = Supervision::sanitizeNip($supervisor['nip'] ?? null);
        $supervisor['nuptk'] = Supervision::sanitizeNuptk($supervisor['nuptk'] ?? null);
        $supervisor['nigs'] = $supervisor['nigs'] ?? $supervision->supervisor?->nigs;

        $principal = $supervision->snapshot_data['principal'] ?? [
            'name' => $supervision->principal?->name ?? $schoolInfo['principal_name'],
            'nip' => $supervision->principal?->nip ?? $schoolInfo['principal_nip'],
            'nuptk' => $supervision->principal?->nuptk ?? null,
            'nigs' => $supervision->principal?->nigs ?? null,
        ];
        $principal['nip'] = Supervision::sanitizeNip($principal['nip'] ?? null);
        $principal['nuptk'] = Supervision::sanitizeNuptk($principal['nuptk'] ?? null);
        $principal['nigs'] = $principal['nigs'] ?? $supervision->principal?->nigs;

        $penetapanCity = request('penetapan_city', 'Kediri');
        $rawPenetapanDate = request('penetapan_date');
        try {
            $carbonDate = $rawPenetapanDate ? Carbon::parse($rawPenetapanDate) : ($supervision->schedule_date ?: now());
            $formattedPenetapanDate = $carbonDate->translatedFormat('d F Y');
            $rawPenetapanDate = $carbonDate->format('Y-m-d');
        } catch (\Throwable $e) {
            $formattedPenetapanDate = ($supervision->schedule_date ?: now())->translatedFormat('d F Y');
            $rawPenetapanDate = ($supervision->schedule_date ?: now())->format('Y-m-d');
        }

        return view('reports.print', compact(
            'supervision',
            'setting',
            'verifyUrl',
            'qrCodeData',
            'rubricPhases',
            'schoolInfo',
            'teacher',
            'supervisor',
            'principal',
            'penetapanCity',
            'rawPenetapanDate',
            'formattedPenetapanDate'
        ));
    }

    /**
     * Download Server-Generated PDF.
     */
    public function pdf(Supervision $supervision)
    {
        $this->authorizeReportAccess($supervision);

        $supervision->load(['teacher', 'supervisor', 'principal', 'photos', 'scores.indicator.phase']);
        $setting = SchoolSetting::getSetting();

        if (! $supervision->hasSnapshot() && in_array($supervision->status, ['disetujui', 'selesai'])) {
            $supervision->captureSnapshot(true);
        }

        $token = $supervision->ensureQrToken();
        $verifyUrl = route('verify.supervision', $token);

        // Generate QR code
        $qrCodeData = (new QRCode)->render($verifyUrl);

        $rubricPhases = $supervision->getRubricPhases();
        $schoolInfo = $supervision->getSnapshotSchool();

        $teacher = $supervision->snapshot_data['teacher'] ?? [
            'name' => $supervision->teacher?->name,
            'nip' => $supervision->teacher?->nip,
            'nuptk' => $supervision->teacher?->nuptk,
            'nigs' => $supervision->teacher?->nigs,
        ];
        $teacher['nip'] = Supervision::sanitizeNip($teacher['nip'] ?? null);
        $teacher['nuptk'] = Supervision::sanitizeNuptk($teacher['nuptk'] ?? null);
        $teacher['nigs'] = $teacher['nigs'] ?? $supervision->teacher?->nigs;

        $supervisor = $supervision->snapshot_data['supervisor'] ?? [
            'name' => $supervision->supervisor?->name,
            'nip' => $supervision->supervisor?->nip,
            'nuptk' => $supervision->supervisor?->nuptk,
            'nigs' => $supervision->supervisor?->nigs,
        ];
        $supervisor['nip'] = Supervision::sanitizeNip($supervisor['nip'] ?? null);
        $supervisor['nuptk'] = Supervision::sanitizeNuptk($supervisor['nuptk'] ?? null);
        $supervisor['nigs'] = $supervisor['nigs'] ?? $supervision->supervisor?->nigs;

        $principal = $supervision->snapshot_data['principal'] ?? [
            'name' => $supervision->principal?->name ?? $schoolInfo['principal_name'],
            'nip' => $supervision->principal?->nip ?? $schoolInfo['principal_nip'],
            'nuptk' => $supervision->principal?->nuptk ?? null,
            'nigs' => $supervision->principal?->nigs ?? null,
        ];
        $principal['nip'] = Supervision::sanitizeNip($principal['nip'] ?? null);
        $principal['nuptk'] = Supervision::sanitizeNuptk($principal['nuptk'] ?? null);
        $principal['nigs'] = $principal['nigs'] ?? $supervision->principal?->nigs;

        $pdf = Pdf::loadView('reports.pdf', compact(
            'supervision',
            'setting',
            'verifyUrl',
            'qrCodeData',
            'rubricPhases',
            'schoolInfo',
            'teacher',
            'supervisor',
            'principal'
        ))
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true);

        $reportNumber = $supervision->report_number ?: 'SPV/'.str_pad($supervision->id, 3, '0', STR_PAD_LEFT);
        $safeReportNumber = trim(str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|', ' '], '_', $reportNumber));
        $cleanTeacherName = trim(str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '', $teacher['name'] ?? $supervision->teacher?->name ?? 'Guru'));

        $filename = "Laporan_{$safeReportNumber} ({$cleanTeacherName}).pdf";

        return $pdf->download($filename);
    }

    /**
     * Rekapitulasi Supervisi Sekolah.
     */
    public function recap(Request $request): View
    {
        $setting = SchoolSetting::getSetting();
        $academicYear = $request->query('academic_year', $setting->academic_year);
        $semester = $request->query('semester', $setting->semester);
        $status = $request->query('status');

        $query = Supervision::with(['teacher', 'supervisor']);

        if ($academicYear && $academicYear !== 'all') {
            $query->where('academic_year', $academicYear);
        }

        if ($semester && $semester !== 'all') {
            $query->where('semester', $semester);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $supervisions = $query->orderBy('schedule_date', 'asc')->get();

        $totalTeachers = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->count();
        $completedCount = $supervisions->whereIn('status', ['disetujui', 'selesai'])->count();
        $inProgressCount = $supervisions->whereIn('status', ['praobservasi', 'observasi', 'pascaobservasi', 'menunggu_persetujuan'])->count();
        $scheduledCount = $supervisions->where('status', 'terjadwal')->count();

        $averageScore = $completedCount > 0
            ? round($supervisions->whereIn('status', ['disetujui', 'selesai'])->avg('percentage'), 1)
            : 0;

        $predicates = [
            'Sangat Baik' => $supervisions->where('predicate', 'Sangat Baik')->count(),
            'Baik' => $supervisions->where('predicate', 'Baik')->count(),
            'Cukup' => $supervisions->where('predicate', 'Cukup')->count(),
            'Perlu Bimbingan' => $supervisions->where('predicate', 'Perlu Bimbingan')->count(),
        ];

        $existingYears = SchoolSetting::getAvailableAcademicYears();

        return view('reports.recap', compact(
            'supervisions',
            'setting',
            'academicYear',
            'semester',
            'status',
            'totalTeachers',
            'completedCount',
            'inProgressCount',
            'scheduledCount',
            'averageScore',
            'predicates',
            'existingYears'
        ));
    }

    /**
     * Ekspor Rekap Supervisi ke CSV / Excel.
     */
    public function exportExcel(Request $request): StreamedResponse
    {
        $setting = SchoolSetting::getSetting();
        $academicYear = $request->query('academic_year', $setting->academic_year);
        $semester = $request->query('semester', $setting->semester);

        $query = Supervision::with(['teacher', 'supervisor']);

        if ($academicYear && $academicYear !== 'all') {
            $query->where('academic_year', $academicYear);
        }

        if ($semester && $semester !== 'all') {
            $query->where('semester', $semester);
        }

        $supervisions = $query->orderBy('schedule_date', 'asc')->get();

        $filename = 'Rekap_Supervisi_'.str_replace('/', '-', $academicYear)."_{$semester}.csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($supervisions, $setting, $academicYear, $semester) {
            $handle = fopen('php://output', 'w');

            // Add UTF-8 BOM for Microsoft Excel to properly parse Indonesian characters
            fwrite($handle, "\xEF\xBB\xBF");

            // School Header
            fputcsv($handle, ['REKAPITULASI HASIL SUPERVISI PEMBELAJARAN']);
            fputcsv($handle, [$setting->school_name]);
            fputcsv($handle, ["Tahun Ajaran: {$academicYear} | Semester: {$semester}"]);
            fputcsv($handle, []); // empty line

            // Column Headers
            fputcsv($handle, [
                'No',
                'No. Laporan',
                'Nama Guru',
                'NIP / NIGS',
                'NUPTK',
                'Mata Pelajaran',
                'Konsentrasi Keahlian',
                'Kelas',
                'Supervisor',
                'Tanggal Supervisi',
                'Total Skor',
                'Skor Maksimal',
                'Persentase Nilai (%)',
                'Predikat',
                'Status',
                'Disetujui Kepala Sekolah',
            ]);

            $no = 1;
            foreach ($supervisions as $s) {
                $teacherId = Supervision::sanitizeNip($s->teacher?->nip) ?: ($s->teacher?->nigs ?: '-');
                $nuptk = Supervision::sanitizeNuptk($s->teacher?->nuptk) ?: '-';

                fputcsv($handle, [
                    $no++,
                    $s->report_number ?: '-',
                    $s->teacher?->name,
                    $teacherId !== '-' ? "'{$teacherId}" : '-',
                    $nuptk !== '-' ? "'{$nuptk}" : '-',
                    $s->subject,
                    $s->expertise_program ?: '-',
                    $s->class_name,
                    $s->supervisor?->name,
                    $s->schedule_date ? $s->schedule_date->format('d/m/Y') : '-',
                    $s->total_score,
                    $s->max_score,
                    number_format($s->percentage, 2, ',', '.').'%',
                    $s->predicate ?: '-',
                    $s->status_label,
                    $s->approved_by_principal ? 'Ya ('.$s->principal_approved_at?->format('d/m/Y').')' : 'Belum',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Riwayat Supervisi (Arsip hasil supervisi pembelajaran - Sesuai Prototipe)
     */
    public function history(Request $request): View
    {
        $user = auth()->user();
        $setting = SchoolSetting::getSetting();
        $academicYear = $request->query('academic_year', $setting->academic_year);
        $semester = $request->query('semester', 'all');
        $search = $request->query('search');
        $status = $request->query('status');

        $query = Supervision::with(['teacher', 'supervisor']);

        if ($academicYear && $academicYear !== 'all') {
            $query->where('academic_year', $academicYear);
        }

        if ($semester && $semester !== 'all') {
            $query->where('semester', $semester);
        }

        if ($user->hasRole('supervisor') && ! $user->isWakaKurikulum()) {
            $query->where('supervisor_id', $user->id);
        }

        if ($user->isTeacher()) {
            $query->where('teacher_id', $user->id);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('teacher', fn ($t) => $t->where('name', 'like', "%{$search}%")->orWhere('nigs', 'like', "%{$search}%"))
                    ->orWhereHas('supervisor', fn ($s) => $s->where('name', 'like', "%{$search}%"))
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('class_name', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        $supervisions = $query->orderBy('schedule_date', 'desc')->paginate(12)->withQueryString();
        $existingYears = SchoolSetting::getAvailableAcademicYears();

        return view('reports.history', compact('supervisions', 'search', 'status', 'academicYear', 'semester', 'setting', 'existingYears'));
    }

    /**
     * Index Cetak Laporan (Daftar laporan siap cetak - Sesuai Prototipe)
     */
    public function indexPrint(Request $request): View
    {
        $user = auth()->user();
        $setting = SchoolSetting::getSetting();
        $academicYear = $request->query('academic_year', $setting->academic_year);
        $semester = $request->query('semester', 'all');
        $search = $request->query('search');

        $query = Supervision::with(['teacher', 'supervisor', 'principal']);

        if ($academicYear && $academicYear !== 'all') {
            $query->where('academic_year', $academicYear);
        }

        if ($semester && $semester !== 'all') {
            $query->where('semester', $semester);
        }

        if ($user->hasRole('supervisor') && ! $user->isWakaKurikulum()) {
            $query->where('supervisor_id', $user->id);
        }

        if ($user->isTeacher()) {
            $query->where('teacher_id', $user->id);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('teacher', fn ($t) => $t->where('name', 'like', "%{$search}%")->orWhere('nigs', 'like', "%{$search}%"))
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('report_number', 'like', "%{$search}%");
            });
        }

        $supervisions = $query->orderBy('schedule_date', 'desc')->paginate(10)->withQueryString();
        $existingYears = SchoolSetting::getAvailableAcademicYears();

        return view('reports.print_index', compact('supervisions', 'search', 'academicYear', 'semester', 'setting', 'existingYears'));
    }
}
