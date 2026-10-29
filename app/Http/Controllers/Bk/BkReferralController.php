<?php

namespace App\Http\Controllers\Bk;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\BkReferral;
use App\Models\Classroom;
use App\Models\PiketIncident;
use App\Services\AcademicYearContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class BkReferralController extends Controller
{
    /**
     * Tampilkan daftar rujukan kasus meja piket ke Guru BK.
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

        if (! ($user->canOperateBk($academicYear) || $user->canManageDiscipline($academicYear))) {
            abort(403, 'Anda tidak memiliki kewenangan mengakses rujukan BK.');
        }

        $status = $request->query('status'); // 'menunggu', 'diproses', 'selesai'
        $classroomId = $request->query('classroom_id');
        $search = $request->query('search');

        $query = PiketIncident::with(['classroom', 'student', 'recordedBy', 'bkReferral.handler', 'bkServices.counselor'])
            ->where('follow_up_target', 'rujuk_bk')
            ->latest('incident_date')
            ->latest('incident_time');

        // Filter Tahun Ajaran
        $query->where(function ($q) use ($academicYear) {
            $q->whereHas('journal', fn ($j) => $j->where('academic_year', $academicYear))
                ->orWhereHas('classroom', fn ($c) => $c->where('academic_year', $academicYear)->orWhereHas('academicYear', fn ($ay) => $ay->where('name', $academicYear)))
                ->orWhereHas('student.studentClassrooms', fn ($sc) => $sc->where('academic_year_name', $academicYear)->orWhereHas('academicYear', fn ($ay) => $ay->where('name', $academicYear)));
        });

        // Filter Status Tindak Lanjut BK
        if ($status === 'menunggu') {
            $query->where(function ($q) {
                $q->doesntHave('bkReferral')
                    ->orWhereHas('bkReferral', fn ($r) => $r->where('status', 'menunggu'));
            });
        } elseif ($status === 'diproses') {
            $query->whereHas('bkReferral', fn ($r) => $r->where('status', 'diproses'));
        } elseif ($status === 'selesai') {
            $query->whereHas('bkReferral', fn ($r) => $r->where('status', 'selesai'));
        }

        if ($classroomId) {
            $query->where('classroom_id', $classroomId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('student_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('action_taken', 'like', "%{$search}%");
            });
        }

        $incidents = $query->paginate(15)->withQueryString();

        // Hitung Statistik Rujukan untuk Ringkasan Card
        $statsBase = PiketIncident::where('follow_up_target', 'rujuk_bk')
            ->where(function ($q) use ($academicYear) {
                $q->whereHas('journal', fn ($j) => $j->where('academic_year', $academicYear))
                    ->orWhereHas('classroom', fn ($c) => $c->where('academic_year', $academicYear)->orWhereHas('academicYear', fn ($ay) => $ay->where('name', $academicYear)))
                    ->orWhereHas('student.studentClassrooms', fn ($sc) => $sc->where('academic_year_name', $academicYear)->orWhereHas('academicYear', fn ($ay) => $ay->where('name', $academicYear)));
            });

        $totalRujukan = (clone $statsBase)->count();
        $totalMenunggu = (clone $statsBase)->where(function ($q) {
            $q->doesntHave('bkReferral')
                ->orWhereHas('bkReferral', fn ($r) => $r->where('status', 'menunggu'));
        })->count();
        $totalDiproses = (clone $statsBase)->whereHas('bkReferral', fn ($r) => $r->where('status', 'diproses'))->count();
        $totalSelesai = (clone $statsBase)->whereHas('bkReferral', fn ($r) => $r->where('status', 'selesai'))->count();

        $classrooms = Classroom::where('academic_year', $academicYear)->orderBy('name')->get();
        $availableYears = AcademicYear::orderByDesc('name')->pluck('name')->all();
        if (empty($availableYears)) {
            $availableYears = [$academicYear];
        }

        return view('bk.referrals.index', compact(
            'incidents',
            'academicYear',
            'availableYears',
            'classrooms',
            'status',
            'classroomId',
            'search',
            'totalRujukan',
            'totalMenunggu',
            'totalDiproses',
            'totalSelesai'
        ));
    }

    /**
     * Tampilkan detail rujukan kasus piket beserta sesi layanan BK yang telah dilakukan.
     */
    public function show(PiketIncident $incident): View
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $academicYear = $incident->journal?->academic_year ?? app(AcademicYearContext::class)->getViewingYear();

        if (! ($user->canOperateBk($academicYear) || $user->canManageDiscipline($academicYear))) {
            abort(403, 'Anda tidak memiliki kewenangan mengakses rujukan BK.');
        }

        if ($incident->follow_up_target !== 'rujuk_bk') {
            abort(403, 'Kasus kejadian ini tidak dirujuk kepada Guru BK.');
        }

        $incident->load([
            'classroom',
            'student.classroom',
            'recordedBy',
            'bkReferral.handler',
            'bkServices' => fn ($q) => $q->with('counselor')->latest('service_date'),
        ]);

        $canAccessConfidential = $user->canAccessCounselingNotes($academicYear);

        return view('bk.referrals.show', compact('incident', 'academicYear', 'canAccessConfidential'));
    }

    /**
     * Perbarui status tindak lanjut BK secara independen (tidak mengubah status operasional piket).
     */
    public function updateStatus(Request $request, PiketIncident $incident): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $academicYear = $incident->journal?->academic_year ?? app(AcademicYearContext::class)->getViewingYear();

        if (! ($user->canOperateBk($academicYear) || $user->canManageDiscipline($academicYear))) {
            abort(403, 'Anda tidak memiliki kewenangan mengakses rujukan BK.');
        }

        if ($incident->follow_up_target !== 'rujuk_bk') {
            abort(403, 'Kasus kejadian ini tidak dirujuk kepada Guru BK.');
        }

        $validated = $request->validate([
            'status' => 'required|in:menunggu,diproses,selesai',
            'notes' => 'nullable|string|max:1000',
        ]);

        $referral = BkReferral::firstOrNew(['piket_incident_id' => $incident->id]);
        $referral->status = $validated['status'];
        if (isset($validated['notes'])) {
            $referral->notes = $validated['notes'];
        }
        $referral->handled_by = $user->id;

        if ($validated['status'] === 'selesai') {
            $referral->completed_at = now();
        } else {
            $referral->completed_at = null;
        }

        $referral->save();

        return redirect()->route('bk.referrals.show', $incident)
            ->with('success', "Status tindak lanjut BK berhasil diperbarui menjadi '{$referral->status_label}'.");
    }
}
