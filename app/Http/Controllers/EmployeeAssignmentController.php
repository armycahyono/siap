<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\AcademicYearContext;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeAssignmentController extends Controller
{
    /**
     * Display a listing of employee assignments across all modules.
     */
    public function index(Request $request): View
    {
        try {
            $currentYear = app(AcademicYearContext::class)->getViewingYear();
        } catch (\Throwable) {
            $currentYear = AcademicYear::getActive()?->name
                ?? SchoolSetting::getMasterYear()
                ?? '2026/2027';
        }

        $filterYear = $request->input('academic_year', $currentYear);
        $filterRoleId = $request->input('role_id');
        $filterStatus = $request->input('status', 'active');
        $filterUserId = $request->input('user_id');
        $search = $request->input('search');

        $query = UserRoleAssignment::with([
            'user',
            'role',
            'department',
            'academicYear',
            'assignedBy',
            'context',
        ]);

        if ($filterYear !== 'all' && ! empty($filterYear)) {
            $query->where(function ($q) use ($filterYear) {
                $q->where('academic_year', $filterYear)
                    ->orWhereNull('academic_year');
            });
        }

        if (! empty($filterRoleId)) {
            $query->where('role_id', $filterRoleId);
        }

        if (! empty($filterUserId)) {
            $query->where('user_id', $filterUserId);
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('sk_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('nip', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    })
                    ->orWhereHas('role', function ($rq) use ($search) {
                        $rq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $today = now()->toDateString();
        if ($filterStatus === 'active') {
            $query->where('status', true)
                ->where(function ($q) use ($today) {
                    $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today);
                })
                ->where(function ($q) use ($today) {
                    $q->whereNull('valid_until')->orWhere('valid_until', '>=', $today);
                });
        } elseif ($filterStatus === 'inactive') {
            $query->where('status', false);
        } elseif ($filterStatus === 'expired') {
            $query->where('status', true)
                ->whereNotNull('valid_until')
                ->where('valid_until', '<', $today);
        } elseif ($filterStatus === 'upcoming') {
            $query->where('status', true)
                ->whereNotNull('valid_from')
                ->where('valid_from', '>', $today);
        }

        $assignments = $query->latest('id')->paginate(15)->withQueryString();

        // Calculate summary metrics for the active year context
        $activeBaseQuery = UserRoleAssignment::where('status', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $today);
            });

        if ($filterYear !== 'all' && ! empty($filterYear)) {
            $activeBaseQuery->where(function ($q) use ($filterYear) {
                $q->where('academic_year', $filterYear)->orWhereNull('academic_year');
            });
        }

        $totalActive = (clone $activeBaseQuery)->count();
        $totalUsersAssigned = (clone $activeBaseQuery)->distinct('user_id')->count('user_id');

        $multiAssignedUserIds = (clone $activeBaseQuery)
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id');
        $multiAssignedUsers = count($multiAssignedUserIds);

        $expiringCount = UserRoleAssignment::where('status', true)
            ->whereNotNull('valid_until')
            ->where('valid_until', '>=', $today)
            ->where('valid_until', '<=', now()->addDays(30)->toDateString())
            ->count();

        $roles = Role::orderBy('name')->get();
        $users = User::operationalPersonnel()->orderBy('name')->get();
        $academicYears = AcademicYear::orderByDesc('start_date')->get();
        $classrooms = Classroom::where('is_active', true)->orderBy('name')->get();
        $departments = Department::orderBy('name')->get();

        return view('assignments.index', compact(
            'assignments',
            'roles',
            'users',
            'academicYears',
            'classrooms',
            'departments',
            'currentYear',
            'filterYear',
            'filterRoleId',
            'filterStatus',
            'filterUserId',
            'search',
            'totalActive',
            'totalUsersAssigned',
            'multiAssignedUsers',
            'expiringCount'
        ));
    }

    /**
     * Store a newly created employee assignment.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role_id' => ['required', 'exists:roles,id'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'context_type' => ['nullable', 'string', 'max:150'],
            'context_id' => ['nullable', 'integer'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'sk_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $contextType = match ($validated['context_type'] ?? null) {
            'classroom', Classroom::class => Classroom::class,
            'department', Department::class => Department::class,
            default => $validated['context_type'] ?? null,
        };

        $ay = null;
        if (! empty($validated['academic_year'])) {
            $ay = AcademicYear::where('name', $validated['academic_year'])->first();
        }

        UserRoleAssignment::create([
            'user_id' => $validated['user_id'],
            'role_id' => $validated['role_id'],
            'academic_year_id' => $ay?->id,
            'academic_year' => $validated['academic_year'] ?? SchoolSetting::getMasterYear(),
            'context_type' => $contextType,
            'context_id' => $contextType ? ($validated['context_id'] ?? null) : null,
            'valid_from' => $validated['valid_from'] ?? null,
            'valid_until' => $validated['valid_until'] ?? null,
            'status' => true,
            'sk_number' => $validated['sk_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'assigned_by' => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Penugasan pegawai berhasil ditambahkan.');
    }

    /**
     * Update the specified employee assignment.
     */
    public function update(Request $request, UserRoleAssignment $assignment): RedirectResponse
    {
        $validated = $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'context_type' => ['nullable', 'string', 'max:150'],
            'context_id' => ['nullable', 'integer'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'sk_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'boolean'],
        ]);

        $contextType = match ($validated['context_type'] ?? null) {
            'classroom', Classroom::class => Classroom::class,
            'department', Department::class => Department::class,
            default => $validated['context_type'] ?? null,
        };

        $ay = null;
        if (! empty($validated['academic_year'])) {
            $ay = AcademicYear::where('name', $validated['academic_year'])->first();
        }

        $assignment->update([
            'role_id' => $validated['role_id'],
            'academic_year_id' => $ay?->id,
            'academic_year' => $validated['academic_year'] ?? $assignment->academic_year,
            'context_type' => $contextType,
            'context_id' => $contextType ? ($validated['context_id'] ?? null) : null,
            'valid_from' => $validated['valid_from'] ?? null,
            'valid_until' => $validated['valid_until'] ?? null,
            'status' => $request->has('status') ? (bool) $request->input('status') : $assignment->status,
            'sk_number' => $validated['sk_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Penugasan pegawai berhasil diperbarui.');
    }

    /**
     * Officially close or revoke an assignment, retaining historical record.
     */
    public function close(Request $request, UserRoleAssignment $assignment): RedirectResponse
    {
        $validated = $request->validate([
            'closing_date' => ['nullable', 'date'],
            'closing_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $closingDate = $validated['closing_date'] ?? now()->toDateString();
        $closingNote = $validated['closing_notes'] ? ' [Ditutup: '.$validated['closing_notes'].']' : ' [Ditutup secara resmi pada '.$closingDate.']';

        $assignment->update([
            'status' => false,
            'valid_until' => $closingDate,
            'notes' => trim(($assignment->notes ?? '').$closingNote),
        ]);

        return redirect()->back()->with('success', 'Penugasan resmi telah ditutup dan tetap tersimpan dalam riwayat.');
    }

    /**
     * Toggle the active status of an assignment.
     */
    public function toggle(UserRoleAssignment $assignment): RedirectResponse
    {
        $assignment->status = ! $assignment->status;
        $assignment->save();

        $statusText = $assignment->status ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->back()->with('success', "Penugasan pegawai berhasil {$statusText}.");
    }

    /**
     * Remove an erroneous assignment from storage.
     */
    public function destroy(UserRoleAssignment $assignment): RedirectResponse
    {
        $assignment->delete();

        return redirect()->back()->with('success', 'Data penugasan pegawai berhasil dihapus.');
    }

    /**
     * Batch close assignments for a specific academic year.
     */
    public function bulkClose(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'academic_year' => ['required', 'string'],
            'closing_date' => ['required', 'date'],
            'closing_notes' => ['nullable', 'string', 'max:500'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        $query = UserRoleAssignment::where('status', true)
            ->where('academic_year', $validated['academic_year']);

        if (! empty($validated['role_ids'])) {
            $query->whereIn('role_id', $validated['role_ids']);
        }

        $closingNote = $validated['closing_notes']
            ? ' [Ditutup massal: '.$validated['closing_notes'].']'
            : ' [Ditutup massal pada '.$validated['closing_date'].']';

        $assignments = $query->get();
        $count = 0;

        foreach ($assignments as $a) {
            $a->update([
                'status' => false,
                'valid_until' => $validated['closing_date'],
                'notes' => trim(($a->notes ?? '').$closingNote),
            ]);
            $count++;
        }

        return redirect()->back()->with('success', "Berhasil menutup {$count} penugasan pegawai untuk Tahun Ajaran {$validated['academic_year']}.");
    }

    /**
     * Batch rollover assignments from source year to target year.
     */
    public function rollover(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_academic_year' => ['required', 'string'],
            'target_academic_year' => ['required', 'string', 'different:source_academic_year'],
            'new_sk_number' => ['nullable', 'string', 'max:100'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'close_previous' => ['nullable', 'boolean'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        $sourceYear = $validated['source_academic_year'];
        $targetYear = $validated['target_academic_year'];
        $closePrevious = ! empty($validated['close_previous']);

        $targetAy = AcademicYear::where('name', $targetYear)->first();

        $query = UserRoleAssignment::where('status', true)
            ->where('academic_year', $sourceYear);

        if (! empty($validated['role_ids'])) {
            $query->whereIn('role_id', $validated['role_ids']);
        }

        $activeAssignments = $query->get();

        if ($activeAssignments->isEmpty()) {
            return redirect()->back()->with('error', "Tidak ada penugasan aktif yang ditemukan pada Tahun Ajaran {$sourceYear}.");
        }

        $createdCount = 0;
        $closedCount = 0;

        $today = now()->toDateString();
        $closingDate = ! empty($validated['valid_from']) ? Carbon::parse($validated['valid_from'])->subDay()->toDateString() : $today;

        foreach ($activeAssignments as $assignment) {
            $exists = UserRoleAssignment::where('user_id', $assignment->user_id)
                ->where('role_id', $assignment->role_id)
                ->where('academic_year', $targetYear)
                ->when($assignment->context_type, fn ($q) => $q->where('context_type', $assignment->context_type)->where('context_id', $assignment->context_id))
                ->exists();

            if (! $exists) {
                UserRoleAssignment::create([
                    'user_id' => $assignment->user_id,
                    'role_id' => $assignment->role_id,
                    'department_id' => $assignment->department_id,
                    'academic_year_id' => $targetAy?->id,
                    'academic_year' => $targetYear,
                    'context_type' => $assignment->context_type,
                    'context_id' => $assignment->context_id,
                    'valid_from' => $validated['valid_from'] ?? null,
                    'valid_until' => $validated['valid_until'] ?? null,
                    'status' => true,
                    'sk_number' => $validated['new_sk_number'] ?: $assignment->sk_number,
                    'notes' => 'Rollover dari TA '.$sourceYear.($assignment->notes ? ' - '.$assignment->notes : ''),
                    'assigned_by' => auth()->id(),
                ]);
                $createdCount++;
            }

            if ($closePrevious) {
                $assignment->update([
                    'status' => false,
                    'valid_until' => $closingDate,
                    'notes' => trim(($assignment->notes ?? '')." [Ditutup via rollover ke {$targetYear}]"),
                ]);
                $closedCount++;
            }
        }

        $msg = "Berhasil membuat {$createdCount} penugasan baru di Tahun Ajaran {$targetYear}.";
        if ($closePrevious) {
            $msg .= " Serta menutup resmi {$closedCount} penugasan lama di Tahun Ajaran {$sourceYear}.";
        }

        return redirect()->back()->with('success', $msg);
    }
}
