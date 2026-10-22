<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Hanya membuat akun Super Administrator awal untuk akses pertama kali.
     * Data guru & staf diinput murni melalui sistem / import.
     */
    public function run(): void
    {
        $superAdminRole = Role::where('slug', 'super_admin')->first();

        User::firstOrCreate(
            ['username' => 'superadmin'],
            [
                'name' => 'Administrator Sistem',
                'email' => 'admin@smkpgrikras.sch.id',
                'password' => Hash::make('password'),
                'role_id' => $superAdminRole?->id,
                'phone' => '081234567890',
                'position' => 'Administrator Sistem',
                'is_active' => true,
            ]
        );
    }
}
