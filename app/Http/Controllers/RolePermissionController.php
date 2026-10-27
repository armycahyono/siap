<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RolePermissionController extends Controller
{
    /**
     * Authorize that the current user has permission to manage roles.
     */
    protected function authorizeManageRoles(): void
    {
        if (! Auth::user()?->hasPermission('manage_roles')) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengelola peran dan hak akses.');
        }
    }

    public function index(): View
    {
        $this->authorizeManageRoles();

        $roles = Role::with('permissions')->get();
        $permissions = Permission::all()->groupBy('group');

        return view('roles.index', compact('roles', 'permissions'));
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeManageRoles();

        $request->validate([
            'roles_present' => ['nullable', 'array'],
            'roles_present.*' => ['integer', 'distinct', 'exists:roles,id'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['nullable', 'array'],
            'permissions.*.*' => ['integer', 'exists:permissions,id'],
        ]);

        $rolesPresent = $request->input('roles_present');
        $permissionsMap = $request->input('permissions', []);

        // Validasi bahwa setiap key di permissionsMap adalah ID role yang sah
        if (! empty($permissionsMap)) {
            $validRoleIds = Role::pluck('id')->map(fn ($id) => (int) $id)->all();
            foreach (array_keys($permissionsMap) as $roleKey) {
                if (! is_numeric($roleKey) || ! in_array((int) $roleKey, $validRoleIds, true)) {
                    throw ValidationException::withMessages([
                        'permissions' => "ID peran '{$roleKey}' tidak valid.",
                    ]);
                }
            }
        }

        // Jika roles_present dikirim, pastikan tidak ada key permissions yang tidak tercantum dalam roles_present
        if (is_array($rolesPresent) && ! empty($rolesPresent)) {
            $rolesPresentInt = array_map('intval', $rolesPresent);
            $submittedRoleKeys = array_map('intval', array_keys($permissionsMap));
            $inconsistentKeys = array_diff($submittedRoleKeys, $rolesPresentInt);
            if (! empty($inconsistentKeys)) {
                throw ValidationException::withMessages([
                    'permissions' => 'Terdapat peran pada data hak akses yang tidak terdaftar dalam lingkup formulir (roles_present).',
                ]);
            }
        }

        // Tentukan peran yang diproses secara aman
        if (is_array($rolesPresent) && ! empty($rolesPresent)) {
            // Permintaan formulir matriks yang menyertakan daftar peran yang sedang dirender
            $rolesToProcess = Role::whereIn('id', $rolesPresent)->get();
        } elseif (! empty($permissionsMap)) {
            // Permintaan langsung / API / pengujian yang menentukan peran target secara eksplisit
            $rolesToProcess = Role::whereIn('id', array_keys($permissionsMap))->get();
        } else {
            throw ValidationException::withMessages([
                'permissions' => 'Data matriks hak akses tidak ditemukan atau kosong.',
            ]);
        }

        // Proteksi self-lockout: Cegah pengguna non-super admin mencabut izin manage_roles dari seluruh peran miliknya
        $currentUser = Auth::user();
        if ($currentUser && ! $currentUser->hasRole('super_admin')) {
            $manageRolesPerm = Permission::where('slug', 'manage_roles')->first();
            if ($manageRolesPerm) {
                $userRoleIds = $currentUser->allRoles()->pluck('id')->all();
                $wouldRetainManageRoles = false;

                foreach ($userRoleIds as $roleId) {
                    if ($rolesToProcess->contains('id', $roleId)) {
                        $submittedPerms = $permissionsMap[$roleId] ?? [];
                        if (in_array($manageRolesPerm->id, array_map('intval', (array) $submittedPerms), true)) {
                            $wouldRetainManageRoles = true;
                            break;
                        }
                    } else {
                        $existingRole = Role::find($roleId);
                        if ($existingRole && $existingRole->permissions()->where('permissions.id', $manageRolesPerm->id)->exists()) {
                            $wouldRetainManageRoles = true;
                            break;
                        }
                    }
                }

                if (! $wouldRetainManageRoles) {
                    throw ValidationException::withMessages([
                        'permissions' => 'Anda tidak dapat mencabut izin kelola hak akses (manage_roles) dari seluruh peran Anda sendiri.',
                    ]);
                }
            }
        }

        $superAdminRole = Role::where('slug', 'super_admin')->first();

        DB::transaction(function () use ($rolesToProcess, $permissionsMap, $superAdminRole) {
            // Role super_admin selalu mempertahankan seluruh permission yang ada di sistem
            if ($superAdminRole) {
                $superAdminRole->permissions()->sync(Permission::pluck('id')->toArray());
            }

            foreach ($rolesToProcess as $role) {
                // Lewati super_admin agar tidak dapat ditimpa dari payload formulir
                if ($role->slug === 'super_admin') {
                    continue;
                }

                $selectedPermIds = $permissionsMap[$role->id] ?? [];
                $role->permissions()->sync($selectedPermIds);
            }
        });

        return back()->with('success', 'Matriks hak akses (Role & Permission) berhasil disimpan.');
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $this->authorizeManageRoles();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:roles,slug'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        Role::create($data);

        return back()->with('success', "Peran/Role baru '{$data['name']}' berhasil ditambahkan.");
    }
}
