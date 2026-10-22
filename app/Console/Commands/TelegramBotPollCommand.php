<?php

namespace App\Console\Commands;

use App\Http\Controllers\TelegramBotWebhookController;
use App\Models\SchoolSetting;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class TelegramBotPollCommand extends Command
{
    protected $signature = 'telegram:poll {--once : Proses antrean pesan tertunda satu kali lalu selesai}';

    protected $description = 'Jalankan listener Bot Telegram lokal (Polling mode untuk localhost/Laragon)';

    public function handle(TelegramService $telegramService, TelegramBotWebhookController $webhookController): int
    {
        $setting = SchoolSetting::getSetting();
        $token = $setting->telegram_bot_token ?: config('services.telegram.bot_token');

        if (empty($token)) {
            $this->error('Bot Token Telegram belum dikonfigurasi di Pengaturan Sekolah.');

            return Command::FAILURE;
        }

        $botUsername = $setting->telegram_bot_username ? '@'.ltrim($setting->telegram_bot_username, '@') : 'Bot';
        $this->info("=== BOT TELEGRAM POLLING STARTED ({$botUsername}) ===");
        $this->comment('Tekan Ctrl+C untuk menghentikan.');
        $this->line('Menunggu pesan masuk dari Telegram...');

        $offset = 0;
        $isOnce = (bool) $this->option('once');

        while (true) {
            try {
                $response = Http::timeout(25)->get("https://api.telegram.org/bot{$token}/getUpdates", [
                    'offset' => $offset,
                    'timeout' => 20,
                ]);

                if ($response->successful() && $response->json('ok')) {
                    $updates = $response->json('result') ?? [];

                    foreach ($updates as $update) {
                        $updateId = $update['update_id'];
                        $offset = $updateId + 1;

                        $sender = $update['message']['from']['first_name'] ?? ($update['callback_query']['from']['first_name'] ?? 'User');
                        $text = $update['message']['text'] ?? ($update['callback_query']['data'] ?? '[Media/Button]');

                        $this->info(date('[H:i:s]')." Pesan dari {$sender}: {$text}");

                        // Dispatch update to webhook controller
                        $fakeRequest = Request::create('/telegram/webhook', 'POST', $update);
                        $webhookController->handle($fakeRequest);
                    }
                }
            } catch (Throwable $e) {
                $this->error('Error polling: '.$e->getMessage());
                sleep(2);
            }

            if ($isOnce) {
                break;
            }

            usleep(500000); // 0.5s pause
        }

        return Command::SUCCESS;
    }
}
