<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\GtkPermit;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\DeviceDetector;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class GtkPermitController extends Controller
{
    public function __construct(
        protected TelegramService $telegramService
    ) {}

    public function create(): View|RedirectResponse
    {
        $personnel = User::operationalPersonnel()->where('is_active', true)->orderBy('name')->get();
        $typeDefinitions = GtkPermit::typeDefinitions();

        if (DeviceDetector::isMobile()) {
            return view('mobile.attendance.gtk_permits_create', compact('personnel', 'typeDefinitions'));
        }

        return redirect()->route('attendance.gtk-permits.index');
    }

    public function index(Request $request): View
    {
        // Auto-sync teacher absences recorded in Piket Inval / Jurnal Piket
        GtkPermit::syncFromPiketInvals();

        $setting = SchoolSetting::getSetting();
        $academicYear = $request->query('academic_year', session('active_academic_year', $setting->academic_year ?? '2026/2027'));

        $date = $request->query('date');
        $month = $request->query('month', $date ? null : Carbon::now()->format('Y-m'));
        $type = $request->query('type', 'all');
        $user = Auth::user();
        $isPrincipal = $user && $user->isPrincipal();
        $isWakaKurikulum = $user && $user->isWakaKurikulum();
        $isSuperAdmin = $user && $user->isSuperAdmin();
        $canManageGtkPermits = $isPrincipal || $isWakaKurikulum || $isSuperAdmin;

        // Evaluasi Scope: 'manage' (Daftar Izin & Cuti Seluruh GTK) vs 'personal' (Izin & Cuti Saya Pribadi)
        $requestedScope = $request->query('scope');
        if (! $canManageGtkPermits || $requestedScope === 'personal') {
            $scope = 'personal';
        } else {
            $scope = 'manage';
        }

        $isPersonalScope = ($scope === 'personal');
        $canViewAllPermits = $canManageGtkPermits && ! $isPersonalScope;

        $isPrincipalOrSuperAdmin = $isPrincipal || $isSuperAdmin;
        $statusDefault = ($canViewAllPermits && $isPrincipalOrSuperAdmin && DeviceDetector::isMobile()) ? 'pending' : 'all';
        $status = $request->query('status', $statusDefault);

        // Jika di mode personal, default status adalah 'all'
        if ((! $isPrincipalOrSuperAdmin || $isPersonalScope) && $status === 'pending' && ! $request->has('status')) {
            $status = 'all';
        }
        $userId = $canViewAllPermits ? $request->query('user_id') : ($user?->id ?? 0);
        $search = $request->query('search');

        $query = GtkPermit::with(['user', 'approver'])->latest('created_at');

        if (! $canViewAllPermits) {
            $query->where('user_id', $user?->id ?? 0);
        }

        if ($date) {
            $query->where(function ($q) use ($date) {
                $q->whereDate('start_date', '<=', $date)
                    ->whereDate('end_date', '>=', $date);
            });
        } elseif ($month && $month !== 'all') {
            $monthCarbon = Carbon::parse($month.'-01');
            $startOfMonth = $monthCarbon->copy()->startOfMonth()->toDateString();
            $endOfMonth = $monthCarbon->copy()->endOfMonth()->toDateString();

            $query->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('start_date', [$startOfMonth, $endOfMonth])
                    ->orWhereBetween('end_date', [$startOfMonth, $endOfMonth])
                    ->orWhere(function ($sq) use ($startOfMonth, $endOfMonth) {
                        $sq->where('start_date', '<=', $startOfMonth)
                            ->where('end_date', '>=', $endOfMonth);
                    });
            });
        }

        if ($type && $type !== 'all') {
            $query->where('permission_type', $type);
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('approval_note', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%"));
            });
        }

        $permits = $query->paginate(15)->withQueryString();

        // Summary counts for filtered date or month
        $statsQuery = GtkPermit::query();
        if (! $canViewAllPermits) {
            $statsQuery->where('user_id', $user?->id ?? 0);
        }

        if ($date) {
            $statsQuery->where(function ($q) use ($date) {
                $q->whereDate('start_date', '<=', $date)
                    ->whereDate('end_date', '>=', $date);
            });
        } elseif ($month && $month !== 'all') {
            $monthCarbon = Carbon::parse($month.'-01');
            $startOfMonth = $monthCarbon->copy()->startOfMonth()->toDateString();
            $endOfMonth = $monthCarbon->copy()->endOfMonth()->toDateString();
            $statsQuery->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('start_date', [$startOfMonth, $endOfMonth])
                    ->orWhereBetween('end_date', [$startOfMonth, $endOfMonth]);
            });
        }

        $allStats = $statsQuery->get();
        $stats = [
            'total' => $allStats->count(),
            'sakit' => $allStats->where('permission_type', 'sakit')->count(),
            'izin' => $allStats->where('permission_type', 'izin')->count(),
            'cuti' => $allStats->where('permission_type', 'cuti')->count(),
            'dinas_luar' => $allStats->where('permission_type', 'dinas_luar')->count(),
            'pending' => $allStats->where('status', GtkPermit::STATUS_PENDING)->count(),
            'approved' => $allStats->where('status', GtkPermit::STATUS_APPROVED)->count(),
            'rejected' => $allStats->where('status', GtkPermit::STATUS_REJECTED)->count(),
        ];

        $pendingPermitsQuery = GtkPermit::pending();
        if (! $canViewAllPermits) {
            $pendingPermitsQuery->where('user_id', $user?->id ?? 0);
        }
        $pendingPermitsCount = $pendingPermitsQuery->count();

        $todayPermitsQuery = GtkPermit::approved()
            ->whereDate('start_date', '<=', Carbon::today()->toDateString())
            ->whereDate('end_date', '>=', Carbon::today()->toDateString());
        if (! $canViewAllPermits) {
            $todayPermitsQuery->where('user_id', $user?->id ?? 0);
        }
        $todayPermitsCount = $todayPermitsQuery->count();

        $personnel = $canViewAllPermits
            ? User::operationalPersonnel()->where('is_active', true)->orderBy('name')->get()
            : collect([$user])->filter();
        $typeDefinitions = GtkPermit::typeDefinitions();
        $statusDefinitions = GtkPermit::statusDefinitions();

        if (DeviceDetector::isMobile()) {
            return view('mobile.attendance.gtk_permits', compact(
                'permits', 'personnel', 'typeDefinitions', 'statusDefinitions', 'stats', 'date', 'month', 'type', 'status', 'userId', 'search', 'todayPermitsCount', 'pendingPermitsCount', 'canViewAllPermits', 'scope', 'isPersonalScope', 'canManageGtkPermits'
            ));
        }

        return view('web.attendance.gtk_permits', compact(
            'permits',
            'personnel',
            'typeDefinitions',
            'statusDefinitions',
            'date',
            'month',
            'type',
            'status',
            'userId',
            'search',
            'stats',
            'todayPermitsCount',
            'pendingPermitsCount',
            'canViewAllPermits',
            'academicYear',
            'scope',
            'isPersonalScope',
            'canManageGtkPermits'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'permission_type' => ['required', 'in:sakit,izin,cuti,dinas_luar'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'], // Max 5MB
        ]);

        $currentUser = Auth::user();
        $canSubmitForOthers = $currentUser->canSubmitPermitForOthers();

        // Jika bukan Kepala Sekolah / Waka / Super Admin, user_id terkunci mutlak ke akun sendiri
        $targetUserId = $canSubmitForOthers ? $validated['user_id'] : $currentUser->id;

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = ! empty($validated['end_date']) ? Carbon::parse($validated['end_date']) : $startDate->copy();
        $durationDays = $startDate->diffInDays($endDate) + 1;

        $documentPath = null;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('gtk_permits', 'local');
        }

        // Jika Kepala Sekolah atau Super Admin yang menginput, otomatis langsung disetujui.
        // Jika Waka atau GTK mandiri yang mengajukan, status adalah 'pending' menunggu persetujuan KS.
        $isPrincipalOrAdmin = $currentUser->isPrincipal() || $currentUser->isSuperAdmin();
        $permitStatus = $isPrincipalOrAdmin ? GtkPermit::STATUS_APPROVED : GtkPermit::STATUS_PENDING;
        $approvedBy = $isPrincipalOrAdmin ? $currentUser->id : null;
        $approvedAt = $isPrincipalOrAdmin ? now() : null;

        $permit = GtkPermit::create([
            'user_id' => $targetUserId,
            'permission_type' => $validated['permission_type'],
            'status' => $permitStatus,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'duration_days' => $durationDays,
            'reason' => $validated['reason'],
            'notes' => $validated['notes'] ?? null,
            'document_path' => $documentPath,
            'approved_by' => $approvedBy,
            'approved_at' => $approvedAt,
        ]);

        $this->telegramService->notifyGtkPermit($permit, $currentUser);

        $user = User::find($validated['user_id']);

        $message = $permitStatus === GtkPermit::STATUS_APPROVED
            ? "Izin/Cuti untuk {$user->name} berhasil dicatat dan disetujui ({$durationDays} hari)."
            : "Pengajuan izin/cuti untuk {$user->name} berhasil dikirim dan menunggu persetujuan Kepala Sekolah.";

        return redirect()->back(fallback: route('attendance.gtk-permits.index'))->with('success', $message);
    }

    public function approve(Request $request, GtkPermit $gtkPermit): RedirectResponse
    {
        $currentUser = Auth::user();
        if (! $currentUser->isPrincipal() && ! $currentUser->isSuperAdmin()) {
            return redirect()->back()->with('error', 'Hanya Kepala Sekolah yang memiliki hak menyetujui izin GTK.');
        }

        $validated = $request->validate([
            'approval_note' => ['nullable', 'string', 'max:500'],
        ]);

        $gtkPermit->update([
            'status' => GtkPermit::STATUS_APPROVED,
            'approved_by' => $currentUser->id,
            'approved_at' => now(),
            'approval_note' => $validated['approval_note'] ?? $gtkPermit->approval_note,
        ]);

        $this->telegramService->notifyGtkPermitDecision($gtkPermit, $currentUser);

        return redirect()->back(fallback: route('attendance.gtk-permits.index'))
            ->with('success', "Pengajuan izin {$gtkPermit->user?->name} telah disetujui.");
    }

    public function reject(Request $request, GtkPermit $gtkPermit): RedirectResponse
    {
        $currentUser = Auth::user();
        if (! $currentUser->isPrincipal() && ! $currentUser->isSuperAdmin()) {
            return redirect()->back()->with('error', 'Hanya Kepala Sekolah yang memiliki hak menolak perizinan GTK.');
        }

        $validated = $request->validate([
            'approval_note' => ['required', 'string', 'max:500'],
        ], [
            'approval_note.required' => 'Mohon sertakan alasan penolakan izin.',
        ]);

        $gtkPermit->update([
            'status' => GtkPermit::STATUS_REJECTED,
            'approved_by' => $currentUser->id,
            'approved_at' => now(),
            'approval_note' => $validated['approval_note'],
        ]);

        $this->telegramService->notifyGtkPermitDecision($gtkPermit, $currentUser);

        return redirect()->back(fallback: route('attendance.gtk-permits.index'))
            ->with('info', "Pengajuan izin {$gtkPermit->user?->name} telah ditolak.");
    }

    public function update(Request $request, GtkPermit $gtkPermit): RedirectResponse
    {
        $currentUser = Auth::user();

        // GTK hanya boleh mengedit izinnya sendiri jika masih pending, atau Super Admin / Kepala Sekolah
        if (! $currentUser->isPrincipal() && ! $currentUser->isSuperAdmin()) {
            if ($gtkPermit->user_id !== $currentUser->id || ! $gtkPermit->isPending()) {
                return redirect()->back()->with('error', 'Anda tidak dapat mengedit izin yang sudah diproses.');
            }
        }

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'permission_type' => ['required', 'in:sakit,izin,cuti,dinas_luar'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = ! empty($validated['end_date']) ? Carbon::parse($validated['end_date']) : $startDate->copy();
        $durationDays = $startDate->diffInDays($endDate) + 1;

        $documentPath = $gtkPermit->document_path;
        if ($request->hasFile('document')) {
            if ($documentPath) {
                if (Storage::disk('local')->exists($documentPath)) {
                    Storage::disk('local')->delete($documentPath);
                } elseif (Storage::disk('public')->exists($documentPath)) {
                    Storage::disk('public')->delete($documentPath);
                }
            }
            $documentPath = $request->file('document')->store('gtk_permits', 'local');
        }

        $gtkPermit->update([
            'user_id' => $validated['user_id'],
            'permission_type' => $validated['permission_type'],
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'duration_days' => $durationDays,
            'reason' => $validated['reason'],
            'notes' => $validated['notes'] ?? null,
            'document_path' => $documentPath,
        ]);

        return redirect()->back(fallback: route('attendance.gtk-permits.index'))->with('success', 'Data izin/cuti GTK berhasil diperbarui.');
    }

    public function destroy(GtkPermit $gtkPermit): RedirectResponse
    {
        $currentUser = Auth::user();

        // Hanya pemilik izin (saat pending), Kepala Sekolah, atau Super Admin yang boleh membatalkan/menghapus
        if (! $currentUser->isPrincipal() && ! $currentUser->isSuperAdmin()) {
            if ($gtkPermit->user_id !== $currentUser->id || ! $gtkPermit->isPending()) {
                return redirect()->back()->with('error', 'Hanya pengajuan yang belum diproses yang dapat dibatalkan.');
            }
        }

        if ($gtkPermit->document_path) {
            if (Storage::disk('local')->exists($gtkPermit->document_path)) {
                Storage::disk('local')->delete($gtkPermit->document_path);
            } elseif (Storage::disk('public')->exists($gtkPermit->document_path)) {
                Storage::disk('public')->delete($gtkPermit->document_path);
            }
        }

        $gtkPermit->delete();

        return redirect()->back(fallback: route('attendance.gtk-permits.index'))->with('success', 'Data izin/cuti berhasil dihapus.');
    }

    /**
     * Download / preview private GTK permit document with strict authorization.
     */
    public function downloadDocument(GtkPermit $gtkPermit)
    {
        $currentUser = Auth::user();
        if (! $currentUser) {
            abort(401);
        }

        $isAuthorized = $currentUser->isSuperAdmin()
            || $currentUser->isPrincipal()
            || $currentUser->isWakaKurikulum()
            || ($gtkPermit->user_id === $currentUser->id);

        if (! $isAuthorized) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat atau mengunduh dokumen ini.');
        }

        $path = $gtkPermit->document_path;
        if (! $path || str_contains($path, '..')) {
            abort(404, 'Dokumen tidak ditemukan.');
        }

        $disk = null;
        if (Storage::disk('local')->exists($path)) {
            $disk = 'local';
        } elseif (Storage::disk('public')->exists($path)) {
            $disk = 'public';
        }

        if (! $disk) {
            abort(404, 'File dokumen tidak ditemukan di penyimpanan.');
        }

        return Storage::disk($disk)->response($path);
    }

    public function syncPiket(): RedirectResponse
    {
        $count = GtkPermit::syncFromPiketInvals();

        return redirect()->back(fallback: route('attendance.gtk-permits.index'))
            ->with('success', "Sinkronisasi perizinan dari Jurnal Piket selesai. ({$count} data baru disinkronkan).");
    }
}
