<?php

namespace App\Http\Controllers\Piket;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\PiketGuest;
use App\Models\PiketIncident;
use App\Models\PiketInvalClass;
use App\Models\PiketStudentPermit;
use App\Models\SchoolSetting;
use App\Services\DeviceDetector;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PiketReportController extends Controller
{
    public function index(Request $request): View
    {
        $setting = SchoolSetting::getSetting();
        $academicYear = $request->query('academic_year', session('active_academic_year', $setting->academic_year ?? '2026/2027'));

        $tab = $request->query('tab', 'permits'); // permits, inval, incidents, guests
        $startDate = $request->query('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', Carbon::now()->endOfMonth()->toDateString());
        $classroomId = $request->query('classroom_id');

        $classrooms = Classroom::where('academic_year', $academicYear)->orderBy('name')->get();

        // 1. Data Izin
        $permitsQuery = PiketStudentPermit::with(['classroom', 'givenBy'])
            ->whereBetween('permit_date', [$startDate, $endDate]);
        if ($classroomId) {
            $permitsQuery->where('classroom_id', $classroomId);
        }
        $permits = $permitsQuery->latest('permit_date')->get();

        // 2. Data Inval
        $invalQuery = PiketInvalClass::with(['journal', 'classroom', 'absentTeacher', 'invalTeacher', 'subject'])
            ->whereHas('journal', fn ($q) => $q->whereBetween('date', [$startDate, $endDate]));
        if ($classroomId) {
            $invalQuery->where('classroom_id', $classroomId);
        }
        $invals = $invalQuery->latest()->get();

        // 3. Data Kasus
        $incidentQuery = PiketIncident::with(['classroom', 'recordedBy'])
            ->whereBetween('incident_date', [$startDate, $endDate]);
        if ($classroomId) {
            $incidentQuery->where('classroom_id', $classroomId);
        }
        $incidents = $incidentQuery->latest('incident_date')->get();

        // 4. Data Tamu
        $guests = PiketGuest::with('recordedBy')
            ->whereBetween('visit_date', [$startDate, $endDate])
            ->latest('visit_date')
            ->get();

        // Rekap ringkasan
        $stats = [
            'total_late' => $permits->where('permit_type', 'terlambat')->count(),
            'total_leave' => $permits->whereIn('permit_type', ['izin_keluar_sementara', 'izin_pulang_cepat'])->count(),
            'total_inval' => $invals->count(),
            'total_incidents' => $incidents->count(),
            'total_guests' => $guests->count(),
        ];

        if (DeviceDetector::isMobile($request)) {
            return view('mobile.piket.reports', compact(
                'tab',
                'startDate',
                'endDate',
                'classroomId',
                'classrooms',
                'permits',
                'invals',
                'incidents',
                'guests',
                'stats',
                'academicYear'
            ));
        }

        return view('piket.reports.index', compact(
            'tab',
            'startDate',
            'endDate',
            'classroomId',
            'classrooms',
            'permits',
            'invals',
            'incidents',
            'guests',
            'stats',
            'academicYear'
        ));
    }

    public function export(Request $request): StreamedResponse
    {
        $tab = $request->query('tab', 'permits');
        $startDate = $request->query('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', Carbon::now()->endOfMonth()->toDateString());
        $classroomId = $request->query('classroom_id');

        $filename = "rekap-piket-{$tab}-{$startDate}-sd-{$endDate}.csv";

        return response()->streamDownload(function () use ($tab, $startDate, $endDate, $classroomId) {
            $handle = fopen('php://output', 'w');
            // Write BOM for Excel UTF-8 support
            fwrite($handle, "\xEF\xBB\xBF");

            if ($tab === 'permits') {
                fputcsv($handle, ['No', 'Nomor Izin', 'Tanggal', 'Nama Siswa', 'Kelas', 'Jenis Izin', 'Waktu Masuk/Keluar', 'Alasan', 'Tindakan/Sanksi', 'Petugas Piket']);
                $query = PiketStudentPermit::with(['classroom', 'givenBy'])
                    ->whereBetween('permit_date', [$startDate, $endDate]);
                if ($classroomId) {
                    $query->where('classroom_id', $classroomId);
                }
                $rows = $query->orderBy('permit_date')->get();
                foreach ($rows as $idx => $r) {
                    fputcsv($handle, [
                        $idx + 1,
                        $r->permit_number,
                        $r->permit_date->format('d/m/Y'),
                        $r->student_name,
                        $r->classroom?->name ?? '-',
                        $r->permit_type_label,
                        $r->entry_time ?: $r->departure_time ?: '-',
                        $r->reason,
                        $r->action_taken ?: '-',
                        $r->givenBy?->name ?? '-',
                    ]);
                }
            } elseif ($tab === 'inval') {
                fputcsv($handle, ['No', 'Tanggal', 'Kelas', 'Guru Berhalangan', 'Mapel', 'Jam Ke', 'Guru Inval/Pengganti', 'Alasan', 'Materi/Tugas']);
                $query = PiketInvalClass::with(['journal', 'classroom', 'absentTeacher', 'invalTeacher', 'subject'])
                    ->whereHas('journal', fn ($q) => $q->whereBetween('date', [$startDate, $endDate]));
                if ($classroomId) {
                    $query->where('classroom_id', $classroomId);
                }
                $rows = $query->get();
                foreach ($rows as $idx => $r) {
                    fputcsv($handle, [
                        $idx + 1,
                        $r->journal?->date?->format('d/m/Y') ?? '-',
                        $r->classroom?->name ?? '-',
                        $r->absentTeacher?->name ?? '-',
                        $r->subject?->name ?? '-',
                        "{$r->period_start} - {$r->period_end}",
                        $r->invalTeacher?->name ?? 'Belum ada',
                        $r->reason,
                        $r->assignment_material ?: '-',
                    ]);
                }
            } elseif ($tab === 'incidents') {
                fputcsv($handle, ['No', 'Tanggal', 'Waktu', 'Nama Siswa', 'Kelas', 'Kategori', 'Uraian Kejadian', 'Tindakan Piket', 'Barang Sitaan', 'Tindak Lanjut', 'Status']);
                $query = PiketIncident::with(['classroom', 'recordedBy'])
                    ->whereBetween('incident_date', [$startDate, $endDate]);
                if ($classroomId) {
                    $query->where('classroom_id', $classroomId);
                }
                $rows = $query->orderBy('incident_date')->get();
                foreach ($rows as $idx => $r) {
                    fputcsv($handle, [
                        $idx + 1,
                        $r->incident_date->format('d/m/Y'),
                        substr($r->incident_time, 0, 5),
                        $r->student_name,
                        $r->classroom?->name ?? '-',
                        $r->category_label,
                        $r->description,
                        $r->action_taken,
                        $r->confiscated_items ?: '-',
                        $r->follow_up_label,
                        ucfirst($r->status),
                    ]);
                }
            } else {
                fputcsv($handle, ['No', 'Tanggal', 'Check-In', 'Check-Out', 'Nama Tamu', 'Instansi', 'No. HP', 'Keperluan', 'Bertemu Dengan', 'No. Badge']);
                $rows = PiketGuest::whereBetween('visit_date', [$startDate, $endDate])->orderBy('visit_date')->get();
                foreach ($rows as $idx => $r) {
                    fputcsv($handle, [
                        $idx + 1,
                        $r->visit_date->format('d/m/Y'),
                        substr($r->check_in_time, 0, 5),
                        $r->check_out_time ? substr($r->check_out_time, 0, 5) : 'Masih Berkunjung',
                        $r->guest_name,
                        $r->institution ?: '-',
                        $r->phone ?: '-',
                        $r->purpose,
                        $r->meet_with,
                        $r->badge_number ?: '-',
                    ]);
                }
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function print(Request $request): View
    {
        $setting = SchoolSetting::getSetting();
        $academicYear = $request->query('academic_year', session('active_academic_year', $setting->academic_year ?? '2026/2027'));
        $tab = $request->query('tab', 'permits');
        $startDate = $request->query('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', Carbon::now()->endOfMonth()->toDateString());
        $classroomId = $request->query('classroom_id');

        $classroom = $classroomId ? Classroom::find($classroomId) : null;

        $permits = PiketStudentPermit::with(['classroom', 'givenBy'])
            ->whereBetween('permit_date', [$startDate, $endDate])
            ->when($classroomId, fn ($q) => $q->where('classroom_id', $classroomId))
            ->orderBy('permit_date')
            ->get();

        $invals = PiketInvalClass::with(['journal', 'classroom', 'absentTeacher', 'invalTeacher', 'subject'])
            ->whereHas('journal', fn ($q) => $q->whereBetween('date', [$startDate, $endDate]))
            ->when($classroomId, fn ($q) => $q->where('classroom_id', $classroomId))
            ->get();

        $incidents = PiketIncident::with(['classroom', 'recordedBy'])
            ->whereBetween('incident_date', [$startDate, $endDate])
            ->when($classroomId, fn ($q) => $q->where('classroom_id', $classroomId))
            ->orderBy('incident_date')
            ->get();

        $guests = PiketGuest::whereBetween('visit_date', [$startDate, $endDate])
            ->orderBy('visit_date')
            ->get();

        return view('piket.reports.print', compact(
            'tab',
            'startDate',
            'endDate',
            'classroom',
            'permits',
            'invals',
            'incidents',
            'guests',
            'setting',
            'academicYear'
        ));
    }
}
