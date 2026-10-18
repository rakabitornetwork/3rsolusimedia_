<?php

namespace App\Console\Commands;

use App\Services\PppoeMonthlyUsageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CollectPppoeMonthlyUsage extends Command
{
    protected $signature = 'pppoe:collect-usage';

    protected $description = 'Hitung pemakaian RX/TX PPPoE bulan berjalan dari counter interface MikroTik';

    public function handle(PppoeMonthlyUsageService $usage): int
    {
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
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }
}
