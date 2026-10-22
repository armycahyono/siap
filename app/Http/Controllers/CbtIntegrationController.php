<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Services\CbtSyncService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CbtIntegrationController extends Controller
{
    public function __construct(
        protected CbtSyncService $syncService
    ) {}

    /**
     * Display the CBT Integration dashboard.
     */
    public function index(Request $request): View
    {
        $status = $this->syncService->getSyncStatus();
        $academicYears = AcademicYear::orderBy('name', 'desc')->get();
        $selectedYear = $request->query('academic_year', $status['active_academic_year'] ?? null);
        $selectedSmt = $request->query('semester', $status['active_semester'] ?? null);
        $selectedJadwal = $request->query('jadwal_id');

        $examResults = collect();
        $jadwalList = collect();

        if ($status['connected']) {
            $jadwalList = $this->syncService->getCbtJadwalList($selectedYear);
            $examResults = $this->syncService->pullExamResults(
                tahun: $selectedYear,
                smt: $selectedSmt,
                jadwalId: $selectedJadwal ? (int) $selectedJadwal : null
            );
        }

        return view('kurikulum.cbt.index', compact(
            'status',
            'academicYears',
            'selectedYear',
            'selectedSmt',
            'selectedJadwal',
            'jadwalList',
            'examResults'
        ));
    }

    /**
     * Trigger synchronization from SIAP to CBT.
     */
    public function sync(Request $request): JsonResponse|RedirectResponse
    {
        $type = $request->input('type', 'all');
        $academicYearId = $request->input('academic_year_id');

        try {
            $result = match ($type) {
                'tp' => $this->syncService->syncTahunPelajaran(),
                'semester' => $this->syncService->syncSemester(),
                'jurusan' => $this->syncService->syncJurusan(),
                'mapel' => $this->syncService->syncMapel(),
                'guru' => $this->syncService->syncGuru(),
                'kelas' => $this->syncService->syncKelas($academicYearId ? (int) $academicYearId : null),
                'siswa' => $this->syncService->syncSiswa($academicYearId ? (int) $academicYearId : null),
                default => $this->syncService->syncAll($academicYearId ? (int) $academicYearId : null),
            };

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Sinkronisasi berhasil dijalankan.',
                    'data' => $result,
                ]);
            }

            return redirect()->back()->with('success', 'Sinkronisasi data ke CBT berhasil dijalankan.');
        } catch (Exception $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal melakukan sinkronisasi: '.$e->getMessage(),
                ], 500);
            }

            return redirect()->back()->with('error', 'Gagal melakukan sinkronisasi: '.$e->getMessage());
        }
    }

    /**
     * Test CBT database connection.
     */
    public function testConnection(): JsonResponse
    {
        $connected = $this->syncService->testConnection();

        return response()->json([
            'connected' => $connected,
            'message' => $connected ? 'Koneksi ke database CBT (db_cbt) berhasil.' : 'Gagal terhubung ke database CBT.',
        ]);
    }
}
