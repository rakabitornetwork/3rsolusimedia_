<?php

namespace App\Console\Commands;

use App\Models\PppoeCustomer;
use App\Services\PppoeSyncService;
use Illuminate\Console\Command;

class SyncFailedPppoeCustomers extends Command
{
    protected $signature = 'pppoe:sync-errors {--limit=50}';

    protected $description = 'Ulangi sinkronisasi pelanggan yang status MikroTik-nya masih gagal';

    public function handle(PppoeSyncService $sync): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $customers = PppoeCustomer::query()
            ->with(['router', 'package'])
            ->where('sync_status', 'error')
            ->orderBy('last_synced_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($customers->isEmpty()) {
            $this->info('Tidak ada pelanggan yang gagal sync.');

            return self::SUCCESS;
        }

        $this->info('Mengulang sync untuk '.$customers->count().' pelanggan...');

        $ok = 0;
        $failed = 0;

        foreach ($customers as $customer) {
            try {
                $sync->sync($customer);
                $fresh = $customer->fresh();

                if ($fresh?->sync_status === 'synced') {
                    $ok++;
                    $this->info("OK [{$customer->username}]");
                } else {
                    $failed++;
                    $this->error("ERR [{$customer->username}]: ".($fresh->sync_message ?? 'gagal'));
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->error("FAIL [{$customer->username}]: {$e->getMessage()}");
            }
        }

        $this->info("Selesai: {$ok} berhasil, {$failed} masih gagal.");

        return self::SUCCESS;
    }
}
