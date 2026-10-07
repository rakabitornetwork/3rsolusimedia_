<?php

use App\Models\PageSection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        PageSection::query()->where('key', 'vpn')->update([
            'title' => 'VPN Tunnel: remote RouterOS dan Speedtest',
        ]);
    }

    public function down(): void
    {
        PageSection::query()->where('key', 'vpn')->update([
            'title' => 'VPN Tunnel untuk Remote perangkat RouterOS, remote perangkat dibelakangnya dan mengalihkan trafik Speedtest',
        ]);
    }
};
