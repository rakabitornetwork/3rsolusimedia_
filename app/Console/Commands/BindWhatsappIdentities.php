<?php

namespace App\Console\Commands;

use App\Services\Messaging\WhatsAppIdentityBinder;
use Illuminate\Console\Command;

class BindWhatsappIdentities extends Command
{
    protected $signature = 'messaging:bind-whatsapp';

    protected $description = 'Ikatkan nomor HP pelanggan ke bot WhatsApp tanpa perintah daftar';

    public function handle(WhatsAppIdentityBinder $binder): int
    {
        $result = $binder->syncAll();

        $this->info(
            'WhatsApp otomatis: '.$result['bound'].' baru terhubung, '
            .$result['existing'].' sudah terikat, '
            .$result['skipped'].' dilewati, '
            .$result['conflicts'].' nomor ganda.'
        );

        return self::SUCCESS;
    }
}
