<?php

use App\Support\AppSettings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$appTimezone = 'Asia/Jakarta';
try {
    $appTimezone = (string) (AppSettings::get('app_timezone', 'Asia/Jakarta') ?: 'Asia/Jakarta');
} catch (Throwable) {
    // Bootstrap awal / migrasi: tetap Asia/Jakarta.
}

// Isolir pelanggan yang tanggal jatuh temponya sudah lewat, sekali sehari jam 00:00.
Schedule::command('pppoe:sync-overdue')
    ->dailyAt('00:00')
    ->timezone($appTimezone)
    ->withoutOverlapping(120);

// Bersihkan voucher hotspot terpakai dari RouterOS & aplikasi
Schedule::command('hotspot:purge-used')->everyFiveMinutes();

// Pengingat tagihan belum lunas (WhatsApp / Telegram terikat)
Schedule::command('messaging:remind-invoices')->dailyAt('08:00');

// Antrian WhatsApp tagihan/pengingat — jeda acak anti-spam
Schedule::command('messaging:send-outbox')
    ->everyMinute()
    ->withoutOverlapping(5);

// Pantau sesi PPPoE connected/disconnected → Telegram admin
Schedule::command('pppoe:watch-sessions')->everyMinute()->withoutOverlapping(5);
