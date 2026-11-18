<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $roleMap = [
            'operator_kurikulum' => 'Tim Kurikulum',
            'operator_kesiswaan' => 'Tim Kesiswaan',
            'operator_sarpras' => 'Tim Sarpras',
            'operator_humas' => 'Tim Humas',
        ];

        foreach ($roleMap as $slug => $name) {
            Role::where('slug', $slug)->update(['name' => $name]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $roleMap = [
            'operator_kurikulum' => 'Operator Kurikulum',
            'operator_kesiswaan' => 'Operator Kesiswaan',
            'operator_sarpras' => 'Operator Sarpras',
            'operator_humas' => 'Operator Humas',
        ];

        foreach ($roleMap as $slug => $name) {
            Role::where('slug', $slug)->update(['name' => $name]);
        }
    }
};
