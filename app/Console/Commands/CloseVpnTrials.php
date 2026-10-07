<?php

namespace App\Console\Commands;

use App\Services\BillingService;
use App\Services\Vpn\VpnRouterAccounts;
use Illuminate\Console\Command;

class CloseVpnTrials extends Command
{
    protected $signature = 'vpn:close-trials';

    protected $description = 'Tagih masa gratis VPN Tunnel yang sudah lewat 3 hari, lalu hapus dari CHR jika sebulan tidak ada pembayaran';

    public function handle(BillingService $billing, VpnRouterAccounts $accounts): int
    {
        $billed = $billing->billEndedVpnTrials();
        $this->info($billed.' tagihan masa gratis VPN Tunnel dikirim.');

        $purged = $accounts->purgeUnpaidTrials();
        $this->info($purged['removed'].' router gratis dihapus dari CHR. '.$purged['failed'].' gagal.');

        return $purged['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
