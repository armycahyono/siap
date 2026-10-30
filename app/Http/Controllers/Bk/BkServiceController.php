<?php

namespace App\Http\Controllers\Bk;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\BkConfidentialNote;
use App\Models\BkGroup;
use App\Models\BkReferral;
use App\Models\BkService;
use App\Models\BkServiceParticipant;
use App\Models\Classroom;
use App\Models\PiketIncident;
use App\Models\Student;
use App\Models\StudentVisitation;
use App\Models\User;
use App\Services\AcademicYearContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class BkServiceController extends Controller
{
    /**
     * Tampilkan daftar sesi layanan Bimbingan dan Konseling.
     */
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
        }

        if (! $user->canOperateBk($academicYear)) {
            abort(403, 'Anda tidak memiliki kewenangan sebagai Guru BK untuk tahun ajaran ini.');
        }

        $serviceType = $request->query('service_type');
        $followUp = $request->query('follow_up');
        $followUpStatus = $request->query('follow_up_status');
        $search = $request->query('search');

        $query = BkService::with(['student.classroom', 'student.studentClassrooms.classroom', 'counselor', 'incident', 'followUpAssignee'])
            ->where('academic_year', $academicYear)
            ->latest('service_date')
            ->latest('id');

        if ($serviceType) {
            $query->where('service_type', $serviceType);
        }

        if ($followUpStatus) {
            $query->where('follow_up_status', $followUpStatus);
        }

        if ($followUp === 'ada') {
            $query->whereNotNull('follow_up_plan')->where('follow_up_plan', '!=', '');
        } elseif ($followUp === 'belum') {
            $query->where(function ($q) {
                $q->whereNull('follow_up_plan')->orWhere('follow_up_plan', '');
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('student', fn ($s) => $s->where('name', 'like', "%{$search}%")->orWhere('nisn', 'like', "%{$search}%"))
                    ->orWhere('summary', 'like', "%{$search}%")
                    ->orWhere('result', 'like', "%{$search}%");
            });
        }

        $services = $query->paginate(15)->withQueryString();
        $availableYears = AcademicYear::orderByDesc('name')->pluck('name')->all();
        if (empty($availableYears)) {
            $availableYears = [$academicYear];
        }
        $canAccessConfidential = $user->canAccessCounselingNotes($academicYear);

        // Early Warning System (EWS) agregat pemantauan
        $ewsStats = [
            'pendingReferrals' => BkReferral::where('status', 'menunggu')->count(),
            'overdueRtl' => BkService::where('academic_year', $academicYear)
                ->whereIn('follow_up_status', ['belum_dilaksanakan', 'sedang_berjalan'])
                ->whereNotNull('follow_up_target_date')
                ->where('follow_up_target_date', '<=', now()->addDays(2)->toDateString())
                ->count(),
            'pendingVisitations' => StudentVisitation::where('academic_year', $academicYear)
                ->where('status', 'dijadwalkan')
                ->count(),
            'repeatIncidents' => Student::whereHas('piketIncidents', fn ($q) => $q->forAcademicYear($academicYear), '>=', 2)->count(),
        ];

        return view('bk.services.index', compact(
            'services',
            'academicYear',
            'availableYears',
            'serviceType',
            'followUp',
            'followUpStatus',
            'search',
            'canAccessConfidential',
            'ewsStats'
        ));
    }

    /**
     * Tampilkan formulir pencatatan layanan BK baru.
     */
    public function create(Request $request): View
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $academicYearContext = app(AcademicYearContext::class);
        $academicYear = $academicYearContext->getViewingYear($request->query('academic_year'));

        if (! $user->canOperateBk($academicYear)) {
            abort(403, 'Anda tidak memiliki kewenangan sebagai Guru BK.');
        }

        $incident = null;
        $selectedStudent = null;

        if ($request->filled('incident_id')) {
            $incident = PiketIncident::with('student.classroom')->findOrFail($request->query('incident_id'));
            if ($incident->follow_up_target !== 'rujuk_bk') {
                abort(403, 'Kejadian piket ini tidak dirujuk ke BK.');
            }
            $selectedStudent = $incident->student;
        } elseif ($request->filled('student_id')) {
            $selectedStudent = Student::with('classroom')->findOrFail($request->query('student_id'));
        }

        $classrooms = Classroom::where(function ($q) use ($academicYear) {
            $q->where('academic_year', $academicYear)
                ->orWhereHas('academicYear', fn ($ay) => $ay->where('name', $academicYear));
        })->orderBy('name')->get();

        if ($classrooms->isEmpty()) {
            $classrooms = Classroom::where('is_active', true)->orderBy('name')->get();
        }

        $students = Student::with(['classroom', 'studentClassrooms.classroom'])
            ->where('status', 'aktif')
            ->orderBy('name')
            ->get();

        $officers = User::orderBy('name')->get(['id', 'name']);
        $canAccessConfidential = $user->canAccessCounselingNotes($academicYear);

        $bkGroups = BkGroup::active()
            ->forAcademicYear($academicYear)
            ->with(['students' => fn ($q) => $q->with('classroom')])
            ->withCount('students')
            ->get();

        return view('bk.services.create', compact(
            'academicYear',
            'incident',
            'selectedStudent',
            'students',
            'classrooms',
            'bkGroups',
            'officers',
            'canAccessConfidential'
        ));
    }

    /**
     * Simpan data layanan BK baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $academicYear = $request->input('academic_year', app(AcademicYearContext::class)->getViewingYear());

        if (! $user->canOperateBk($academicYear)) {
            abort(403, 'Anda tidak memiliki kewenangan sebagai Guru BK.');
        }

        $validated = $request->validate([
            'service_mode' => 'nullable|in:individual,kelompok,klasikal',
            'student_id' => 'required_if:service_mode,individual|nullable|exists:students,id',
            'bk_group_id' => 'required_if:service_mode,kelompok|nullable|exists:bk_groups,id',
            'classroom_id' => 'required_if:service_mode,klasikal|nullable|exists:classrooms,id',
            'participant_ids' => 'nullable|array',
            'participant_ids.*' => 'exists:students,id',
            'piket_incident_id' => 'nullable|exists:piket_incidents,id',
            'academic_year' => 'required|string|max:20',
            'service_date' => 'required|date',
            'service_type' => 'required|in:pribadi,sosial,belajar,karier',
            'summary' => 'required|string',
            'result' => 'required|string',
            'follow_up_plan' => 'nullable|string',
            'follow_up_assignee_id' => 'nullable|exists:users,id',
            'follow_up_target_date' => 'nullable|date',
            'follow_up_status' => 'nullable|in:belum_dilaksanakan,sedang_berjalan,selesai,dibatalkan',
            'confidential_notes' => 'nullable|string',
        ]);

        $serviceMode = $validated['service_mode'] ?? 'individual';

        $incident = null;
        if (! empty($validated['piket_incident_id'])) {
            $incident = PiketIncident::findOrFail($validated['piket_incident_id']);
            if ($incident->follow_up_target !== 'rujuk_bk') {
                abort(403, 'Kejadian piket ini tidak dirujuk ke BK.');
            }
        }

        // Tentukan perwakilan siswa utama untuk student_id jika kelompok/klasikal
        $primaryStudentId = $validated['student_id'] ?? null;
        if (! $primaryStudentId && ! empty($validated['participant_ids'])) {
            $primaryStudentId = $validated['participant_ids'][0];
        }

        $service = BkService::create([
            'student_id' => $primaryStudentId,
            'service_mode' => $serviceMode,
            'bk_group_id' => $validated['bk_group_id'] ?? null,
            'classroom_id' => $validated['classroom_id'] ?? null,
            'piket_incident_id' => $validated['piket_incident_id'] ?? null,
            'academic_year' => $validated['academic_year'],
            'service_date' => $validated['service_date'],
            'service_type' => $validated['service_type'],
            'summary' => $validated['summary'],
            'result' => $validated['result'],
            'follow_up_plan' => $validated['follow_up_plan'] ?? null,
            'follow_up_assignee_id' => $validated['follow_up_assignee_id'] ?? null,
            'follow_up_target_date' => $validated['follow_up_target_date'] ?? null,
            'follow_up_status' => $validated['follow_up_status'] ?? 'belum_dilaksanakan',
            'counselor_id' => $user->id,
        ]);

        // Simpan peserta layanan ke tabel bk_service_participants
        $participantsToAttach = [];
        if ($serviceMode === 'individual' && ! empty($validated['student_id'])) {
            $participantsToAttach[] = $validated['student_id'];
        } elseif (! empty($validated['participant_ids'])) {
            $participantsToAttach = $validated['participant_ids'];
        } elseif ($serviceMode === 'kelompok' && ! empty($validated['bk_group_id'])) {
            $group = BkGroup::with('students')->find($validated['bk_group_id']);
            $participantsToAttach = $group ? $group->students->pluck('id')->all() : [];
        } elseif ($serviceMode === 'klasikal' && ! empty($validated['classroom_id'])) {
            $participantsToAttach = Student::where('classroom_id', $validated['classroom_id'])
                ->where('status', 'aktif')
                ->pluck('id')
                ->all();
        }

        foreach (array_unique($participantsToAttach) as $stId) {
            BkServiceParticipant::create([
                'bk_service_id' => $service->id,
                'student_id' => $stId,
                'attendance_status' => 'hadir',
            ]);
        }

        // Simpan catatan rahasia hanya jika pengguna memiliki wewenang canAccessCounselingNotes
        if (! empty($validated['confidential_notes'])) {
            if ($user->canAccessCounselingNotes($validated['academic_year'])) {
                BkConfidentialNote::create([
                    'bk_service_id' => $service->id,
                    'notes' => $validated['confidential_notes'],
                    'created_by' => $user->id,
                ]);
            }
        }

        // Jika layanan berasal dari rujukan piket, pastikan status tindak lanjut BK minimal 'diproses'
        if ($incident) {
            $referral = BkReferral::firstOrNew(['piket_incident_id' => $incident->id]);
            if ($referral->status === 'menunggu' || ! $referral->exists) {
                $referral->status = 'diproses';
                $referral->handled_by = $user->id;
                $referral->save();
            }
        }

        return redirect()->route('bk.services.show', $service)
            ->with('success', 'Layanan bimbingan dan konseling berhasil dicatat.');
    }

    /**
     * Tampilkan detail sesi layanan BK.
     */
    public function show(BkService $service): View
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if (! $user->canOperateBk($service->academic_year)) {
            abort(403, 'Anda tidak memiliki kewenangan sebagai Guru BK untuk tahun ajaran ini.');
        }

        $service->load([
            'student.classroom',
            'counselor',
            'incident.classroom',
            'followUpAssignee',
            'group',
            'classroom',
            'participants.student.classroom',
        ]);

        $canAccessConfidential = $user->canAccessCounselingNotes($service->academic_year);

        // Hanya muat relasi catatan rahasia jika memiliki hak akses catatan konseling rahasia
        if ($canAccessConfidential) {
            $service->load('confidentialNote.author');
        }

        $officers = User::orderBy('name')->get(['id', 'name']);

        return view('bk.services.show', compact('service', 'canAccessConfidential', 'officers'));
    }

    /**
     * Tampilkan formulir edit layanan BK.
     */
    public function edit(BkService $service): View
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if (! $user->canOperateBk($service->academic_year)) {
            abort(403, 'Anda tidak memiliki kewenangan sebagai Guru BK untuk tahun ajaran ini.');
        }

        $service->load(['student.classroom', 'incident', 'followUpAssignee']);
        $canAccessConfidential = $user->canAccessCounselingNotes($service->academic_year);

        if ($canAccessConfidential) {
            $service->load('confidentialNote');
        }

        $officers = User::orderBy('name')->get(['id', 'name']);

        return view('bk.services.edit', compact('service', 'canAccessConfidential', 'officers'));
    }

    /**
     * Perbarui data layanan BK.
     */
    public function update(Request $request, BkService $service): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if (! $user->canOperateBk($service->academic_year)) {
            abort(403, 'Anda tidak memiliki kewenangan sebagai Guru BK.');
        }

        $validated = $request->validate([
            'service_date' => 'required|date',
            'service_type' => 'required|in:pribadi,sosial,belajar,karier',
            'summary' => 'required|string',
            'result' => 'required|string',
            'follow_up_plan' => 'nullable|string',
            'follow_up_assignee_id' => 'nullable|exists:users,id',
            'follow_up_target_date' => 'nullable|date',
            'follow_up_status' => 'nullable|in:belum_dilaksanakan,sedang_berjalan,selesai,dibatalkan',
            'follow_up_evaluation_notes' => 'nullable|string',
            'confidential_notes' => 'nullable|string',
        ]);

        $service->update([
            'service_date' => $validated['service_date'],
            'service_type' => $validated['service_type'],
            'summary' => $validated['summary'],
            'result' => $validated['result'],
            'follow_up_plan' => $validated['follow_up_plan'] ?? null,
            'follow_up_assignee_id' => $validated['follow_up_assignee_id'] ?? $service->follow_up_assignee_id,
            'follow_up_target_date' => $validated['follow_up_target_date'] ?? $service->follow_up_target_date,
            'follow_up_status' => $validated['follow_up_status'] ?? $service->follow_up_status,
            'follow_up_evaluation_notes' => $validated['follow_up_evaluation_notes'] ?? $service->follow_up_evaluation_notes,
            'follow_up_evaluated_at' => ! empty($validated['follow_up_evaluation_notes']) ? now() : $service->follow_up_evaluated_at,
        ]);

        // Periksa dan perbarui catatan rahasia secara aman
        $canAccessConfidential = $user->canAccessCounselingNotes($service->academic_year);
        if ($request->has('confidential_notes')) {
            if (! $canAccessConfidential) {
                abort(403, 'Anda tidak memiliki hak akses untuk mengubah catatan konseling rahasia.');
            }

            if (! empty($validated['confidential_notes'])) {
                $confidentialNote = BkConfidentialNote::firstOrNew(['bk_service_id' => $service->id]);
                $confidentialNote->notes = $validated['confidential_notes'];
                $confidentialNote->created_by = $user->id;
                $confidentialNote->save();
            } else {
                // Jika dikosongkan, hapus catatan rahasia
                $service->confidentialNote()?->delete();
            }
        }

        return redirect()->route('bk.services.show', $service)
            ->with('success', 'Data layanan BK berhasil diperbarui.');
    }

    /**
     * Perbarui Rencana Tindak Lanjut (RTL) dan evaluasi monitoring layanan BK.
     */
    public function updateRtl(Request $request, BkService $service): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $canUpdate = $user->canOperateBk($service->academic_year)
            || $user->id === $service->follow_up_assignee_id
            || $user->id === $service->counselor_id
            || $user->isSuperAdmin();

        if (! $canUpdate) {
            abort(403, 'Anda tidak memiliki wewenang untuk memperbarui monitoring RTL layanan ini.');
        }

        $validated = $request->validate([
            'follow_up_plan' => 'nullable|string',
            'follow_up_assignee_id' => 'nullable|exists:users,id',
            'follow_up_target_date' => 'nullable|date',
            'follow_up_status' => 'required|in:belum_dilaksanakan,sedang_berjalan,selesai,dibatalkan',
            'follow_up_evaluation_notes' => 'nullable|string',
        ]);

        $service->fill($validated);
        if (! empty($validated['follow_up_evaluation_notes']) || in_array($validated['follow_up_status'], ['selesai', 'sedang_berjalan'])) {
            $service->follow_up_evaluated_at = now();
        }
        $service->save();

        return redirect()->route('bk.services.show', $service)
            ->with('success', 'Monitoring dan evaluasi Rencana Tindak Lanjut (RTL) berhasil diperbarui.');
    }

    /**
     * Hapus data layanan BK.
     */
    public function destroy(BkService $service): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if (! $user->canOperateBk($service->academic_year)) {
            abort(403, 'Anda tidak memiliki kewenangan sebagai Guru BK.');
        }

        $incident = $service->incident;
        $service->delete();

        if ($incident) {
            return redirect()->route('bk.referrals.show', $incident)
                ->with('success', 'Sesi layanan BK berhasil dihapus.');
        }

        return redirect()->route('bk.services.index')
            ->with('success', 'Sesi layanan BK berhasil dihapus.');
    }
}
