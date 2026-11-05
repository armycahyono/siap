<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permissions = Permission::whereIn('slug', [
            'access_piket',
            'operate_piket',
            'view_piket_reports',
        ])->get();

        $roles = Role::whereIn('slug', ['waka_sarpras', 'waka_humas'])->get();

        foreach ($roles as $role) {
            foreach ($permissions as $permission) {
                if (! $role->permissions()->where('permissions.id', $permission->id)->exists()) {
                    $role->permissions()->attach($permission->id);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissions = Permission::whereIn('slug', [
            'access_piket',
            'operate_piket',
            'view_piket_reports',
        ])->pluck('id');

        $roles = Role::whereIn('slug', ['waka_sarpras', 'waka_humas'])->get();

        foreach ($roles as $role) {
            $role->permissions()->detach($permissions);
        }
    }
};
