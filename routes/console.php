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

// Akun VPN yang 3 bulan berturut-turut tidak diperpanjang kehilangan semua routernya.
Schedule::command('vpn:purge-lapsed-routers')
    ->dailyAt('00:20')
    ->timezone($appTimezone)
    ->withoutOverlapping(60);

// Sebulan setelah masa gratis berakhir: kirim tagihan WhatsApp dan hapus secret dari CHR.
Schedule::command('vpn:close-trials')
    ->dailyAt('00:25')
    ->timezone($appTimezone)
    ->withoutOverlapping(60);

// Bersihkan voucher hotspot terpakai dari RouterOS & aplikasi
Schedule::command('hotspot:purge-used')->everyFiveMinutes();

// Pengingat tagihan belum lunas (WhatsApp / Telegram terikat)
Schedule::command('messaging:remind-invoices')->dailyAt('08:00');

// Ikatkan nomor HP pelanggan ke bot WhatsApp tanpa perintah daftar
Schedule::command('messaging:bind-whatsapp')
    ->dailyAt('00:10')
    ->timezone($appTimezone)
    ->withoutOverlapping(30);

// Antrian WhatsApp tagihan/pengingat — jeda acak anti-spam
Schedule::command('messaging:send-outbox')
    ->everyMinute()
    ->withoutOverlapping(5);

// Pantau sesi PPPoE connected/disconnected → Telegram admin
Schedule::command('pppoe:watch-sessions')->everyMinute()->withoutOverlapping(5);

// Akumulasi pemakaian RX/TX per pelanggan. Bulan baru mulai dari nol tanggal 1.
Schedule::command('pppoe:collect-usage')
    ->everyFiveMinutes()
    ->timezone($appTimezone)
    ->withoutOverlapping(10);
