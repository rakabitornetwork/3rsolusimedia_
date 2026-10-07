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
            'label' => 'Layanan VPN',
            'title' => 'VPN untuk RouterOS Anda, daftar sendiri',
            'subtitle' => 'Layanan VPN',
            'body' => 'Satu akun untuk paling banyak tiga router. Anda mengisi nama router pertama, membayar tagihan lewat pembayaran online, lalu skrip Winbox langsung siap dipakai.',
            'content' => [
                'steps' => [
                    ['title' => 'Daftar', 'description' => 'Isi nama, nomor WhatsApp, dan paket. Akun VPN langsung terbuat.'],
                    ['title' => 'Nama router', 'description' => 'Tentukan nama router pertama. Nama itu menjadi username terowongan VPN.'],
                    ['title' => 'Bayar', 'description' => 'Tagihan pertama dibuka di payment gateway. Setelah lunas, secret di server aktif.'],
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
