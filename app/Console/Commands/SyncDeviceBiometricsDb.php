<?php

namespace App\Console\Commands;

use App\Models\AttendanceBiometric;
use App\Models\AttendanceDeviceBiometric;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:sync-device-biometrics-db')]
#[Description('Command description')]
class SyncDeviceBiometricsDb extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $all = AttendanceBiometric::all();
        foreach (['AEWD233160028', 'A3AG185060085'] as $sn) {
            foreach ($all as $b) {
                AttendanceDeviceBiometric::updateOrCreate(
                    [
                        'device_sn' => $sn,
                        'pin' => $b->pin,
                        'bio_type' => $b->bio_type,
                        'finger_id' => $b->finger_id,
                    ],
                    [
                        'synced_at' => now(),
                    ]
                );
            }
        }
        $this->info('Synced device biometrics in database.');

        return 0;
    }
}
