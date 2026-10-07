<?php

use App\Models\PageSection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (PageSection::query()->where('key', 'vpn')->exists()) {
            return;
        }

        PageSection::query()->create([
            'key' => 'vpn',
            'label' => 'Layanan VPN Tunnel',
            'title' => 'VPN Tunnel untuk mengalihkan trafik Speedtest',
            'subtitle' => 'Layanan VPN Tunnel',
            'body' => 'Layanan VPN Tunnel dipakai untuk mengalihkan trafik Speedtest dari ISP utama ke tunnel kami. Internet utama tetap jalan. Tes kecepatan yang Anda arahkan keluar lewat VPN Tunnel, bukan lewat ISP yang sedang diukur.',
            'content' => [
                'steps' => [
                    ['title' => 'Daftar', 'description' => 'Isi nama, email, dan WhatsApp. Akun langsung terbuat, tanpa memilih paket.'],
                    ['title' => 'Coba gratis', 'description' => 'Masuk dengan kode OTP WhatsApp dan pakai tunnel selama 3 hari.'],
                    ['title' => 'Jika cocok', 'description' => 'Silahkan lanjut pembayaran via portal pelanggan.'],
                ],
            ],
            'image' => '/images/vpn/office.jpg',
            'image_secondary' => '/images/vpn/router.jpg',
            'cta_label' => 'Daftar VPN',
            'cta_url' => '/vpn/daftar',
            'is_visible' => true,
            'sort_order' => 12,
        ]);
    }

    public function down(): void
    {
        PageSection::query()->where('key', 'vpn')->delete();
    }
};
