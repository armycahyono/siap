<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    /**
     * Run the test user seeds.
     * This seeder is intended to run only in the testing environment.
     */
    public function run(): void
    {
        // Ensure required roles exist.
        $wakaRole = Role::where('slug', 'waka_kurikulum')->firstOrFail();
        $guruRole = Role::where('slug', 'guru')->firstOrFail();

        // Create or update the Waka user.
        User::firstOrCreate(
            ['username' => 'waka'],
            [
                'name' => 'Waka Kurikulum',
                'email' => 'waka@smkpgrikras.sch.id',
                'password' => Hash::make('password'),
                'role_id' => $wakaRole->id,
                'is_active' => true,
            ]
        );

        // Create or update a Guru user.
        User::firstOrCreate(
            ['username' => 'guru1'],
            [
                'name' => 'Guru Contoh',
                'email' => 'guru1@smkpgrikras.sch.id',
                'password' => Hash::make('password'),
                'role_id' => $guruRole->id,
                'is_active' => true,
            ]
        );
    }
}
