<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    /**
     * Display a listing of users with filters and stats.
     */
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $roleId = $request->query('role_id');
        $statusFilter = $request->query('status');
        $typeFilter = $request->query('type'); // 'admin', 'guru', 'staf'

        $fifteenMinutesAgo = now()->subMinutes(15)->timestamp;

        // Statistics
        $totalUsers = User::count();
        $superAdminCount = User::where('position', 'administrator')->orWhereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->count();
        $guruCount = User::where('position', 'guru')->count();
        $operatorCount = User::whereHas('role', fn ($q) => $q->where('slug', 'like', 'operator%'))
            ->orWhereHas('activeRoleAssignments.role', fn ($q) => $q->where('slug', 'like', 'operator%'))
            ->distinct()
            ->count();
        $stafCount = User::where('position', 'staf')->count();
        $activeCount = User::where('is_active', true)->count();
        $inactiveCount = User::where('is_active', false)->count();

        // Main Query
        $query = User::with([
            'role',
            'structuralPositions',
            'roleAssignments.role',
            'roleAssignments.context',
            'activeRoleAssignments.role',
            'activeRoleAssignments.context',
        ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('nigs', 'like', "%{$search}%")
                    ->orWhere('position', 'like', "%{$search}%");
            });
        }

        if ($roleId) {
            $query->where(function ($q) use ($roleId) {
                $q->where('role_id', $roleId)
                    ->orWhereHas('activeRoleAssignments', fn ($sub) => $sub->where('role_id', $roleId));
            });
        }

        if ($statusFilter !== null && $statusFilter !== '') {
            $query->where('is_active', (bool) $statusFilter);
        }

        if ($typeFilter === 'admin') {
            $query->where('position', 'administrator');
        } elseif ($typeFilter === 'guru') {
            $query->where('position', 'guru');
        } elseif ($typeFilter === 'operator') {
            $query->where(function ($q) {
                $q->whereHas('role', fn ($r) => $r->where('slug', 'like', 'operator%'))
                    ->orWhereHas('activeRoleAssignments.role', fn ($r) => $r->where('slug', 'like', 'operator%'));
            });
        } elseif ($typeFilter === 'staf') {
            $query->where('position', 'staf');
        }

        $users = $query->orderBy('name')->paginate(20)->withQueryString();

        // Check active sessions
        $onlineUserIds = DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $fifteenMinutesAgo)
            ->pluck('user_id')
            ->flip()
            ->toArray();

        $roles = Role::orderBy('id')->get();
        $classrooms = Classroom::orderBy('name')->get();
        $assignmentRoles = Role::whereNotIn('slug', ['super_admin', 'guru', 'staf', 'adm2'])->orderBy('name')->get();
        $academicYears = AcademicYear::orderByDesc('name')->get();
        $currentAcademicYear = SchoolSetting::getCurrentAcademicYear() ?? '2026/2027';

        return view('superadmin.users.index', compact(
            'users',
            'roles',
            'classrooms',
            'assignmentRoles',
            'academicYears',
            'currentAcademicYear',
            'search',
            'roleId',
            'statusFilter',
            'typeFilter',
            'totalUsers',
            'superAdminCount',
            'guruCount',
            'operatorCount',
            'stafCount',
            'activeCount',
            'inactiveCount',
            'onlineUserIds'
        ));
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'role_id' => ['required', 'exists:roles,id'],
            'position' => ['required', Rule::in(['guru', 'staf', 'administrator'])],
            'nigs' => ['nullable', 'string', 'max:50'],
            'nip' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'short_name' => $validated['short_name'] ?? null,
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'],
            'position' => $validated['position'],
            'nigs' => $validated['nigs'] ?? null,
            'nip' => $validated['nip'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        ActivityLogger::log(
            'create_user',
            "Menambahkan akun pengguna baru: {$user->name} ({$user->username}) dengan peran {$user->role?->name}.",
            $user
        );

        return redirect()->route('users.index')
            ->with('success', "Pengguna {$user->name} ({$user->username}) berhasil ditambahkan sebagai {$user->role?->name}.");
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', Rule::unique('users')->ignore($user->id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role_id' => ['required', 'exists:roles,id'],
            'position' => ['required', Rule::in(['guru', 'staf', 'administrator'])],
            'nigs' => ['nullable', 'string', 'max:50'],
            'nip' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Guard: Prevent a Super Admin from demoting themselves if they are the only Super Admin
        if ($user->id === auth()->id() && (int) $validated['role_id'] !== (int) $user->role_id) {
            $otherSuperAdmins = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))
                ->where('id', '!=', $user->id)
                ->where('is_active', true)
                ->count();

            if ($otherSuperAdmins === 0) {
                return back()->with('error', 'Anda tidak dapat menurunkan peran Super Admin Anda sendiri karena tidak ada Super Admin aktif lainnya di sistem!');
            }
        }

        $oldRole = $user->role?->name;

        $user->update([
            'name' => $validated['name'],
            'short_name' => $validated['short_name'] ?? $user->short_name,
            'username' => $validated['username'],
            'email' => $validated['email'],
            'role_id' => $validated['role_id'],
            'position' => $validated['position'],
            'nigs' => $validated['nigs'] ?? null,
            'nip' => $validated['nip'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $user->is_active,
        ]);

        $newRole = $user->fresh()->role?->name;

        ActivityLogger::log(
            'update_user',
            "Memperbarui data pengguna: {$user->name} ({$user->username}). Peran: {$oldRole} -> {$newRole}.",
            $user
        );

        return redirect()->route('users.index')
            ->with('success', "Data pengguna {$user->name} berhasil diperbarui.");
    }

    /**
     * Reset the password for the specified user.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        ActivityLogger::log(
            'reset_password',
            "Mereset password untuk pengguna: {$user->name} ({$user->username}).",
            $user
        );

        return redirect()->route('users.index')
            ->with('success', "Password untuk pengguna {$user->name} ({$user->username}) berhasil diubah.");
    }

    /**
     * Toggle active/inactive status of a user.
     */
    public function toggleStatus(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri!');
        }

        $newStatus = ! $user->is_active;
        $user->update(['is_active' => $newStatus]);

        $statusText = $newStatus ? 'diaktifkan kembali' : 'dinonaktifkan';

        ActivityLogger::log(
            'toggle_status',
            "Mengubah status akun {$user->name} ({$user->username}) menjadi {$statusText}.",
            $user
        );

        return redirect()->route('users.index')
            ->with('success', "Akun {$user->name} berhasil {$statusText}.");
    }

    /**
     * Delete the specified user from storage.
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri!');
        }

        // Check if user is the last active Super Admin
        if ($user->isSuperAdmin()) {
            $otherSuperAdmins = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))
                ->where('id', '!=', $user->id)
                ->where('is_active', true)
                ->count();

            if ($otherSuperAdmins === 0) {
                return back()->with('error', 'Akun Super Admin ini tidak dapat dihapus karena merupakan satu-satunya Super Admin aktif di sistem!');
            }
        }

        // Check if user has active supervision or teaching schedules
        if ($user->supervisionsAsTeacher()->exists() || $user->supervisionsAsSupervisor()->exists() || $user->lessonSchedules()->exists()) {
            return back()->with('error', "Pengguna {$user->name} memiliki riwayat supervisi atau jadwal mengajar resmi. Silakan nonaktifkan akun sebagai gantinya untuk menjaga integritas data.");
        }

        $userName = $user->name;
        $user->delete();

        ActivityLogger::log('delete_user', "Menghapus akun pengguna: {$userName}.");

        return redirect()->route('users.index')
            ->with('success', "Pengguna {$userName} berhasil dihapus dari sistem.");
    }

    /**
     * Store a new contextual role assignment for the specified user (Fase 2).
     */
    public function storeAssignment(Request $request, User $user): RedirectResponse
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
        ]);

        $ay = null;
        if (! empty($validated['academic_year'])) {
            $ay = AcademicYear::where('name', $validated['academic_year'])->first();
        }

        $assignment = $user->roleAssignments()->create([
            'role_id' => $validated['role_id'],
            'academic_year_id' => $ay?->id,
            'academic_year' => $validated['academic_year'] ?? SchoolSetting::getCurrentAcademicYear() ?? '2026/2027',
            'context_type' => $validated['context_type'] ?: null,
            'context_id' => $validated['context_id'] ?: null,
            'valid_from' => $validated['valid_from'] ?? null,
            'valid_until' => $validated['valid_until'] ?? null,
            'sk_number' => $validated['sk_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => true,
            'assigned_by' => auth()->id(),
        ]);

        $roleName = $assignment->role?->name;
        ActivityLogger::log(
            'assign_role',
            "Memberikan penugasan peran '{$roleName}' kepada {$user->name} ({$user->username}).",
            $assignment
        );

        return back()->with('success', "Penugasan peran '{$roleName}' berhasil diberikan kepada {$user->name}.");
    }

    /**
     * Delete a contextual role assignment.
     */
    public function destroyAssignment(UserRoleAssignment $assignment): RedirectResponse
    {
        $userName = $assignment->user?->name;
        $roleName = $assignment->role?->name;

        $assignment->delete();

        ActivityLogger::log(
            'remove_role_assignment',
            "Menghapus penugasan peran '{$roleName}' dari {$userName}."
        );

        return back()->with('success', "Penugasan peran '{$roleName}' untuk {$userName} berhasil dihapus.");
    }

    /**
     * Toggle active status of a contextual role assignment.
     */
    public function toggleAssignmentStatus(UserRoleAssignment $assignment): RedirectResponse
    {
        $assignment->status = ! $assignment->status;
        $assignment->save();

        $statusText = $assignment->status ? 'diaktifkan kembali' : 'dinonaktifkan';
        $roleName = $assignment->role?->name;
        $userName = $assignment->user?->name;

        ActivityLogger::log(
            'toggle_role_assignment',
            "Status penugasan peran '{$roleName}' untuk {$userName} {$statusText}."
        );

        return back()->with('success', "Penugasan peran '{$roleName}' untuk {$userName} berhasil {$statusText}.");
    }
}
