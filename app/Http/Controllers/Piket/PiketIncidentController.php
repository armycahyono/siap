<?php

namespace App\Http\Controllers\Piket;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\PiketIncident;
use App\Models\PiketJournal;
use App\Models\Student;
use App\Services\AcademicYearContext;
use App\Services\DeviceDetector;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class PiketIncidentController extends Controller
{
    public function __construct(protected TelegramService $telegramService) {}

    public function index(Request $request): View
    {
        $academicYearContext = app(AcademicYearContext::class);
        try {
            $academicYear = $academicYearContext->getViewingYear($request->query('academic_year'));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'academic_year' => $e->getMessage(),
            ]);
        }

        $ayModel = AcademicYear::where('name', $academicYear)->first();

        $category = $request->query('category');
        $status = $request->query('status');
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

        $query = PiketIncident::with(['classroom.homeroomTeacher', 'student', 'recordedBy'])
            ->latest('incident_date')
            ->latest('incident_time');

        if ($category) {
            $query->where('category', $category);
        }

        if ($status) {
            $query->where('status', $status);
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
        $statsQuery = PiketIncident::query();
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
        ];

        $classrooms = Classroom::where(function ($query) use ($academicYear, $ayModel) {
            if ($ayModel) {
                $query->where('academic_year_id', $ayModel->id);
            }
            $query->orWhere('academic_year', $academicYear);
        })->orderBy('name')->get();

        if (DeviceDetector::isMobile($request)) {
            return view('mobile.piket.incidents_index', compact(
                'incidents', 'classrooms', 'category', 'status', 'classroomId', 'date',
                'startDate', 'endDate', 'period', 'search', 'stats', 'academicYear'
            ));
        }

        return view('piket.incidents.index', compact(
            'incidents', 'classrooms', 'category', 'status', 'classroomId', 'date',
            'startDate', 'endDate', 'period', 'search', 'academicYear'
        ));
    }

    public function create(Request $request): View
    {
        $operationalYear = app(AcademicYearContext::class)->getOperationalYear();
        $academicYear = $operationalYear->name;
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
        $validated = $request->validate([
            'incident_date' => 'required|date',
            'incident_time' => 'required|string',
            'student_id' => 'nullable|exists:students,id',
            'student_name' => 'required|string|max:255',
            'classroom_id' => 'nullable|exists:classrooms,id',
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

        $operationalYear = app(AcademicYearContext::class)->getOperationalYear();

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
            'incident_date' => $validated['incident_date'],
            'incident_time' => $validated['incident_time'],
            'student_id' => $validated['student_id'] ?? null,
            'student_name' => $validated['student_name'],
            'classroom_id' => $validated['classroom_id'] ?? null,
            'category' => $validated['category'],
            'description' => $validated['description'],
            'action_taken' => $validated['action_taken'],
            'follow_up_target' => $validated['follow_up_target'],
            'confiscated_items' => $validated['confiscated_items'] ?? null,
            'evidence_photo' => $photoPath,
            'recorded_by' => Auth::id(),
            'status' => $validated['follow_up_target'] === 'selesai_di_piket' ? 'selesai' : 'tercatat',
        ]);

        // Kirim notifikasi Telegram
        $classroom = $incident->classroom_id ? Classroom::with('homeroomTeacher')->find($incident->classroom_id) : null;
        $homeroomTeacher = $classroom?->homeroomTeacher;
        $this->telegramService->notifyIncident($incident, $homeroomTeacher);

        return redirect()->route('piket.incidents.index')
            ->with('success', 'Catatan kejadian/kasus berhasil disimpan dan dilaporkan.');
    }

    public function updateStatus(Request $request, PiketIncident $incident): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:tercatat,ditindaklanjuti,selesai',
        ]);

        $incident->update($validated);

        return back()->with('success', 'Status penanganan kejadian berhasil diperbarui.');
    }

    public function destroy(PiketIncident $incident): RedirectResponse
    {
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

        $isAuthorized = $user->isSuperAdmin()
            || $user->isPrincipal()
            || (method_exists($user, 'isWakaKesiswaan') && $user->isWakaKesiswaan())
            || (method_exists($user, 'isWakaKurikulum') && $user->isWakaKurikulum())
            || (method_exists($user, 'isDisciplineTeam') && $user->isDisciplineTeam())
            || ($incident->follow_up_target === 'rujuk_bk' && method_exists($user, 'isCounselingBK') && $user->isCounselingBK())
            || $user->hasPermission('manage_piket_cases')
            || $user->hasPermission('operate_piket')
            || (method_exists($user, 'isPiketOfficerToday') && $user->isPiketOfficerToday())
            || ($incident->recorded_by === $user->id)
            || ($incident->classroom_id && $incident->classroom && $incident->classroom->homeroom_teacher_id === $user->id);

        if (! $isAuthorized) {
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
}
