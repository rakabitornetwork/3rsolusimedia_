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
            'title' => 'VPN Tunnel untuk mengalihkan trafik Speedtest',
            'subtitle' => 'Layanan VPN Tunnel',
            'body' => 'Layanan VPN Tunnel dipakai untuk mengalihkan trafik Speedtest dari ISP utama ke tunnel kami. Internet utama tetap jalan. Tes kecepatan yang Anda arahkan keluar lewat VPN Tunnel, bukan lewat ISP yang sedang diukur.',
            'content' => json_encode([
                'features' => [
                    'Alihkan trafik Speedtest dari ISP utama ke VPN Tunnel',
                    'Tunnel L2TP langsung ke router MikroTik Anda',
                    'Router pertama gratis 3 hari setelah akun dibuat',
                    'Masuk portal hanya dengan kode OTP WhatsApp',
                ],
                'steps' => [
                    ['title' => 'Daftar', 'description' => 'Isi nama, email, dan WhatsApp. Akun langsung jadi tanpa memilih paket di formulir.'],
                    ['title' => 'Masuk dengan OTP', 'description' => 'Kode dikirim ke WhatsApp yang didaftarkan. Username dan password tidak dipakai untuk masuk portal.'],
                    ['title' => 'Router gratis 3 hari', 'description' => 'Buat router pertama di portal. Tunnel langsung bisa dipakai. Setelah 3 hari, tagihan dikirim ke WhatsApp.'],
                    ['title' => 'Bayar atau berakhir', 'description' => 'Jika sebulan tidak ada pembayaran, router gratis itu dihapus dari server VPN secara otomatis.'],
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
