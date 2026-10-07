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
            $table->boolean('vpn_self_signup')->default(false)->after('vpn_trial_billed_at');
        });

        PageSection::query()->where('key', 'vpn')->update([
            'content' => json_encode([
                'features' => [
                    'Alihkan trafik Speedtest dari ISP utama ke VPN Tunnel',
                    'Tunnel L2TP langsung ke router MikroTik Anda',
                    'Masuk portal dengan email dan password',
                ],
                'steps' => [
                    ['title' => 'Daftar', 'description' => 'Isi nama, email, password, dan WhatsApp.'],
                    ['title' => 'Coba gratis', 'description' => 'Masuk portal dengan email dan password, lalu buat akun VPN. Gratis 3 hari.'],
                    ['title' => 'Jika cocok', 'description' => 'Bayar tagihan lewat payment gateway. Jika tidak dibayar setelah masa gratis, akun dihapus dari CHR.'],
                ],
            ]),
        ]);
    }

    public function down(): void
    {
        Schema::table('pppoe_customers', function (Blueprint $table) {
            $table->dropColumn('vpn_self_signup');
        });
    }
};
