<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    public function show(string $module): Response
    {
        $catalog = [
            'routeros' => [
                'title' => 'RouterOS MikroTik',
                'subtitle' => 'Jaringan',
                'description' => 'Kelola koneksi API RouterOS, monitoring interface, secret PPPoE, queue, dan script otomasi isolir.',
            ],
            'hotspot' => [
                'title' => 'Manajemen Hotspot',
                'subtitle' => 'Jaringan',
                'description' => 'Kelola voucher hotspot, profile bandwidth, dan sesi aktif di RouterOS.',
            ],
            'pppoe' => [
                'title' => 'Manajemen PPPoE',
                'subtitle' => 'Pelanggan',
                'description' => 'Manajemen data pelanggan, secret PPPoE, status aktif/nonaktif, dan histori layanan.',
            ],
            'users' => [
                'title' => 'User Management',
                'subtitle' => 'Sistem',
                'description' => 'Kelola akun admin/operator, peran akses, dan hak modul aplikasi.',
            ],
            'system' => [
                'title' => 'Pengaturan Aplikasi',
                'subtitle' => 'Sistem',
                'description' => 'Konfigurasi umum aplikasi, integrasi, notifikasi, dan preferensi operasional.',
            ],
        ];

        abort_unless(isset($catalog[$module]), 404);

        return Inertia::render('Admin/ComingSoon', [
            'module' => [
                'key' => $module,
                ...$catalog[$module],
            ],
        ]);
    }
}
