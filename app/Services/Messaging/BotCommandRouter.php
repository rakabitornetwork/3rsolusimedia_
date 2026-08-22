<?php

namespace App\Services\Messaging;

use App\Models\Invoice;
use App\Models\MessageLog;
use App\Models\MessagingIdentity;
use App\Models\PppoeCustomer;
use App\Services\PaymentGateway\PaymentGatewayManager;
use App\Support\AppSettings;
use App\Support\PhoneNumber;
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
        private readonly PaymentGatewayManager $gateways,
    ) {
    }

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
        $this->log($message, 'inbound', $identity, $this->parseCommand($message->text)[0], 'received');

        if (! $message->isPrivate) {
            $this->reply($message, 'Gunakan chat pribadi dengan bot ini.', $identity);

            return;
        }

        if ($this->isRateLimited($message)) {
            $this->reply($message, 'Terlalu banyak pesan. Tunggu sebentar lalu coba lagi.', $identity);

            return;
        }

        [$command, $args] = $this->parseCommand($message->text);

        if ($command === null) {
            if ($this->pendingBind($message) !== null) {
                $this->completeBind($message, $message->text, $identity);

                return;
            }

            $this->reply(
                $message,
                trim($message->text) === ''
                    ? $this->helpText($identity !== null, $message->channel)
                    : 'Perintah tidak dikenali. Ketik /bantuan atau bantuan.',
                $identity,
            );

            return;
        }

        match ($command) {
            'start', 'bantuan', 'help', 'menu' => $this->reply($message, $this->helpText($identity !== null, $message->channel), $identity),
            'daftar' => $this->daftar($message, $args, $identity),
            'batal' => $this->cancelBind($message, $identity),
            'lepas' => $this->lepas($message, $identity),
            'tagihan' => $this->requireBound($message, $identity, fn (MessagingIdentity $bound) => $this->tagihan($message, $bound)),
            'bayar' => $this->requireBound($message, $identity, fn (MessagingIdentity $bound) => $this->bayar($message, $bound)),
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
            $this->reply($message, "Ketik username PPPoE Anda, contoh:\n/daftar budi01\n\nSetelah itu kirim nomor HP yang terdaftar di tagihan.");

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
     * @param  \Illuminate\Support\Collection<int, PppoeCustomer>  $candidates
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
            $this->reply($message, 'Akun ini sudah terikat ke Telegram lain. Minta admin melepas ikatan lama di menu Notifikasi & Bot.');

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

    private function cancelBind(IncomingMessage $message, ?MessagingIdentity $identity): void
    {
        if ($this->pendingBind($message) === null) {
            $this->reply($message, 'Tidak ada pendaftaran yang sedang berlangsung.', $identity);

            return;
        }

        $this->forgetPending($message);
        $this->reply($message, 'Pendaftaran dibatalkan. Ketik /daftar untuk mulai lagi.', $identity);
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
            $this->reply($message, 'Chat ini belum terhubung. Ketik /daftar lalu ikuti petunjuknya.');

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

        $unpaid = Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('status', 'unpaid')
            ->orderBy('due_date')
            ->get();

        if ($unpaid->isEmpty()) {
            $this->reply($message, 'Tidak ada tagihan yang belum lunas untuk '.$customer->username.'.', $identity);

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
        $lines[] = 'Ketik /bayar untuk tautan pembayaran tagihan tertua.';

        $this->reply($message, implode("\n", $lines), $identity);
    }

    private function bayar(IncomingMessage $message, MessagingIdentity $identity): void
    {
        $customer = $identity->customer;
        if (! $customer) {
            $this->reply($message, 'Akun pelanggan tidak ditemukan. Ketik /daftar ulang.', $identity);

            return;
        }

        $invoice = Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('status', 'unpaid')
            ->orderBy('due_date')
            ->first();

        if (! $invoice) {
            $this->reply($message, 'Tidak ada tagihan yang belum lunas.', $identity);

            return;
        }

        $token = Str::lower(Str::random(48));
        Cache::put('portal_pay:'.$token, $customer->id, now()->addHours(2));
        $invoicesUrl = URL::route('portal.pay.invoices', ['token' => $token]);

        if (! $this->gateways->hasEnabledGateway()) {
            $this->reply(
                $message,
                'Pembayaran online belum aktif. Cek tagihan di portal:'."\n".$invoicesUrl,
                $identity,
            );

            return;
        }

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

    private function helpText(bool $bound, string $channel = 'telegram'): string
    {
        $company = AppSettings::companyName();
        $bot = ltrim((string) AppSettings::get('telegram_bot_username', ''), '@');
        $header = $channel === 'telegram' && $bot !== ''
            ? $company.' (@'.$bot.')'
            : $company;

        $slash = $channel === 'whatsapp' ? '' : '/';

        $lines = [
            $header,
            'Bot pelanggan — ketik salah satu perintah:',
            '',
            $slash.'daftar — hubungkan chat ke akun PPPoE (username + nomor HP terdaftar)',
            $slash.'tagihan — cek tagihan belum lunas',
            $slash.'bayar — tautan bayar tagihan tertua',
            $slash.'lepas — putuskan ikatan chat ini',
            $slash.'bantuan — tampilkan pesan ini',
        ];

        if (! $bound && $channel === 'whatsapp') {
            $lines[] = '';
            $lines[] = 'Jika nomor chat sama dengan data pelanggan, ketik tagihan langsung. Jika belum, ketik daftar <username>.';
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

        if (preg_match('/^(daftar|tagihan|bayar|bantuan|help|menu|start|lepas|batal)(?:\s+([\s\S]+))?$/iu', $text, $matches)) {
            $command = strtolower($matches[1]);
            if ($command === 'menu') {
                $command = 'bantuan';
            }

            return [$command, trim((string) ($matches[2] ?? ''))];
        }

        return [null, ''];
    }

    /**
     * @return \Illuminate\Support\Collection<int, PppoeCustomer>
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

        $needle = $message->externalId;
        $tail = substr(PhoneNumber::normalize($needle), -8);
        if (strlen($tail) < 8) {
            return null;
        }

        $matches = PppoeCustomer::query()
            ->whereNotNull('phone')
            ->where('phone', 'like', '%'.$tail)
            ->get()
            ->filter(fn (PppoeCustomer $row) => PhoneNumber::matches((string) $row->phone, $needle))
            ->values();

        if ($matches->count() !== 1) {
            return null;
        }

        /** @var PppoeCustomer $customer */
        $customer = $matches->first();

        $existing = MessagingIdentity::query()
            ->where('channel', 'whatsapp')
            ->where('pppoe_customer_id', $customer->id)
            ->first();

        if ($existing && $existing->external_id !== PhoneNumber::toInternational($needle)) {
            return null;
        }

        return MessagingIdentity::query()->updateOrCreate(
            [
                'channel' => 'whatsapp',
                'external_id' => PhoneNumber::toInternational($needle),
            ],
            [
                'pppoe_customer_id' => $customer->id,
                'display_name' => $message->fromName,
                'verified_at' => now(),
                'last_seen_at' => now(),
            ],
        );
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

    private function reply(IncomingMessage $message, string $text, ?MessagingIdentity $identity = null): void
    {
        $result = $this->channels->send($message->channel, $message->externalId, $text);
        $this->log(
            $message,
            'outbound',
            $identity,
            $this->parseCommand($message->text)[0],
            ($result['ok'] ?? false) ? 'sent' : 'failed',
            $text,
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
