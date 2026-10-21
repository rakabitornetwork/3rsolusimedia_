<?php

namespace App\Services\Messaging;

use App\Models\Invoice;
use App\Models\MessageLog;
use App\Models\MessagingIdentity;
use App\Models\PppoeCustomer;
use App\Services\BillingService;
use App\Services\PaymentGateway\PaymentGatewayManager;
use App\Support\AppSettings;
use App\Support\PhoneNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class BotCommandRouter
{
    public function __construct(
        private readonly MessagingManager $channels,
        private readonly BillingService $billing,
        private readonly PaymentGatewayManager $gateways,
        private readonly AdminCustomerLookup $adminLookup,
        private readonly WhatsAppIdentityBinder $whatsappBinder,
    ) {}

    public function handle(IncomingMessage $message): void
    {
        $channel = $this->channels->driver($message->channel);

        if (! $channel->isEnabled()) {
            if ($message->text !== '') {
                $this->reply($message, 'Layanan bot sedang nonaktif. Hubungi admin.');
            }

            return;
        }

        $identity = $this->identityFor($message) ?? $this->autoBindWhatsApp($message);
        $this->touchIdentity($identity, $message);
        $whatsappSelection = $message->channel === 'whatsapp'
            ? $this->whatsappMenuSelection($message->text, $identity)
            : null;
        $isWhatsappNumber = $message->channel === 'whatsapp'
            && preg_match('/^\d{1,2}$/', trim($message->text)) === 1;
        $inboundCommand = $message->isCallback()
            ? 'cari'
            : ($whatsappSelection ?? $this->parseCommand($message->text)[0]);
        $this->log(
            $message,
            'inbound',
            $identity,
            $inboundCommand,
            'received',
            in_array($inboundCommand, ['password', 'sandi'], true) ? $inboundCommand : null,
        );

        if (! $message->isPrivate) {
            $this->reply($message, 'Gunakan chat pribadi dengan bot ini.', $identity);

            return;
        }

        if ($this->isRateLimited($message)) {
            $this->reply($message, 'Terlalu banyak pesan. Tunggu sebentar lalu coba lagi.', $identity);

            return;
        }

        if ($message->isCallback()) {
            $this->handleAdminCallback($message, $identity);

            return;
        }

        [$command, $args] = $this->parseCommand($message->text);

        if ($command === null) {
            if ($this->pendingAdminAction($message) !== null) {
                $this->completeAdminAction($message, $identity);

                return;
            }

            if ($this->pendingBind($message) !== null) {
                $this->completeBind($message, $message->text, $identity);

                return;
            }

            if ($whatsappSelection !== null) {
                $command = $whatsappSelection;
            } elseif ($isWhatsappNumber) {
                $this->reply(
                    $message,
                    "Maaf, pilihan nomor ".trim($message->text)." tidak ada di menu. 🙏\n\n".$this->helpText($identity !== null, $message->channel, false, $identity?->customer),
                    $identity,
                );

                return;
            } else {
                $this->reply(
                    $message,
                    trim($message->text) === ''
                        ? $this->helpText($identity !== null, $message->channel, $this->adminLookup->isAdmin($message), $identity?->customer)
                        : ($message->channel === 'whatsapp'
                            ? 'Maaf, pesan itu belum saya pahami. 🙏 Ketik *0* atau *bantuan* untuk melihat menu pilihan.'
                            : 'Perintah tidak dikenali. Ketik /bantuan atau bantuan.'),
                    $identity,
                );

                return;
            }
        }

        if ($command !== 'batal' && $command !== 'cari') {
            $this->forgetAdminAction($message);
        }

        match ($command) {
            'start', 'bantuan', 'help', 'menu' => $this->reply($message, $this->helpText($identity !== null, $message->channel, $this->adminLookup->isAdmin($message), $identity?->customer), $identity),
            'daftar' => $this->daftar($message, $args, $identity),
            'batal' => $this->batal($message, $identity),
            'lepas' => $this->lepas($message, $identity),
            'tagihan' => $this->requireBound($message, $identity, fn (MessagingIdentity $bound) => $this->tagihan($message, $bound)),
            'bayar' => $this->requireBound($message, $identity, fn (MessagingIdentity $bound) => $this->bayar($message, $bound, $args)),
            'wifi' => $this->requireBound($message, $identity, fn (MessagingIdentity $bound) => $this->onu($message, $bound, 'wifi')),
            'ssid' => $this->requireBound($message, $identity, fn (MessagingIdentity $bound) => $this->onu($message, $bound, 'ssid', $args)),
            'password', 'sandi' => $this->requireBound($message, $identity, fn (MessagingIdentity $bound) => $this->onu($message, $bound, 'password', $args)),
            'rx', 'rxpower' => $this->requireBound($message, $identity, fn (MessagingIdentity $bound) => $this->onu($message, $bound, 'rx')),
            'suhu', 'temp' => $this->requireBound($message, $identity, fn (MessagingIdentity $bound) => $this->onu($message, $bound, 'temp')),
            'perangkat' => $this->requireBound($message, $identity, fn (MessagingIdentity $bound) => $this->onu($message, $bound, 'dev')),
            'cari' => $this->cari($message, $args, $identity),
            default => $this->reply($message, 'Perintah '.$command.' belum tersedia. Ketik bantuan.', $identity),
        };
    }

    private function daftar(IncomingMessage $message, string $args, ?MessagingIdentity $identity): void
    {
        if ($identity) {
            $this->reply(
                $message,
                'Chat ini sudah terikat ke akun '.$identity->customer?->username.'. Ketik /lepas untuk mengganti.',
                $identity,
            );

            return;
        }

        if ($this->bindAttempts($message) >= 5) {
            $this->reply($message, 'Terlalu banyak percobaan. Tunggu 15 menit atau hubungi admin.');

            return;
        }

        $parts = preg_split('/\s+/', trim($args), 2) ?: [];
        $username = trim((string) ($parts[0] ?? ''));
        $phone = trim((string) ($parts[1] ?? ''));

        if ($username === '') {
            $this->rememberPending($message, ['username' => null, 'customer_id' => null]);
            $this->reply($message, "Ketik username PPPoE atau VPN Anda, contoh:\n/daftar budi01\n\nSetelah itu kirim nomor HP yang terdaftar di tagihan.");

            return;
        }

        $candidates = $this->customersByUsername($username);
        if ($candidates->isEmpty()) {
            $this->hitBindAttempt($message);
            $this->reply($message, 'Username tidak ditemukan. Periksa ejaan atau hubungi admin.');

            return;
        }

        if ($phone !== '') {
            $this->tryBind($message, $candidates, $phone, null);

            return;
        }

        $customerId = $candidates->count() === 1 ? (int) $candidates->first()->id : null;
        $this->rememberPending($message, [
            'username' => $username,
            'customer_id' => $customerId,
        ]);
        $this->reply(
            $message,
            'Username ditemukan. Kirim nomor HP yang terdaftar di tagihan (contoh 081234567890). Ketik /batal untuk membatalkan.',
        );
    }

    private function completeBind(IncomingMessage $message, string $phone, ?MessagingIdentity $identity): void
    {
        if ($identity) {
            $this->forgetPending($message);
            $this->reply($message, 'Chat ini sudah terikat ke akun pelanggan.', $identity);

            return;
        }

        $pending = $this->pendingBind($message) ?? [];
        $username = trim((string) ($pending['username'] ?? ''));

        if ($username === '') {
            $username = trim($message->text);
            $candidates = $this->customersByUsername($username);
            if ($candidates->isEmpty()) {
                $this->hitBindAttempt($message);
                $this->reply($message, 'Username tidak ditemukan. Kirim lagi, contoh: budi01');

                return;
            }

            $this->rememberPending($message, [
                'username' => $username,
                'customer_id' => $candidates->count() === 1 ? (int) $candidates->first()->id : null,
            ]);
            $this->reply($message, 'Username ditemukan. Kirim nomor HP yang terdaftar di tagihan.');

            return;
        }

        $candidates = $this->customersByUsername($username);
        $this->tryBind($message, $candidates, $phone, isset($pending['customer_id']) ? (int) $pending['customer_id'] : null);
    }

    /**
     * @param  Collection<int, PppoeCustomer>  $candidates
     */
    private function tryBind(IncomingMessage $message, $candidates, string $phone, ?int $preferredId): void
    {
        if (! PhoneNumber::normalize($phone) || strlen(PhoneNumber::normalize($phone)) < 8) {
            $this->reply($message, 'Nomor HP tidak valid. Kirim nomor terdaftar, contoh 081234567890.');

            return;
        }

        $matched = $candidates->first(function (PppoeCustomer $row) use ($phone, $preferredId) {
            if ($preferredId && (int) $row->id !== $preferredId) {
                return false;
            }

            return PhoneNumber::matches((string) $row->phone, $phone);
        });

        if (! $matched && $preferredId) {
            $matched = $candidates->first(
                fn (PppoeCustomer $row) => PhoneNumber::matches((string) $row->phone, $phone)
            );
        }

        if (! $matched) {
            $this->hitBindAttempt($message);
            $this->reply($message, 'Nomor HP tidak cocok dengan username itu. Coba lagi atau hubungi admin.');

            return;
        }

        $existingCustomer = MessagingIdentity::query()
            ->where('channel', $message->channel)
            ->where('pppoe_customer_id', $matched->id)
            ->first();

        if ($existingCustomer && $existingCustomer->external_id !== $message->externalId) {
            $channelLabel = $message->channel === 'whatsapp' ? 'WhatsApp' : 'Telegram';
            $this->reply($message, 'Akun ini sudah terikat ke '.$channelLabel.' lain. Minta admin melepas ikatan lama di menu Notifikasi & Bot.');

            return;
        }

        $identity = MessagingIdentity::query()->updateOrCreate(
            [
                'channel' => $message->channel,
                'external_id' => $message->externalId,
            ],
            [
                'pppoe_customer_id' => $matched->id,
                'display_name' => $message->fromName,
                'username' => $message->fromUsername,
                'verified_at' => now(),
                'last_seen_at' => now(),
            ],
        );

        $this->forgetPending($message);
        Cache::forget($this->attemptKey($message));

        $this->reply(
            $message,
            'Berhasil terhubung sebagai '.$matched->name.' ('.$matched->username.").\nKetik /tagihan atau /bayar.",
            $identity,
        );
    }

    private function batal(IncomingMessage $message, ?MessagingIdentity $identity): void
    {
        if ($this->forgetAdminAction($message)) {
            $this->reply($message, 'Perubahan WiFi dibatalkan. Ketik /cari untuk mencari pelanggan lagi.', $identity);

            return;
        }

        $this->cancelBind($message, $identity);
    }

    private function cancelBind(IncomingMessage $message, ?MessagingIdentity $identity): void
    {
        if ($this->pendingBind($message) === null) {
            $this->reply($message, 'Tidak ada proses yang sedang berlangsung.', $identity);

            return;
        }

        $this->forgetPending($message);
        $this->reply($message, 'Pendaftaran dibatalkan. Ketik /daftar untuk mulai lagi.', $identity);
    }

    private function cari(IncomingMessage $message, string $args, ?MessagingIdentity $identity): void
    {
        if ($message->channel !== 'telegram') {
            $this->reply($message, 'Perintah cari hanya tersedia di Telegram.', $identity);

            return;
        }

        if (! $this->adminLookup->isAdmin($message)) {
            $hint = AppSettings::telegramAdminChatIds() === []
                ? 'Isi Chat ID admin di menu Notifikasi & Bot, lalu pasang ulang webhook.'
                : 'Chat ID Anda: '.$message->externalId.'. Minta admin menambahkannya di Notifikasi & Bot.';
            $this->reply($message, 'Perintah /cari khusus admin/teknisi. '.$hint, $identity);

            return;
        }

        $this->forgetAdminAction($message);
        $term = trim($args);

        if (mb_strlen($term) < 2) {
            $this->reply($message, "Ketik nama, username, atau nomor HP pelanggan.\nContoh: /cari Budi Santoso", $identity);

            return;
        }

        $total = $this->adminLookup->countMatches($term);
        if ($total === 0) {
            $this->reply($message, 'Pelanggan "'.$term.'" tidak ditemukan. Coba nama lain, username, atau nomor HP.', $identity);

            return;
        }

        $customers = $this->adminLookup->search($term, 8);
        if ($customers->count() === 1 && $total === 1) {
            $result = $this->adminLookup->menu($customers->first());
            $this->reply($message, $result['text'], $identity, $result['keyboard']);

            return;
        }

        $result = $this->adminLookup->choices($customers, $total);
        $this->reply($message, $result['text'], $identity, $result['keyboard']);
    }

    private function handleAdminCallback(IncomingMessage $message, ?MessagingIdentity $identity): void
    {
        $this->answerCallback($message);

        if (! $this->adminLookup->isAdmin($message)) {
            $this->reply($message, 'Tombol ini khusus admin/teknisi.', $identity);

            return;
        }

        $parsed = $this->adminLookup->parseCallback((string) $message->callbackData);
        if (! $parsed) {
            $this->reply($message, 'Pilihan tidak valid. Ketik /cari nama_pelanggan.', $identity);

            return;
        }

        $customer = PppoeCustomer::query()->with(['package', 'router', 'agent'])->find($parsed['customer_id']);
        if (! $customer) {
            $this->reply($message, 'Pelanggan tidak ditemukan lagi. Ketik /cari untuk mencari ulang.', $identity);

            return;
        }

        $result = $this->adminLookup->action($parsed['action'], $customer);
        if (isset($result['pending']) && is_array($result['pending'])) {
            $this->rememberAdminAction($message, $result['pending']);
        } else {
            $this->forgetAdminAction($message);
        }

        $this->reply(
            $message,
            $result['text'],
            $identity,
            $result['keyboard'] ?? null,
            $result['log_text'] ?? null,
            true,
        );
    }

    private function completeAdminAction(IncomingMessage $message, ?MessagingIdentity $identity): void
    {
        $pending = $this->pendingAdminAction($message);
        if (! $pending || ($pending['type'] ?? '') !== 'edit_wifi') {
            $this->forgetAdminAction($message);
            $this->reply($message, 'Tidak ada perubahan yang sedang berlangsung.', $identity);

            return;
        }

        $customer = PppoeCustomer::query()->find((int) ($pending['customer_id'] ?? 0));
        if (! $customer) {
            $this->forgetAdminAction($message);
            $this->reply($message, 'Pelanggan tidak ditemukan lagi. Ketik /cari untuk mencari ulang.', $identity);

            return;
        }

        $parsed = $this->adminLookup->parseWifiInput($message->text);
        if (isset($parsed['error'])) {
            $this->reply($message, $parsed['error']."\nKetik /batal untuk membatalkan.", $identity);

            return;
        }

        $result = $this->adminLookup->applyWifi($customer, $parsed['ssid'], $parsed['password']);
        $this->forgetAdminAction($message);
        $this->reply(
            $message,
            $result['text'],
            $identity,
            $result['keyboard'] ?? null,
            $result['log_text'] ?? null,
        );
    }

    /**
     * @return array{type: string, customer_id: int}|null
     */
    private function pendingAdminAction(IncomingMessage $message): ?array
    {
        $value = Cache::get($this->adminActionKey($message));

        return is_array($value) ? $value : null;
    }

    /**
     * @param  array{type: string, customer_id: int}  $payload
     */
    private function rememberAdminAction(IncomingMessage $message, array $payload): void
    {
        Cache::put($this->adminActionKey($message), $payload, now()->addMinutes(10));
    }

    private function forgetAdminAction(IncomingMessage $message): bool
    {
        $key = $this->adminActionKey($message);
        if (! Cache::has($key)) {
            return false;
        }

        Cache::forget($key);

        return true;
    }

    private function adminActionKey(IncomingMessage $message): string
    {
        return 'messaging:admin-action:'.$message->channel.':'.$message->externalId;
    }

    private function answerCallback(IncomingMessage $message): void
    {
        if ($message->callbackQueryId === null || $message->callbackQueryId === '') {
            return;
        }

        $channel = $this->channels->driver($message->channel);
        if ($channel instanceof TelegramChannel) {
            $channel->answerCallback($message->callbackQueryId);
        }
    }

    private function lepas(IncomingMessage $message, ?MessagingIdentity $identity): void
    {
        if (! $identity) {
            $this->reply($message, 'Chat ini belum terikat ke akun pelanggan.');

            return;
        }

        $username = $identity->customer?->username ?? 'pelanggan';
        $identity->delete();
        $this->forgetPending($message);
        $this->reply($message, 'Ikatan ke akun '.$username.' sudah dilepas. Ketik /daftar untuk menghubungkan lagi.');
    }

    private function requireBound(IncomingMessage $message, ?MessagingIdentity $identity, callable $callback): void
    {
        if (! $identity) {
            $hint = $message->channel === 'whatsapp'
                ? $this->unboundWhatsappHint()
                : 'Chat ini belum terhubung. Ketik /daftar lalu ikuti petunjuknya.';
            $this->reply($message, $hint);

            return;
        }

        $callback($identity);
    }

    private function tagihan(IncomingMessage $message, MessagingIdentity $identity): void
    {
        $customer = $identity->customer;
        if (! $customer) {
            $this->reply($message, 'Akun pelanggan tidak ditemukan. Ketik /daftar ulang.', $identity);

            return;
        }

        $this->billing->ensurePayableInvoice($customer);

        $unpaid = Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('status', 'unpaid')
            ->orderBy('due_date')
            ->get();

        $slash = $message->channel === 'whatsapp' ? '' : '/';

        if ($unpaid->isEmpty()) {
            $due = $customer->due_date?->format('d/m/Y');
            $lines = ['Tidak ada tagihan yang belum lunas untuk '.$customer->username.'.'];
            if ($due) {
                $lines[] = 'Jatuh tempo berikutnya '.$due.'.';
            }
            if ($this->gateways->hasEnabledGateway()) {
                $lines[] = 'Ketik '.$slash.'bayar 2 sampai '.$slash.'bayar 6 untuk membayar beberapa bulan sekaligus.';
            }
            $this->reply($message, implode("\n", $lines), $identity);

            return;
        }

        $lines = [
            'Tagihan '.$customer->name.' ('.$customer->username.')',
            '',
        ];
        $total = 0;
        foreach ($unpaid as $invoice) {
            $total += (int) $invoice->total;
            $due = $invoice->due_date?->format('d/m/Y') ?? '—';
            $late = $invoice->isOverdue() ? ' · terlambat' : '';
            $lines[] = $invoice->number.' · '.$this->rupiah((int) $invoice->total).' · jatuh tempo '.$due.$late;
        }
        $lines[] = '';
        $lines[] = 'Total: '.$this->rupiah($total);
        if ($this->gateways->hasEnabledGateway()) {
            $lines[] = 'Ketik '.$slash.'bayar untuk tautan pembayaran tagihan tertua.';
            $lines[] = 'Ketik '.$slash.'bayar 2 sampai '.$slash.'bayar 6 untuk membayar beberapa bulan sekaligus.';
        }

        $this->reply($message, implode("\n", $lines), $identity);
    }

    private function advanceMonthsArgument(string $args): ?int
    {
        $arg = trim($args);
        if ($arg === '') {
            return null;
        }

        if (! preg_match('/^[2-6]$/', $arg)) {
            return 0;
        }

        return (int) $arg;
    }

    private function bayar(IncomingMessage $message, MessagingIdentity $identity, string $args = ''): void
    {
        $customer = $identity->customer;
        if (! $customer) {
            $this->reply($message, 'Akun pelanggan tidak ditemukan. Ketik /daftar ulang.', $identity);

            return;
        }

        if (! $this->gateways->hasEnabledGateway()) {
            $lines = ['Pembayaran online belum aktif. Hubungi admin untuk konfirmasi pembayaran.'];
            if ($customer->pppService() !== PppoeCustomer::SERVICE_L2TP) {
                $rekening = MessageTemplate::sharedVars()['rekening'];
                if ($rekening !== '') {
                    $lines[] = '';
                    $lines[] = $rekening;
                }
            }

            $this->reply($message, implode("\n", $lines), $identity);

            return;
        }

        $slash = $message->channel === 'whatsapp' ? '' : '/';
        $months = $this->advanceMonthsArgument($args);
        if ($months === 0) {
            $this->reply(
                $message,
                'Jumlah bulan tidak valid. Ketik '.$slash.'bayar untuk tagihan saat ini, atau '.$slash.'bayar 2 sampai '.$slash.'bayar 6 untuk bayar beberapa bulan sekaligus.',
                $identity,
            );

            return;
        }

        if ($months !== null) {
            try {
                $invoice = $this->billing->createAdvanceInvoice($customer->loadMissing('package'), $months);
            } catch (InvalidArgumentException $e) {
                $this->reply($message, $e->getMessage(), $identity);

                return;
            }
        } else {
            $this->billing->ensurePayableInvoice($customer);

            $invoice = Invoice::query()
                ->where('pppoe_customer_id', $customer->id)
                ->where('status', 'unpaid')
                ->where('type', '!=', 'vpn_router')
                ->orderBy('due_date')
                ->first();

            if (! $invoice) {
                $invoice = Invoice::query()
                    ->where('pppoe_customer_id', $customer->id)
                    ->where('status', 'unpaid')
                    ->orderBy('due_date')
                    ->first();
            }
        }

        if (! $invoice) {
            $this->reply(
                $message,
                'Tidak ada tagihan yang belum lunas. Ketik '.$slash.'bayar 2 sampai '.$slash.'bayar 6 untuk membayar beberapa bulan sekaligus.',
                $identity,
            );

            return;
        }

        $token = Str::lower(Str::random(48));
        Cache::put('portal_pay:'.$token, $customer->id, now()->addHours(2));
        $invoicesUrl = URL::route('portal.pay.invoices', ['token' => $token]);

        $successUrl = URL::route('portal.pay.invoices', ['token' => $token, 'status' => 'success']);
        $failureUrl = URL::route('portal.pay.invoices', ['token' => $token, 'status' => 'failed']);

        try {
            $result = $this->gateways->createPayment($invoice, $successUrl, $failureUrl);
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->reply($message, $e->getMessage()."\nPortal: ".$invoicesUrl, $identity);

            return;
        } catch (Throwable $e) {
            Log::error('Bot /bayar failed', ['message' => $e->getMessage()]);
            $this->reply($message, 'Gagal membuat tautan pembayaran. Coba portal:'."\n".$invoicesUrl, $identity);

            return;
        }

        $this->reply(
            $message,
            'Bayar '.$invoice->number.' ('.$this->rupiah((int) $invoice->total).") melalui tautan ini:\n".$result['checkout_url'],
            $identity,
        );
    }

    private function onu(IncomingMessage $message, MessagingIdentity $identity, string $view, string $args = ''): void
    {
        $customer = $identity->customer;
        if (! $customer) {
            $this->reply($message, 'Akun pelanggan tidak ditemukan. Ketik daftar ulang.', $identity);

            return;
        }

        if ($customer->pppService() === PppoeCustomer::SERVICE_L2TP) {
            $this->reply($message, 'Akun VPN tidak memiliki ONU. Perintah ini untuk pelanggan internet PPPoE.', $identity);

            return;
        }

        if ($view === 'ssid' && $args !== '') {
            if (mb_strlen($args) > 32) {
                $this->reply($message, 'SSID harus 1–32 karakter.', $identity);

                return;
            }

            $result = $this->adminLookup->applyWifi($customer, $args, null);
            $this->reply($message, $result['text'], $identity, null, $result['log_text'] ?? null);

            return;
        }

        if ($view === 'password' && $args !== '') {
            $parsed = $this->adminLookup->parseWifiInput('*'.$args);
            if (isset($parsed['error'])) {
                $this->reply($message, $parsed['error'], $identity);

                return;
            }

            $result = $this->adminLookup->applyWifi($customer, null, $parsed['password']);
            $this->reply($message, $result['text'], $identity, null, $result['log_text'] ?? null);

            return;
        }

        $action = match ($view) {
            'ssid', 'password' => 'wifi',
            default => $view,
        };
        $result = $this->adminLookup->customerView($customer, $action);
        $text = $result['text'];
        if ($action === 'wifi' && str_contains($text, 'SSID:')) {
            $slash = $message->channel === 'whatsapp' ? '' : '/';
            $text .= "\n\nGanti nama: {$slash}ssid NamaBaru";
            $text .= "\nGanti password (8–63 karakter): {$slash}password passwordbaru";
        }

        $this->reply($message, $text, $identity, null, $result['log_text'] ?? null);
    }

    /**
     * Pilihan menu WhatsApp berbasis angka untuk pelanggan awam.
     * Urutan di sini HARUS sama dengan urutan yang ditampilkan di whatsappHelpText(),
     * karena pelanggan membalas hanya dengan angkanya (mis. "1").
     *
     * @return list<array{command: string, icon: string, label: string}>
     */
    private function whatsappMenuItems(bool $bound, bool $vpn): array
    {
        $onlinePay = $this->gateways->hasEnabledGateway();

        if ($vpn) {
            $items = [
                ['command' => 'tagihan', 'icon' => '🧾', 'label' => 'Cek tagihan yang belum dibayar'],
            ];
            if ($onlinePay) {
                $items[] = ['command' => 'bayar', 'icon' => '💳', 'label' => 'Bayar tagihan sekarang (online)'];
            }
            $items[] = ['command' => 'lepas', 'icon' => '🔓', 'label' => 'Putuskan hubungan nomor WhatsApp ini dari akun'];

            return $items;
        }

        $items = [
            ['command' => 'wifi', 'icon' => '📶', 'label' => 'Lihat nama & password WiFi di rumah'],
            ['command' => 'perangkat', 'icon' => '📱', 'label' => 'Lihat HP / perangkat yang terhubung ke WiFi'],
            ['command' => 'rx', 'icon' => '📡', 'label' => 'Cek sinyal kotak internet / ONU (kuat atau lemah)'],
            ['command' => 'suhu', 'icon' => '🌡️', 'label' => 'Cek suhu kotak internet / ONU (normal atau panas)'],
            ['command' => 'tagihan', 'icon' => '🧾', 'label' => 'Cek tagihan yang belum dibayar'],
        ];
        if ($onlinePay) {
            $items[] = ['command' => 'bayar', 'icon' => '💳', 'label' => 'Bayar tagihan sekarang (online)'];
        }
        $items[] = ['command' => 'ssid', 'icon' => '✏️', 'label' => 'Ganti nama WiFi'];
        $items[] = ['command' => 'password', 'icon' => '🔐', 'label' => 'Ganti password WiFi'];
        $items[] = $bound
            ? ['command' => 'lepas', 'icon' => '🔓', 'label' => 'Putuskan hubungan nomor WhatsApp ini dari akun']
            : ['command' => 'daftar', 'icon' => '🔗', 'label' => 'Hubungkan nomor WhatsApp ini ke akun pelanggan'];

        return $items;
    }

    private function whatsappMenuSelection(string $text, ?MessagingIdentity $identity): ?string
    {
        $text = trim($text);
        if (preg_match('/^\d{1,2}$/', $text) !== 1) {
            return null;
        }

        $number = (int) $text;
        if ($number === 0) {
            return 'bantuan';
        }

        $vpn = $identity?->customer?->pppService() === PppoeCustomer::SERVICE_L2TP;
        $items = $this->whatsappMenuItems($identity !== null, $vpn);

        return $items[$number - 1]['command'] ?? null;
    }

    /**
     * Nomor WhatsApp Admin dari Pengaturan Situs (kunci `whatsapp` di panel admin).
     *
     * @return array{display: string, url: string}|null
     */
    private function adminWhatsappContact(): ?array
    {
        $display = PhoneNumber::normalize(trim((string) AppSettings::get('whatsapp', '')));
        $url = AppSettings::whatsappChatUrl(
            'Halo Admin, nomor WhatsApp saya belum terhubung ke akun pelanggan. Mohon bantu daftarkan/hubungkan nomor ini ke username saya. Terima kasih.'
        );

        if ($display === '' || $url === '') {
            return null;
        }

        return ['display' => $display, 'url' => $url];
    }

    private function unboundWhatsappHint(): string
    {
        $adminContact = $this->adminWhatsappContact();
        $portal = url('/portal');

        if ($adminContact !== null) {
            return 'Nomor WhatsApp ini belum terhubung ke akun pelanggan. 🙏 '
                .'Minta Admin mendaftarkannya secara manual ke username Anda di WhatsApp Admin *'.$adminContact['display'].'* ('.$adminContact['url'].'), '
                .'atau hubungkan sendiri lewat Portal Pelanggan (lebih mudah): '.$portal.' — masuk dengan nomor WhatsApp, kode OTP dikirim ke HP terdaftar.';
        }

        return 'Nomor WhatsApp ini belum terhubung ke akun pelanggan. 🙏 '
            .'Hubungkan sendiri lewat Portal Pelanggan (lebih mudah): '.$portal.' — masuk dengan nomor WhatsApp, kode OTP dikirim ke HP terdaftar. '
            .'Atau ketik *daftar usernameAnda* (contoh: daftar budi01) di chat ini.';
    }

    private function whatsappHelpText(bool $bound, ?PppoeCustomer $customer = null): string
    {
        $company = AppSettings::companyName();
        $vpn = $customer?->pppService() === PppoeCustomer::SERVICE_L2TP;
        $onlinePay = $this->gateways->hasEnabledGateway();
        $items = $this->whatsappMenuItems($bound, $vpn);
        $keycaps = ['1️⃣', '2️⃣', '3️⃣', '4️⃣', '5️⃣', '6️⃣', '7️⃣', '8️⃣', '9️⃣'];

        $adminContact = $bound ? null : $this->adminWhatsappContact();

        $portalHint = $vpn
            ? ($onlinePay
                ? 'Di portal Anda bisa cek & bayar tagihan, lalu menyalin skrip VPN untuk dipasang di Winbox.'
                : 'Di portal Anda bisa menyalin skrip VPN untuk dipasang di Winbox.')
            : ($bound
                ? ($onlinePay
                    ? 'Di portal Anda bisa cek & bayar tagihan, melihat kondisi kotak internet (ONU), mengganti WiFi, dan melihat perangkat yang terhubung.'
                    : 'Di portal Anda bisa melihat kondisi kotak internet (ONU), mengganti WiFi, dan melihat perangkat yang terhubung.')
                : '💻 *Lewat Portal Pelanggan (lebih mudah):* Anda bisa menghubungkan nomor WhatsApp ini ke username Anda sendiri di portal, tanpa menunggu Admin. Cukup masuk dengan nomor WhatsApp — kode OTP akan dikirim ke HP yang terdaftar. Setelah terhubung, Anda bisa langsung cek & bayar tagihan, mengatur WiFi, dan melihat perangkat yang terhubung.');

        $lines = [
            $company,
            '👋 Halo! Silakan pilih layanan di bawah ini.',
            'Cukup *ketik angkanya saja*, lalu kirim.',
            '',
        ];

        if (! $bound) {
            $lines[] = '⚠️ Nomor WhatsApp ini belum terhubung ke akun pelanggan.';
            $lines[] = 'Agar bisa memakai layanan di bawah, nomor ini perlu dihubungkan dulu ke username Anda.';
            $lines[] = $adminContact !== null
                ? 'Minta Admin mendaftarkannya secara manual, atau hubungkan sendiri lewat Portal Pelanggan (lebih mudah) — caranya ada di bawah menu ini. 👇'
                : 'Hubungkan sendiri lewat Portal Pelanggan (lebih mudah) — caranya ada di bawah menu ini. 👇';
            $lines[] = '';
        }

        foreach ($items as $index => $item) {
            $lines[] = ($keycaps[$index] ?? (($index + 1).'.')).' '.$item['icon'].' '.$item['label'];
        }
        $lines[] = '0️⃣ ❓ Tampilkan menu ini lagi';
        $lines[] = '';
        $lines[] = $vpn
            ? '💡 Contoh: ketik *1* untuk mengecek tagihan Anda.'
            : '💡 Contoh: ketik *1* untuk melihat nama & password WiFi.';
        $lines[] = $vpn
            ? 'Selain angka, Anda juga tetap bisa mengetik katanya, contoh: *tagihan*, *bayar*.'
            : 'Selain angka, Anda juga tetap bisa mengetik katanya, contoh: *wifi*, *tagihan*, *bayar*.';
        if (! $bound) {
            $lines[] = '';
            if ($adminContact !== null) {
                $lines[] = '📞 *Minta dibantu Admin (didaftarkan manual)*';
                $lines[] = 'WhatsApp Admin: *'.$adminContact['display'].'*';
                $lines[] = $adminContact['url'];
                $lines[] = 'Klik link di atas, lalu sampaikan username Anda dan minta nomor WhatsApp ini didaftarkan ke username tersebut. Siapkan juga nomor HP yang terdaftar ya. 🙏';
            } else {
                $lines[] = '📞 *Minta dibantu Admin (didaftarkan manual)*';
                $lines[] = 'Ketik *daftar usernameAnda* (contoh: daftar budi01) di chat ini, lalu kirim nomor HP yang terdaftar.';
            }
        }
        $lines[] = '';
        $lines[] = '🌐 Portal pelanggan: '.url('/portal');
        $lines[] = '';
        $lines[] = $portalHint;

        if (! $bound) {
            $lines[] = '';
            $lines[] = 'Jika nomor WhatsApp ini sama dengan nomor HP di data pelanggan, biasanya langsung terhubung otomatis. Jika belum, silakan pilih salah satu cara di atas ya. 🙏';
        }

        return implode("\n", $lines);
    }

    private function helpText(bool $bound, string $channel = 'telegram', bool $admin = false, ?PppoeCustomer $customer = null): string
    {
        if ($channel === 'whatsapp') {
            return $this->whatsappHelpText($bound, $customer);
        }

        $company = AppSettings::companyName();
        $bot = ltrim((string) AppSettings::get('telegram_bot_username', ''), '@');
        $header = $channel === 'telegram' && $bot !== ''
            ? $company.' (@'.$bot.')'
            : $company;

        $slash = $channel === 'whatsapp' ? '' : '/';
        $vpn = $customer?->pppService() === PppoeCustomer::SERVICE_L2TP;
        $account = $vpn ? 'VPN' : ($bound ? 'PPPoE' : 'PPPoE atau VPN');
        $onlinePay = $this->gateways->hasEnabledGateway();
        $portalHint = $vpn
            ? ($onlinePay
                ? 'Login WhatsApp, kode OTP dikirim ke HP terdaftar. Di portal bisa cek & bayar tagihan, lalu salin skrip VPN dan jalankan di New Terminal Winbox.'
                : 'Login WhatsApp, kode OTP dikirim ke HP terdaftar. Di portal bisa salin skrip VPN dan jalankan di New Terminal Winbox.')
            : ($bound
                ? ($onlinePay
                    ? 'Login WhatsApp, kode OTP dikirim ke HP terdaftar. Di portal bisa cek & bayar tagihan, lihat status ONU, ubah WiFi, dan pantau perangkat terhubung.'
                    : 'Login WhatsApp, kode OTP dikirim ke HP terdaftar. Di portal bisa lihat status ONU, ubah WiFi, dan pantau perangkat terhubung.')
                : 'Login WhatsApp, kode OTP dikirim ke HP terdaftar. Pelanggan PPPoE mengelola ONU dan WiFi di portal. Pelanggan VPN mendapat skrip pemasangan untuk terminal Winbox.');

        $lines = [
            $header,
            'Bot pelanggan — ketik salah satu perintah:',
            '',
            $slash.'daftar — hubungkan chat ke akun '.$account.' (username + nomor HP terdaftar)',
            $slash.'tagihan — cek tagihan belum lunas',
        ];
        if ($onlinePay) {
            $lines[] = $slash.'bayar — tautan bayar tagihan saat ini';
            $lines[] = $slash.'bayar 3 — bayar 3 bulan sekaligus (2–6)';
        }

        if (! $vpn) {
            $lines[] = $slash.'wifi — lihat nama dan password WiFi';
            $lines[] = $slash.'ssid NamaBaru — ganti nama WiFi';
            $lines[] = $slash.'password passwordbaru — ganti password WiFi';
            $lines[] = $slash.'rx — lihat RX power ONU';
            $lines[] = $slash.'suhu — lihat suhu ONU';
            $lines[] = $slash.'perangkat — jumlah dan detail perangkat terhubung';
        }

        $lines = array_merge($lines, [
            $slash.'lepas — putuskan ikatan chat ini',
            $slash.'bantuan — tampilkan pesan ini',
            '',
            'Portal pelanggan: '.url('/portal'),
            $portalHint,
        ]);

        if ($admin && $channel === 'telegram') {
            $lines[] = '';
            $lines[] = 'Admin/teknisi:';
            $lines[] = '🔎 '.$slash.'cari nama_pelanggan — cari pelanggan, lalu pilih profil, RX power, suhu, SSID/password, perangkat terhubung, tagihan';
        }

        if (! $bound && $channel === 'whatsapp') {
            $lines[] = '';
            $lines[] = $onlinePay
                ? 'Nomor HP di data pelanggan terhubung otomatis. Ketik tagihan atau bayar. Jika nomor chat berbeda, ketik daftar <username>.'
                : 'Nomor HP di data pelanggan terhubung otomatis. Ketik tagihan. Jika nomor chat berbeda, ketik daftar <username>.';
        } elseif (! $bound) {
            $lines[] = '';
            $lines[] = 'Mulai dengan /daftar diikuti username, contoh /daftar budi01';
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function parseCommand(string $text): array
    {
        $text = trim($text);
        if (preg_match('/^\/([a-zA-Z]+)(?:@[\w]+)?(?:\s+([\s\S]+))?$/u', $text, $matches)) {
            return [strtolower($matches[1]), trim((string) ($matches[2] ?? ''))];
        }

        if (preg_match('/^(daftar|tagihan|bayar|bantuan|help|menu|start|lepas|batal|cari|wifi|ssid|password|sandi|rxpower|rx|suhu|temp|perangkat)(?:\s+([\s\S]+))?$/iu', $text, $matches)) {
            $command = strtolower($matches[1]);
            if ($command === 'menu') {
                $command = 'bantuan';
            }

            return [$command, trim((string) ($matches[2] ?? ''))];
        }

        return [null, ''];
    }

    /**
     * @return Collection<int, PppoeCustomer>
     */
    private function customersByUsername(string $username)
    {
        $username = trim($username);
        $exact = PppoeCustomer::query()->where('username', $username)->get();
        if ($exact->isNotEmpty()) {
            return $exact;
        }

        return PppoeCustomer::query()
            ->whereRaw('LOWER(username) = ?', [mb_strtolower($username)])
            ->get();
    }

    private function identityFor(IncomingMessage $message): ?MessagingIdentity
    {
        $query = MessagingIdentity::query()
            ->with('customer')
            ->where('channel', $message->channel);

        $identity = (clone $query)->where('external_id', $message->externalId)->first();
        if ($identity) {
            return $identity;
        }

        if ($message->channel !== 'whatsapp') {
            return null;
        }

        $local = PhoneNumber::normalize($message->externalId);
        $intl = PhoneNumber::toInternational($message->externalId);

        return $query
            ->whereIn('external_id', array_values(array_unique(array_filter([$local, $intl, $message->externalId]))))
            ->first();
    }

    private function autoBindWhatsApp(IncomingMessage $message): ?MessagingIdentity
    {
        if ($message->channel !== 'whatsapp') {
            return null;
        }

        return $this->whatsappBinder->bindFromIncomingNumber($message->externalId, $message->fromName);
    }

    private function touchIdentity(?MessagingIdentity $identity, IncomingMessage $message): void
    {
        if (! $identity) {
            return;
        }

        $identity->forceFill([
            'display_name' => $message->fromName ?: $identity->display_name,
            'username' => $message->fromUsername ?: $identity->username,
            'last_seen_at' => now(),
        ])->save();
    }

    /**
     * @param  array<int, array<int, array{text: string, callback_data: string}>>|null  $keyboard
     */
    private function reply(
        IncomingMessage $message,
        string $text,
        ?MessagingIdentity $identity = null,
        ?array $keyboard = null,
        ?string $logText = null,
        bool $preferEdit = false,
    ): void {
        $text = AppSettings::replaceLegacyBrand($text);
        $markup = $keyboard ? ['inline_keyboard' => $keyboard] : null;
        $channel = $this->channels->driver($message->channel);
        $result = ['ok' => false, 'message' => 'Gagal mengirim'];

        if ($preferEdit && $message->messageId && $channel instanceof TelegramChannel) {
            $result = $channel->editMessage($message->externalId, $message->messageId, $text, $markup);
            if (! ($result['ok'] ?? false)) {
                $result = $channel->send($message->externalId, $text, $markup);
            }
        } else {
            $result = $this->channels->send($message->channel, $message->externalId, $text, $markup);
        }

        $command = $message->isCallback()
            ? 'cari'
            : $this->parseCommand($message->text)[0];

        $this->log(
            $message,
            'outbound',
            $identity,
            $command,
            ($result['ok'] ?? false) ? 'sent' : 'failed',
            $logText ?? $text,
            ($result['ok'] ?? false) ? null : ($result['message'] ?? 'Gagal mengirim'),
        );
    }

    private function log(
        IncomingMessage $message,
        string $direction,
        ?MessagingIdentity $identity,
        ?string $command,
        string $status,
        ?string $body = null,
        ?string $error = null,
    ): void {
        try {
            $text = $body ?? $message->text;
            if ($command === 'daftar' || $this->pendingBind($message) !== null) {
                $text = $this->redactSensitive($text);
            }
            if ($direction === 'inbound' && $this->pendingAdminAction($message) !== null) {
                $text = '[perubahan wifi]';
            }

            MessageLog::query()->create([
                'channel' => $message->channel,
                'direction' => $direction,
                'messaging_identity_id' => $identity?->id,
                'pppoe_customer_id' => $identity?->pppoe_customer_id,
                'external_id' => $message->externalId,
                'command' => $command,
                'status' => $status,
                'body' => Str::limit((string) $text, 480, ''),
                'error_message' => $error,
            ]);
        } catch (Throwable $e) {
            Log::warning('Gagal menyimpan message log', ['message' => $e->getMessage()]);
        }
    }

    private function redactSensitive(string $text): string
    {
        $redacted = preg_replace('/\b0?\d{8,15}\b/', '[nomor]', $text) ?? $text;

        return $redacted;
    }

    /**
     * @return array{username: ?string, customer_id: ?int}|null
     */
    private function pendingBind(IncomingMessage $message): ?array
    {
        $value = Cache::get($this->pendingKey($message));

        return is_array($value) ? $value : null;
    }

    /**
     * @param  array{username: ?string, customer_id: ?int}  $payload
     */
    private function rememberPending(IncomingMessage $message, array $payload): void
    {
        Cache::put($this->pendingKey($message), $payload, now()->addMinutes(10));
    }

    private function forgetPending(IncomingMessage $message): void
    {
        Cache::forget($this->pendingKey($message));
    }

    private function pendingKey(IncomingMessage $message): string
    {
        return 'messaging:bind:'.$message->channel.':'.$message->externalId;
    }

    private function isRateLimited(IncomingMessage $message): bool
    {
        $key = 'messaging:rate:'.$message->channel.':'.$message->externalId;
        $count = (int) Cache::get($key, 0);
        if ($count >= 20) {
            return true;
        }

        Cache::put($key, $count + 1, now()->addMinute());

        return false;
    }

    private function bindAttempts(IncomingMessage $message): int
    {
        return (int) Cache::get($this->attemptKey($message), 0);
    }

    private function hitBindAttempt(IncomingMessage $message): void
    {
        $key = $this->attemptKey($message);
        Cache::put($key, $this->bindAttempts($message) + 1, now()->addMinutes(15));
    }

    private function attemptKey(IncomingMessage $message): string
    {
        return 'messaging:bind-fail:'.$message->channel.':'.$message->externalId;
    }

    private function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
