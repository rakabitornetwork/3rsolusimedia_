<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Jalankan sinkronisasi & auto isolir pelanggan jatuh tempo setiap 30 menit
Schedule::command('pppoe:sync-overdue')->everyThirtyMinutes();

// Bersihkan voucher hotspot terpakai dari RouterOS & aplikasi
Schedule::command('hotspot:purge-used')->everyFiveMinutes();

// Pengingat tagihan belum lunas (WhatsApp / Telegram terikat)
Schedule::command('messaging:remind-invoices')->dailyAt('08:00');

