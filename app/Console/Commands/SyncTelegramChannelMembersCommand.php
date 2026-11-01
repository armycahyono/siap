<?php

namespace App\Console\Commands;

use App\Models\TelegramChannel;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class SyncTelegramChannelMembersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:sync-channel-members
                            {--channel= : Kode saluran grup tertentu yang ingin diaudit}
                            {--dry-run : Lakukan simulasi tanpa mengeluarkan anggota}
                            {--no-notify : Jangan kirimkan notifikasi penyesuaian via japri bot}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit dan sesuaikan keanggotaan grup Telegram sekolah sesuai data penugasan resmi SIAP (keluarkan anggota yang sudah tidak bertugas)';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegramService): int
    {
        $this->info('Memulai audit dan sinkronisasi keanggotaan grup Telegram...');

        $channelCode = $this->option('channel');
        $dryRun = (bool) $this->option('dry-run');
        $sendNotify = ! $this->option('no-notify');

        if ($dryRun) {
            $this->warn('Mode simulasi (DRY-RUN) aktif: tidak ada anggota yang akan dikeluarkan.');
        }

        if ($channelCode) {
            $channel = TelegramChannel::where('code', $channelCode)->first();
            if (! $channel) {
                $this->error("Saluran grup dengan kode '{$channelCode}' tidak ditemukan.");

                return self::FAILURE;
            }

            $res = $telegramService->syncChannelMembers($channel, $sendNotify, $dryRun);
            if (! $res['success']) {
                $this->error($res['message']);

                return self::FAILURE;
            }

            $this->info($res['message']);
            if (! empty($res['kicked_users'])) {
                $this->line('Daftar akun disesuaikan: '.implode(', ', $res['kicked_users']));
            }

            return self::SUCCESS;
        }

        $res = $telegramService->syncAllChannelMembers($sendNotify, $dryRun);
        $this->info($res['message']);

        if (! empty($res['details'])) {
            $rows = [];
            foreach ($res['details'] as $groupName => $count) {
                $rows[] = [$groupName, $count];
            }
            $this->table(['Nama Saluran Grup', 'Jumlah Disesuaikan / Dikeluarkan'], $rows);
        }

        return self::SUCCESS;
    }
}
