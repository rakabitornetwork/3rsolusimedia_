<?php

use App\Models\PageSection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pppoe_customers', function (Blueprint $table) {
            $table->string('email')->nullable()->after('phone');
            $table->date('vpn_trial_ends_at')->nullable()->after('notes');
            $table->timestamp('vpn_trial_billed_at')->nullable()->after('vpn_trial_ends_at');
        });

        PageSection::query()->where('key', 'vpn')->update([
            'label' => 'Layanan VPN Tunnel',
            'title' => 'VPN Tunnel: remote RouterOS dan Speedtest',
            'subtitle' => 'Layanan VPN Tunnel',
            'body' => 'VPN Tunnel dipakai untuk me-remote perangkat RouterOS, me-remote perangkat di belakangnya, dan mengalihkan trafik Speedtest dari ISP utama ke tunnel kami. Internet utama tetap jalan.',
            'content' => json_encode([
                'features' => [
                    'Alihkan trafik Speedtest dari ISP utama ke VPN Tunnel',
                    'Tunnel L2TP langsung ke router MikroTik Anda',
                    'Masuk portal hanya dengan kode OTP WhatsApp',
                ],
                'steps' => [
                    ['title' => 'Daftar', 'description' => 'Isi nama, email, dan WhatsApp. Akun langsung terbuat, tanpa memilih paket.'],
                    ['title' => 'Coba gratis', 'description' => 'Masuk dengan kode OTP WhatsApp dan pakai tunnel selama 3 hari.'],
                    ['title' => 'Jika cocok', 'description' => 'Silahkan lanjut pembayaran via portal pelanggan.'],
                ],
            ]),
            'cta_label' => 'Daftar VPN Tunnel',
            'cta_url' => '/vpn/daftar',
        ]);
    }

    public function down(): void
    {
        Schema::table('pppoe_customers', function (Blueprint $table) {
            $table->dropColumn(['email', 'vpn_trial_ends_at', 'vpn_trial_billed_at']);
        });
    }
};
