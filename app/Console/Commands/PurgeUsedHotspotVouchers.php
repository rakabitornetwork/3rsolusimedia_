<?php

namespace App\Console\Commands;

use App\Models\MikrotikRouter;
use App\Services\HotspotVoucherService;
use Illuminate\Console\Command;

class PurgeUsedHotspotVouchers extends Command
{
    protected $signature = 'hotspot:purge-used {--router= : ID router tertentu}';

    protected $description = 'Hapus voucher hotspot yang sudah terpakai dari RouterOS dan aplikasi';

    public function handle(HotspotVoucherService $vouchers): int
    {
        $routerId = $this->option('router');

        $query = MikrotikRouter::query()->where('is_active', true)->orderBy('name');
        if ($routerId) {
            $query->where('id', (int) $routerId);
        }

        $routers = $query->get();
        if ($routers->isEmpty()) {
            $this->warn('Tidak ada router aktif.');

            return self::SUCCESS;
        }

        $totalRemoved = 0;

        foreach ($routers as $router) {
            $result = $vouchers->purgeUsed($router);
            $removed = (int) ($result['removed'] ?? 0);
            $totalRemoved += $removed;
            $this->line("[{$router->name}] {$result['message']}");
        }

        $this->info("Selesai. Total dihapus: {$totalRemoved}");

        return self::SUCCESS;
    }
}
