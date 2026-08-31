<?php

namespace App\Console\Commands;

use App\Services\Messaging\CustomerNotifier;
use Illuminate\Console\Command;

class SendWhatsappOutbox extends Command
{
    protected $signature = 'messaging:send-outbox';

    protected $description = 'Kirim antrian WhatsApp tagihan/pengingat dengan jeda acak (anti-spam Evolution API)';

    public function handle(CustomerNotifier $notifier): int
    {
        $result = $notifier->dispatchWhatsappOutbox();

        $this->info(
            'WhatsApp antrian: '.$result['sent'].' terkirim, '
            .$result['failed'].' gagal, '
            .$result['postponed'].' ditunda (batas harian).'
        );

        return self::SUCCESS;
    }
}
