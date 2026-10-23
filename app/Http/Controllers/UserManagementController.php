<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
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
        $superAdminCount = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->count();
        $guruCount = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['guru', 'waka_kurikulum', 'supervisor', 'kepala_sekolah']))->count();
        $operatorCount = User::whereHas('role', fn ($q) => $q->where('slug', 'like', 'operator%'))->count();
        $activeCount = User::where('is_active', true)->count();
        $inactiveCount = User::where('is_active', false)->count();

        // Main Query
        $query = User::with('role');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('position', 'like', "%{$search}%");
            });
        }

        if ($roleId) {
            $query->where('role_id', $roleId);
        }

        if ($statusFilter !== null && $statusFilter !== '') {
            $query->where('is_active', (bool) $statusFilter);
        }

        if ($typeFilter === 'admin') {
            $query->whereHas('role', fn ($q) => $q->where('slug', 'super_admin'));
        } elseif ($typeFilter === 'guru') {
            $query->whereHas('role', fn ($q) => $q->whereIn('slug', ['guru', 'waka_kurikulum', 'supervisor', 'kepala_sekolah']));
        } elseif ($typeFilter === 'operator') {
            $query->whereHas('role', fn ($q) => $q->where('slug', 'like', 'operator%'));
        } elseif ($typeFilter === 'staf') {
            $query->whereDoesntHave('role', fn ($q) => $q->where('slug', 'super_admin')->orWhere('slug', 'like', 'operator%'))
                ->where(function ($q) {
                    $q->where('position', 'like', '%administrasi%')
                        ->orWhere('position', 'like', '%satpam%')
                        ->orWhere('position', 'like', '%penjaga%')
                        ->orWhere('position', 'like', '%toolman%')
                        ->orWhere('position', 'like', '%tata usaha%')
                        ->orWhere('position', 'like', '%kebun%')
                        ->orWhere('position', 'like', '%staf%');
                });
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

        return view('superadmin.users.index', compact(
            'users',
            'roles',
            'search',
            'roleId',
            'statusFilter',
            'typeFilter',
            'totalUsers',
            'superAdminCount',
            'guruCount',
            'operatorCount',
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
            'position' => ['nullable', 'string', 'max:255'],
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
            'position' => $validated['position'] ?? null,
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
            'position' => ['nullable', 'string', 'max:255'],
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
            'position' => $validated['position'] ?? null,
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
}
