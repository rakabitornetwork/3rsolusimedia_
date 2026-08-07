<?php

namespace App\Console\Commands;

use App\Models\PppoeCustomer;
use App\Services\PppoeSyncService;
use App\Support\AppSettings;
use Illuminate\Console\Command;

class SyncOverduePppoeCustomers extends Command
{
    /**
     * Perintah artisan untuk sinkronisasi & auto isolir pelanggan jatuh tempo.
     *
     * @var string
     */
    protected $signature = 'pppoe:sync-overdue';

    /**
     * Deskripsi perintah.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi dan otomatisasi isolir pelanggan PPPoE yang sudah jatuh tempo ke MikroTik';

    public function handle(PppoeSyncService $sync): int
    {
        if (! AppSettings::bool('app_auto_isolir', true)) {
            $this->warn('Fitur auto isolir dalam kondisi nonaktif di Pengaturan Aplikasi.');

            return self::SUCCESS;
        }

        $today = now()->toDateString();

        $customers = PppoeCustomer::query()
            ->with(['router', 'package'])
            ->where('is_active', true)
            ->where(function ($q) use ($today) {
                // Customer aktif tapi harusnya diisolir (due_date < today)
                $q->where(function ($q2) use ($today) {
                    $q2->where('status', 'active')
                        ->whereDate('due_date', '<', $today)
                        ->where('overdue_action', 'isolir')
                        ->where(function ($g) use ($today) {
                            $g->whereNull('grace_until')
                                ->orWhereDate('grace_until', '<', $today);
                        });
                })
                // Customer ter-isolir tapi harusnya aktif (misal sudah bayar / grace aktif / bypass)
                ->orWhere(function ($q2) use ($today) {
                    $q2->where('status', 'isolated')
                        ->where(function ($g) use ($today) {
                            $g->whereDate('due_date', '>=', $today)
                                ->orWhereDate('grace_until', '>=', $today)
                                ->orWhere('overdue_action', '!=', 'isolir');
                        });
                });
            })
            ->get();

        $total = $customers->count();

        if ($total === 0) {
            $this->info('Tidak ada pelanggan yang perlu disinkronkan / diisolir hari ini.');

            return self::SUCCESS;
        }

        $this->info("Memproses sinkronisasi untuk {$total} pelanggan...");

        $isolatedCount = 0;
        $restoredCount = 0;
        $errorCount = 0;

        foreach ($customers as $customer) {
            try {
                $sync->sync($customer);
                $fresh = $customer->fresh();

                if ($fresh->sync_status === 'error') {
                    $errorCount++;
                    $this->error("ERR [{$customer->username}]: {$fresh->sync_message}");
                } elseif ($fresh->status === 'isolated') {
                    $isolatedCount++;
                    $this->line("ISOLIR [{$customer->username}]: Profile diubah ke {$fresh->isolir_profile}");
                } else {
                    $restoredCount++;
                    $this->info("RESTORE [{$customer->username}]: Profile dikembalikan ke {$fresh->service_profile}");
                }
            } catch (\Throwable $e) {
                $errorCount++;
                $this->error("FAIL [{$customer->username}]: {$e->getMessage()}");
            }
        }

        $this->info("Proses selesai: {$isolatedCount} diisolir, {$restoredCount} dikembalikan aktif, {$errorCount} gagal.");

        return self::SUCCESS;
    }
}
