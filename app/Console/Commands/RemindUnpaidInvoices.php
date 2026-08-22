<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\Messaging\CustomerNotifier;
use App\Support\AppSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RemindUnpaidInvoices extends Command
{
    protected $signature = 'messaging:remind-invoices';

    protected $description = 'Kirim pengingat tagihan belum lunas ke kanal terikat / nomor WA pelanggan';

    public function handle(CustomerNotifier $notifier): int
    {
        if (! AppSettings::bool('app_notif_whatsapp', false)) {
            $this->info('Pengingat tagihan nonaktif (Notifikasi & Bot).');

            return self::SUCCESS;
        }

        $horizon = now()->addDays(AppSettings::billingGenerateDays())->endOfDay();

        $invoices = Invoice::query()
            ->with('customer')
            ->where('status', 'unpaid')
            ->whereDate('due_date', '<=', $horizon->toDateString())
            ->whereDate('created_at', '<', now()->toDateString())
            ->orderBy('due_date')
            ->limit(200)
            ->get();

        $sent = 0;
        foreach ($invoices as $invoice) {
            if (! $invoice->customer) {
                continue;
            }

            $cacheKey = 'messaging:remind:'.$invoice->id.':'.now()->toDateString();
            if (! Cache::add($cacheKey, 1, now()->endOfDay())) {
                continue;
            }

            $notifier->notifyReminder($invoice);
            $sent++;
            $this->line('REMIND '.$invoice->number.' → '.$invoice->customer->username);
        }

        $this->info("Pengingat terkirim: {$sent}.");

        return self::SUCCESS;
    }
}
