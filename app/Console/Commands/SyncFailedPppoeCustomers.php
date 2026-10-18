<?php

namespace App\Console\Commands;

use App\Models\PppoeCustomer;
use App\Services\PppoeSyncService;
use Illuminate\Console\Command;

class SyncFailedPppoeCustomers extends Command
{
    protected $signature = 'pppoe:sync-errors {--limit=50}';

    protected $description = 'Ulangi sync gagal, dan pastikan secret pelanggan nonaktif tetap mati di RouterOS';

    public function handle(PppoeSyncService $sync): int
    {
        $limit = max(1, (int) $this->option('limit'));

        // Prioritas: sync_status=error, lalu pelanggan nonaktif (secret sering
        // tertinggal aktif setelah RouterOS offline / sync sempat "berhasil" palsu).
        $customers = PppoeCustomer::query()
            ->with(['router', 'package'])
            ->where(function ($q) {
                $q->where('sync_status', 'error')
                    ->orWhere('is_active', false);
            })
            ->orderByRaw("CASE WHEN sync_status = 'error' THEN 0 ELSE 1 END")
            ->orderBy('last_synced_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($customers->isEmpty()) {
            $this->info('Tidak ada pelanggan yang perlu di-sync ulang.');

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
