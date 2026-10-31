<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pengingat 10 Menit Sebelum Mengajar KBM via Telegram
Schedule::command('telegram:teaching-reminder')
    ->everyMinute()
    ->withoutOverlapping();
