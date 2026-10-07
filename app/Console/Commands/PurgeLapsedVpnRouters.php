<?php

namespace App\Console\Commands;

use App\Models\PppoeCustomer;
use App\Services\Vpn\VpnRouterAccounts;
use Illuminate\Console\Command;

class PurgeLapsedVpnRouters extends Command
{
    protected $signature = 'vpn:purge-lapsed-routers';

    protected $description = 'Hapus router VPN akun yang sudah 3 bulan berturut-turut tidak diperpanjang, termasuk aturan di CHR';

    public function handle(VpnRouterAccounts $accounts): int
    {
        $cutoff = now()->startOfDay()->subMonthsNoOverflow(3)->toDateString();

        $customers = PppoeCustomer::query()
            ->where('ppp_service', PppoeCustomer::SERVICE_L2TP)
            ->whereDate('due_date', '<=', $cutoff)
            ->whereHas('vpnRouters')
            ->get();

        if ($customers->isEmpty()) {
            $this->info('Tidak ada router VPN yang sudah 3 bulan tidak diperpanjang.');

            return self::SUCCESS;
        }

        $removed = 0;
        $failed = 0;

        foreach ($customers as $customer) {
            $result = $accounts->purgeLapsedRouters($customer);
            $removed += $result['removed'];
            $failed += count($result['failed']);

            if ($result['removed'] > 0) {
                $this->line($customer->username.': '.$result['removed'].' router dihapus. Akun pelanggan tetap ada.');
            }

            foreach ($result['failed'] as $name) {
                $this->warn($customer->username.': router '.$name.' gagal dihapus dari CHR, jadi datanya dipertahankan.');
            }
        }

        $this->info("Selesai. {$removed} router dihapus, {$failed} gagal.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
