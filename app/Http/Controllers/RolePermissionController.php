<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RolePermissionController extends Controller
{
    public function index(): View
    {
        $roles = Role::with('permissions')->get();
        $permissions = Permission::all()->groupBy('group');

        return view('roles.index', compact('roles', 'permissions'));
    }

    public function update(Request $request): RedirectResponse
    {
        $permissionsMap = $request->input('permissions', []); // [role_id => [perm_id_1, perm_id_2]]

        $roles = Role::all();

        foreach ($roles as $role) {
            // Super admin always retains everything
            if ($role->slug === 'super_admin') {
                $role->permissions()->sync(Permission::pluck('id')->toArray());

                continue;
            }

            $selectedPermIds = $permissionsMap[$role->id] ?? [];
            $role->permissions()->sync($selectedPermIds);
        }

        return back()->with('success', 'Matriks hak akses (Role & Permission) berhasil disimpan.');
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:50', 'unique:roles,slug'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        Role::create($data);

        return back()->with('success', "Peran/Role baru '{$data['name']}' berhasil ditambahkan.");
    }
}
