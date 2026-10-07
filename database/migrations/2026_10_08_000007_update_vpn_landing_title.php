<?php

use App\Models\PageSection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        PageSection::query()->where('key', 'vpn')->update([
            'title' => 'VPN Tunnel untuk Remote perangkat RouterOS, remote perangkat dibelakangnya dan mengalihkan trafik Speedtest',
            'body' => 'VPN Tunnel dipakai untuk me-remote perangkat RouterOS, me-remote perangkat di belakangnya, dan mengalihkan trafik Speedtest dari ISP utama ke tunnel kami. Internet utama tetap jalan.',
        ]);
    }

    public function down(): void
    {
        PageSection::query()->where('key', 'vpn')->update([
            'title' => 'VPN Tunnel untuk mengalihkan trafik Speedtest',
            'body' => 'Layanan VPN Tunnel dipakai untuk mengalihkan trafik Speedtest dari ISP utama ke tunnel kami. Internet utama tetap jalan. Tes kecepatan yang Anda arahkan keluar lewat VPN Tunnel, bukan lewat ISP yang sedang diukur.',
        ]);
    }
};
