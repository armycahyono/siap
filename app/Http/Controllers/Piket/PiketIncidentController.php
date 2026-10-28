<?php

namespace App\Http\Controllers\Piket;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\BkReferral;
use App\Models\Classroom;
use App\Models\PiketIncident;
use App\Models\PiketJournal;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Services\AcademicYearContext;
use App\Services\DeviceDetector;
use App\Services\TelegramService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class PiketIncidentController extends Controller
{
    public function __construct(protected TelegramService $telegramService) {}

    public function index(Request $request): View
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $academicYearContext = app(AcademicYearContext::class);
        try {
            $academicYear = $academicYearContext->getViewingYear($request->query('academic_year'));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'academic_year' => $e->getMessage(),
            ]);
        } catch (\Throwable) {
            $academicYear = AcademicYear::getActive()?->name ?? SchoolSetting::getMasterYear();
        }

        if (! method_exists($user, 'canViewPiketCases') || ! $user->canViewPiketCases($academicYear)) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat data kejadian siswa.');
        }

        $canManageAllCases = $user->canManagePiketCases($academicYear)
            || $user->isPrincipal()
            || (method_exists($user, 'isWakaKurikulum') && $user->isWakaKurikulum())
            || $user->hasPermission('operate_piket', $academicYear);

        $isCounselingBK = method_exists($user, 'isCounselingBK') && $user->isCounselingBK($academicYear);
        $isHomeroomTeacher = method_exists($user, 'isHomeroomTeacher') && $user->isHomeroomTeacher(academicYear: $academicYear);

        $ayModel = AcademicYear::where('name', $academicYear)->first();

        $category = $request->query('category');
        $status = $request->query('status');
        $verificationStatus = $request->query('verification_status');
        $classroomId = $request->query('classroom_id');
        $search = $request->query('search');

        // Mode Periode: 'today' (default hari ini), 'week' (pekan ini), 'month' (bulan ini), 'range' (rentang kustom)
        $period = $request->query('period', 'today');
        $today = Carbon::today()->toDateString();

        if ($period === 'week') {
            $startDate = Carbon::now()->startOfWeek()->toDateString();
            $endDate = Carbon::now()->endOfWeek()->toDateString();
        } elseif ($period === 'month') {
            $startDate = Carbon::now()->startOfMonth()->toDateString();
            $endDate = Carbon::now()->endOfMonth()->toDateString();
        } elseif ($period === 'range') {
            $startDate = $request->query('start_date', $today);
            $endDate = $request->query('end_date', $today);
        } else {
            // Default 'today'
            $period = 'today';
            $specificDate = $request->query('date', $today);
            $startDate = $specificDate;
            $endDate = $specificDate;
        }

        $date = $startDate;

        $query = PiketIncident::with(['classroom.homeroomTeacher', 'student', 'recordedBy', 'verifier'])
            ->forAcademicYear($academicYear)
            ->latest('incident_date')
            ->latest('incident_time');

        if (! $canManageAllCases) {
            $query->where(function ($q) use ($user, $isCounselingBK, $isHomeroomTeacher) {
                $hasPrior = false;
                if ($isCounselingBK) {
                    $q->where('follow_up_target', 'rujuk_bk');
                    $hasPrior = true;
                }
                if ($isHomeroomTeacher) {
                    $homeroomClause = function ($sub) use ($user) {
                        $sub->where('follow_up_target', 'rujuk_wali_kelas')
                            ->whereHas('classroom', fn ($c) => $c->where('homeroom_teacher_id', $user->id));
                    };
                    if ($hasPrior) {
                        $q->orWhere($homeroomClause);
                    } else {
                        $q->where($homeroomClause);
                        $hasPrior = true;
                    }
                }
                if ($hasPrior) {
                    $q->orWhere('recorded_by', $user->id);
                } else {
                    $q->where('recorded_by', $user->id);
                }
            });
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($verificationStatus) {
            $query->where('verification_status', $verificationStatus);
        }

        if ($classroomId) {
            $query->where('classroom_id', $classroomId);
        }

        if ($startDate === $endDate) {
            $query->whereDate('incident_date', $startDate);
        } else {
            $query->whereBetween('incident_date', [$startDate, $endDate]);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('student_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('action_taken', 'like', "%{$search}%");
            });
        }

        $incidents = $query->paginate(20)->withQueryString();

        // Rekap angka statistik kejadian/kasus sesuai filter aktif
        $statsQuery = PiketIncident::forAcademicYear($academicYear);

        if (! $canManageAllCases) {
            $statsQuery->where(function ($q) use ($user, $isCounselingBK, $isHomeroomTeacher) {
                $hasPrior = false;
                if ($isCounselingBK) {
                    $q->where('follow_up_target', 'rujuk_bk');
                    $hasPrior = true;
                }
                if ($isHomeroomTeacher) {
                    $homeroomClause = function ($sub) use ($user) {
                        $sub->where('follow_up_target', 'rujuk_wali_kelas')
                            ->whereHas('classroom', fn ($c) => $c->where('homeroom_teacher_id', $user->id));
                    };
                    if ($hasPrior) {
                        $q->orWhere($homeroomClause);
                    } else {
                        $q->where($homeroomClause);
                        $hasPrior = true;
                    }
                }
                if ($hasPrior) {
                    $q->orWhere('recorded_by', $user->id);
                } else {
                    $q->where('recorded_by', $user->id);
                }
            });
        }

        if ($startDate === $endDate) {
            $statsQuery->whereDate('incident_date', $startDate);
        } else {
            $statsQuery->whereBetween('incident_date', [$startDate, $endDate]);
        }
        if ($classroomId) {
            $statsQuery->where('classroom_id', $classroomId);
        }
        if ($search) {
            $statsQuery->where(function ($q) use ($search) {
                $q->where('student_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('action_taken', 'like', "%{$search}%");
            });
        }

        $stats = [
            'total' => (clone $statsQuery)->count(),
            'tercatat' => (clone $statsQuery)->where('status', 'tercatat')->count(),
            'ditindaklanjuti' => (clone $statsQuery)->where('status', 'ditindaklanjuti')->count(),
            'selesai' => (clone $statsQuery)->where('status', 'selesai')->count(),
            'menunggu_verifikasi' => (clone $statsQuery)->where('verification_status', 'menunggu_verifikasi')->count(),
            'terverifikasi' => (clone $statsQuery)->where('verification_status', 'terverifikasi')->count(),
        ];

        $classrooms = Classroom::where(function ($query) use ($academicYear, $ayModel) {
            if ($ayModel) {
                $query->where('academic_year_id', $ayModel->id);
            }
            $query->orWhere('academic_year', $academicYear);
        })->orderBy('name')->get();

        if (DeviceDetector::isMobile($request)) {
            return view('mobile.piket.incidents_index', compact(
                'incidents', 'classrooms', 'category', 'status', 'verificationStatus', 'classroomId', 'date',
                'startDate', 'endDate', 'period', 'search', 'stats', 'academicYear', 'canManageAllCases'
            ));
        }

        return view('piket.incidents.index', compact(
            'incidents', 'classrooms', 'category', 'status', 'verificationStatus', 'classroomId', 'date',
            'startDate', 'endDate', 'period', 'search', 'stats', 'academicYear', 'canManageAllCases'
        ));
    }

    public function create(Request $request): View
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $operationalYear = app(AcademicYearContext::class)->getOperationalYear();
        $academicYear = $operationalYear->name;

        if (! $user->canRecordPiketIncident($academicYear)) {
            abort(403, 'Anda tidak memiliki hak akses untuk mencatat kejadian piket/pelanggaran.');
        }

        $ayModel = AcademicYear::where('name', $academicYear)->first();

        $classrooms = Classroom::where(function ($query) use ($academicYear, $ayModel) {
            if ($ayModel) {
                $query->where('academic_year_id', $ayModel->id);
            }
            $query->orWhere('academic_year', $academicYear);
        })->orderBy('name')->get();

        if (DeviceDetector::isMobile($request)) {
            return view('mobile.piket.incidents_create', compact('classrooms', 'academicYear'));
        }

        return view('piket.incidents.create', compact('classrooms', 'academicYear'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $operationalYear = app(AcademicYearContext::class)->getOperationalYear();
        $academicYear = $operationalYear->name;

        if (! $user->canRecordPiketIncident($academicYear)) {
            abort(403, 'Anda tidak memiliki hak akses untuk mencatat kejadian piket/pelanggaran.');
        }

        $validated = $request->validate([
            'incident_date' => 'required|date',
            'incident_time' => 'required|string',
            'student_id' => 'nullable|exists:students,id',
            'student_name' => 'required|string|max:255',
            'classroom_id' => 'nullable|exists:classrooms,id',
            'location' => 'nullable|string|max:255',
            'category' => 'required|in:kedisiplinan,seragam,gadget_terlarang,perkelahian,rokok,kesehatan_pingsan,lainnya',
            'description' => 'required|string',
            'action_taken' => 'required|string',
            'follow_up_target' => 'required|in:selesai_di_piket,rujuk_wali_kelas,rujuk_bk,rujuk_kesiswaan',
            'confiscated_items' => 'nullable|string|max:255',
            'evidence_photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        if (empty($validated['classroom_id']) && ! empty($validated['student_id'])) {
            $student = Student::find($validated['student_id']);
            if ($student) {
                $validated['classroom_id'] = $student->classroom_id;
            }
        }

        $photoPath = null;
        if ($request->hasFile('evidence_photo')) {
            $photoPath = $request->file('evidence_photo')->store('piket/incidents', 'local');
        }

        $journal = PiketJournal::firstOrCreate(
            ['date' => $validated['incident_date']],
            [
                'academic_year' => $operationalYear->name,
                'semester' => $operationalYear->semester ?? 'Ganjil',
                'status' => 'berlangsung',
                'coordinator_id' => Auth::id(),
            ]
        );

        $incident = PiketIncident::create([
            'piket_journal_id' => $journal->id,
            'academic_year' => $operationalYear->name ?? $journal->academic_year,
            'incident_date' => $validated['incident_date'],
            'incident_time' => $validated['incident_time'],
            'student_id' => $validated['student_id'] ?? null,
            'student_name' => $validated['student_name'],
            'classroom_id' => $validated['classroom_id'] ?? null,
            'location' => $validated['location'] ?? null,
            'category' => $validated['category'],
            'description' => $validated['description'],
            'action_taken' => $validated['action_taken'],
            'follow_up_target' => $validated['follow_up_target'],
            'confiscated_items' => $validated['confiscated_items'] ?? null,
            'evidence_photo' => $photoPath,
            'recorded_by' => Auth::id(),
            'status' => $validated['follow_up_target'] === 'selesai_di_piket' ? 'selesai' : 'tercatat',
        ]);

        // Jika dirujuk ke BK, buat rujukan BK otomatis agar langsung muncul di agenda/dashboard BK
        if ($incident->follow_up_target === 'rujuk_bk' && $incident->student_id) {
            BkReferral::firstOrCreate(
                ['piket_incident_id' => $incident->id],
                ['status' => 'menunggu']
            );
        }

        // Kirim notifikasi Telegram
        $classroom = $incident->classroom_id ? Classroom::with('homeroomTeacher')->find($incident->classroom_id) : null;
        $homeroomTeacher = $classroom?->homeroomTeacher;
        $this->telegramService->notifyIncident($incident, $homeroomTeacher);

        return redirect()->route('piket.incidents.index')
            ->with('success', 'Catatan kejadian/kasus berhasil disimpan dan dilaporkan.');
    }

    public function updateStatus(Request $request, PiketIncident $incident): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $academicYear = $incident->academic_year ?? app(AcademicYearContext::class)->getViewingYear();

        $validated = $request->validate([
            'status' => 'required|in:tercatat,ditindaklanjuti,selesai',
        ]);

        if (! $user->canTransitionIncidentStatus($incident, $validated['status'], $academicYear)) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengubah status kejadian ini ke '.$validated['status'].'.');
        }

        $incident->update($validated);

        return back()->with('success', 'Status penanganan kejadian berhasil diperbarui.');
    }

    /**
     * Verifikasi kejadian piket sebagai pelanggaran kedisiplinan dan tetapkan sanksi/tindak lanjut.
     * Hanya dapat dilakukan oleh Tim Kedisiplinan, Waka Kesiswaan, atau Pimpinan Sekolah.
     */
    public function verify(Request $request, PiketIncident $incident): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $academicYear = $incident->academic_year ?? $incident->journal?->academic_year ?? app(AcademicYearContext::class)->getViewingYear();

        if (! $user->canVerifyDiscipline($academicYear)) {
            abort(403, 'Anda tidak memiliki hak akses verifikasi kedisiplinan untuk kasus ini.');
        }

        $validated = $request->validate([
            'verification_status' => 'required|in:menunggu_verifikasi,terverifikasi,bukan_pelanggaran,dibatalkan',
            'verification_notes' => 'nullable|string',
            'disciplinary_sanction' => 'nullable|string',
            'parental_involvement' => 'nullable|boolean',
            'parental_involvement_notes' => 'nullable|string',
            'follow_up_target' => 'nullable|in:selesai_di_piket,rujuk_wali_kelas,rujuk_bk,rujuk_kesiswaan',
            'status' => 'nullable|in:tercatat,ditindaklanjuti,selesai',
        ]);

        $updateData = [
            'verification_status' => $validated['verification_status'],
            'verified_by' => $user->id,
            'verified_at' => now(),
            'verification_notes' => $validated['verification_notes'] ?? null,
            'disciplinary_sanction' => $validated['disciplinary_sanction'] ?? null,
            'parental_involvement' => (bool) ($validated['parental_involvement'] ?? false),
            'parental_involvement_notes' => $validated['parental_involvement_notes'] ?? null,
        ];

        if (! empty($validated['follow_up_target'])) {
            $updateData['follow_up_target'] = $validated['follow_up_target'];
        }

        if (! empty($validated['status'])) {
            $updateData['status'] = $validated['status'];
        } elseif ($validated['verification_status'] === 'terverifikasi') {
            $updateData['status'] = 'ditindaklanjuti';
        } elseif (in_array($validated['verification_status'], ['bukan_pelanggaran', 'dibatalkan'])) {
            $updateData['status'] = 'selesai';
        }

        $incident->update($updateData);

        if ($incident->follow_up_target === 'rujuk_bk' && $incident->student_id) {
            BkReferral::firstOrCreate(
                ['piket_incident_id' => $incident->id],
                ['status' => 'menunggu']
            );
        }

        // Kirim notifikasi Telegram verifikasi kedisiplinan
        $this->telegramService->notifyIncidentVerification($incident->fresh(['classroom.homeroomTeacher', 'verifier', 'recordedBy']));

        return back()->with('success', 'Verifikasi kejadian dan penetapan sanksi kedisiplinan berhasil disimpan.');
    }

    public function destroy(PiketIncident $incident): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $academicYear = $incident->academic_year ?? app(AcademicYearContext::class)->getViewingYear();

        if (! $user->canDeletePiketIncident($incident, $academicYear)) {
            abort(403, 'Anda tidak memiliki hak akses untuk menghapus data catatan kejadian ini.');
        }

        if ($incident->evidence_photo) {
            if (Storage::disk('local')->exists($incident->evidence_photo)) {
                Storage::disk('local')->delete($incident->evidence_photo);
            } elseif (Storage::disk('public')->exists($incident->evidence_photo)) {
                Storage::disk('public')->delete($incident->evidence_photo);
            }
        }

        $incident->delete();

        return back()->with('success', 'Data catatan kejadian berhasil dihapus.');
    }

    /**
     * View private incident evidence photo with strict authorization.
     */
    public function viewPhoto(PiketIncident $incident)
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if (! Gate::allows('viewPhoto', $incident)) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat foto bukti kejadian ini.');
        }

        $path = $incident->evidence_photo;
        if (! $path || str_contains($path, '..')) {
            abort(404, 'Foto bukti tidak ditemukan.');
        }

        $disk = null;
        if (Storage::disk('local')->exists($path)) {
            $disk = 'local';
        } elseif (Storage::disk('public')->exists($path)) {
            $disk = 'public';
        }

        if (! $disk) {
            abort(404, 'File foto bukti tidak ditemukan di penyimpanan.');
        }

        return Storage::disk($disk)->response($path);
    }

    /**
     * Ekspor Surat Panggilan Orang Tua / Surat Peringatan Kedisiplinan dalam format PDF.
     */
    public function exportSuratPanggilan(PiketIncident $incident)
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if (! Gate::allows('exportSuratPanggilan', $incident)) {
            abort(403, 'Anda tidak memiliki hak akses untuk mencetak surat panggilan orang tua.');
        }

        $incident->load(['student.classroom.homeroomTeacher', 'classroom.homeroomTeacher', 'verifier', 'recordedBy']);
        $setting = SchoolSetting::getSetting();

        $pdf = Pdf::loadView('piket.incidents.surat_panggilan_pdf', compact('incident', 'setting'))
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true);

        $filename = 'Surat_Panggilan_Ortu_'.Str::slug($incident->student_name).'_'.$incident->incident_date->format('Ymd').'.pdf';

        return $pdf->download($filename);
    }
}
