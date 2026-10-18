<?php

namespace App\Console\Commands;

use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\PppoeTrafficCursor;
use App\Services\MikrotikApiService;
use App\Services\PppoeMonthlyUsageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CollectPppoeMonthlyUsage extends Command
{
    protected $signature = 'pppoe:collect-usage
                            {--diagnose : Uji koneksi RouterOS dan cocokkan username tanpa menunggu cron}';

    protected $description = 'Hitung pemakaian RX/TX PPPoE bulan berjalan dari counter interface MikroTik';

    public function handle(PppoeMonthlyUsageService $usage, MikrotikApiService $api): int
    {
        if ($this->option('diagnose')) {
            return $this->diagnose($api, $usage);
        }

        $lock = Cache::lock('pppoe:collect-usage', 240);
        if (! $lock->get()) {
            $this->warn('Pengumpulan pemakaian PPPoE masih berjalan.');

            return self::SUCCESS;
        }

        try {
            $summary = $usage->collect();
            $this->info(sprintf(
                'Router terbaca %d, router gagal API %d, pelanggan diperbarui %d, gagal simpan %d.',
                $summary['routers'],
                $summary['failed_routers'],
                $summary['customers'],
                $summary['failed_customers'] ?? 0,
            ));

            if ($summary['routers'] === 0 && $summary['failed_routers'] > 0) {
                $this->warn('Semua router gagal dibaca. Cek host/port/user/password API RouterOS di menu Network → RouterOS.');
            } elseif ($summary['customers'] === 0 && $summary['routers'] > 0) {
                $this->warn('Router terbaca, tetapi tidak ada pelanggan online yang cocok. Pastikan secret online dan username di app sama dengan di MikroTik.');
            }

            $this->line('Catatan: pembacaan pertama hanya patokan (0 B). Angka muncul setelah pembacaan berikutnya (~5 menit), dan hanya saat pelanggan online.');
            $this->line('Pastikan cron aktif: * * * * * cd '.base_path().' && php artisan schedule:run');
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }

    private function diagnose(MikrotikApiService $api, PppoeMonthlyUsageService $usage): int
    {
        $this->info('Diagnosa pemakaian RX/TX');
        $this->line('Path: '.base_path());
        $last = PppoeTrafficCursor::query()->orderByDesc('sampled_at')->value('sampled_at');
        $this->line('Sample terakhir di database: '.($last ?: 'belum pernah'));
        $this->newLine();

        $routers = MikrotikRouter::query()->orderBy('id')->get();
        if ($routers->isEmpty()) {
            $this->error('Belum ada RouterOS di database.');

            return self::FAILURE;
        }

        $anyOnline = false;
        $anyMatched = false;

        foreach ($routers as $router) {
            $this->line(sprintf(
                '=== %s (id=%d, %s:%d, aktif=%s) ===',
                $router->name,
                $router->id,
                $router->host,
                $router->port,
                $router->is_active ? 'ya' : 'tidak',
            ));

            if (! $router->is_active) {
                $this->warn('Dilewati: router nonaktif di app.');
                $this->newLine();

                continue;
            }

            $map = $api->pppoeInterfaceBytesMap($router);
            if ($map === null) {
                $this->error('API gagal. Cek IP, port API (8728/8729), user, password, dan firewall.');
                $this->newLine();

                continue;
            }

            $anyOnline = $anyOnline || $map !== [];
            $this->info('Interface PPPoE/L2TP online: '.count($map));

            $customers = PppoeCustomer::query()
                ->where('mikrotik_router_id', $router->id)
                ->get(['id', 'username', 'is_active', 'status']);

            $matched = 0;
            $offlineInApp = 0;
            foreach ($customers as $customer) {
                $key = strtolower(trim((string) $customer->username));
                if (isset($map[$key])) {
                    $matched++;
                    $anyMatched = true;
                } elseif ($customer->is_active) {
                    $offlineInApp++;
                }
            }

            $this->line("Pelanggan di app untuk router ini: {$customers->count()}");
            $this->line("Cocok dengan sesi online: {$matched}");
            if ($offlineInApp > 0) {
                $this->line("Aktif di app tetapi tidak online di RouterOS saat ini: {$offlineInApp}");
            }

            if ($map !== [] && $matched === 0) {
                $sample = array_slice(array_keys($map), 0, 5);
                $this->warn('Ada sesi online, tetapi username tidak cocok dengan pelanggan di app.');
                $this->line('Contoh interface: '.implode(', ', $sample));
            }

            $this->newLine();
        }

        $this->info('Menjalankan satu kali collect…');
        $summary = $usage->collect();
        $this->info(sprintf(
            'Hasil: router=%d, gagal_api=%d, pelanggan=%d, gagal_simpan=%d',
            $summary['routers'],
            $summary['failed_routers'],
            $summary['customers'],
            $summary['failed_customers'] ?? 0,
        ));
        $this->newLine();

        if (! $anyOnline) {
            $this->warn('Tidak ada interface <pppoe-…> / <l2tp-…> online. RX/TX hanya terhitung saat pelanggan sedang login.');
        } elseif (! $anyMatched) {
            $this->warn('Sesi online ada, tetapi tidak ada username yang cocok dengan data pelanggan di app.');
        } else {
            $this->info('Cocok. Jika angka masih 0 B: itu normal setelah sample pertama. Jalankan lagi setelah ~5 menit (atau set cron).');
        }

        $this->line('Cron wajib (setiap menit):');
        $this->line('* * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1');

        return self::SUCCESS;
    }
}
