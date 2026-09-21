<?php

namespace App\Services\Messaging;

use App\Models\Invoice;
use App\Models\MessageLog;
use App\Models\MessageOutbox;
use App\Models\MessagingIdentity;
use App\Models\PppoeCustomer;
use App\Support\AppSettings;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CustomerNotifier
{
    public function __construct(
        private readonly MessagingManager $channels,
        private readonly WhatsappOutbox $outbox,
    ) {}

    public function notifyInvoice(Invoice $invoice): void
    {
        if (! AppSettings::notifyInvoice()) {
            return;
        }

        $invoice->loadMissing('customer');
        $customer = $invoice->customer;
        if (! $customer) {
            return;
        }

        $this->send(
            $customer,
            MessageTemplate::INVOICE,
            $this->invoiceVars($invoice, $customer),
            paceWhatsapp: true,
            invoiceId: $invoice->id,
        );
    }

    public function notifyReminder(Invoice $invoice): void
    {
        if (! AppSettings::notifyReminder()) {
            return;
        }

        $invoice->loadMissing('customer');
        $customer = $invoice->customer;
        if (! $customer) {
            return;
        }

        $this->send(
            $customer,
            MessageTemplate::REMINDER,
            $this->invoiceVars($invoice, $customer),
            paceWhatsapp: true,
            invoiceId: $invoice->id,
        );
    }

    public function notifyPaid(Invoice $invoice): void
    {
        if (! AppSettings::notifyPaid()) {
            return;
        }

        $invoice->loadMissing(['customer.package']);
        $customer = $invoice->customer;
        if (! $customer) {
            return;
        }

        $vars = $this->invoiceVars($invoice, $customer);
        $nextDue = $customer->due_date?->format('d/m/Y');
        if ($nextDue) {
            $vars['jatuh_tempo'] = $nextDue;
        }

        $this->send($customer, MessageTemplate::PAID, $vars);
    }

    public function notifyIsolir(PppoeCustomer $customer): void
    {
        if (! AppSettings::bool('messaging_notify_isolir', false)) {
            return;
        }

        $this->send($customer, MessageTemplate::ISOLIR, $this->customerVars($customer));
    }

    public function notifyRestore(PppoeCustomer $customer): void
    {
        if (! AppSettings::bool('messaging_notify_isolir', false)) {
            return;
        }

        $this->send($customer, MessageTemplate::RESTORE, $this->customerVars($customer));
    }

    public function notifyWelcome(PppoeCustomer $customer, ?Invoice $invoice = null): void
    {
        if (! AppSettings::bool('messaging_notify_welcome', true)) {
            return;
        }

        $customer->loadMissing('package');
        $this->send($customer, MessageTemplate::WELCOME, $this->welcomeVars($customer, $invoice));
    }

    /**
     * @param  array<string, scalar|null>  $vars
     */
    public function send(
        PppoeCustomer $customer,
        string $template,
        array $vars,
        bool $paceWhatsapp = false,
        ?int $invoiceId = null,
    ): void {
        $body = MessageTemplate::render($template, $vars);
        if ($body === '') {
            return;
        }

        $body = AppSettings::replaceLegacyBrand($body);

        $sentTo = [];

        $identities = MessagingIdentity::query()
            ->where('pppoe_customer_id', $customer->id)
            ->get();

        foreach ($identities as $identity) {
            $channel = $identity->channel;
            if (! $this->channelEnabled($channel)) {
                continue;
            }

            if ($paceWhatsapp && $channel === 'whatsapp' && $this->outbox->enabled()) {
                $queued = $this->outbox->enqueue(
                    $template,
                    $identity->external_id,
                    $body,
                    $customer->id,
                    $invoiceId,
                    $identity->id,
                );
                if ($queued) {
                    $sentTo['whatsapp:'.$queued->external_id] = true;
                }

                continue;
            }

            $this->deliver($channel, $identity->external_id, $body, $identity, $template, $vars);
            $sentTo[$channel.':'.$identity->external_id] = true;
        }

        if (! $this->channelEnabled('whatsapp')) {
            return;
        }

        $phone = PhoneNumber::toInternational((string) $customer->phone);
        if ($phone === '' || isset($sentTo['whatsapp:'.$phone])) {
            return;
        }

        if ($paceWhatsapp && $this->outbox->enabled()) {
            $this->outbox->enqueue($template, $phone, $body, $customer->id, $invoiceId);

            return;
        }

        $this->deliver('whatsapp', $phone, $body, null, $template, $vars, $customer->id);
    }

    /**
     * @return array{sent: int, failed: int, postponed: int}
     */
    public function dispatchWhatsappOutbox(): array
    {
        return $this->outbox->dispatch(function (MessageOutbox $item): array {
            return $this->deliver(
                'whatsapp',
                $item->external_id,
                $item->body,
                $item->identity,
                $item->template,
                [],
                $item->pppoe_customer_id,
            );
        });
    }

    /**
     * Kirim satu template ke WhatsApp pelanggan (bukan welcome). Toggle otomatis diabaikan.
     *
     * @return array{ok: bool, message: string, queued?: bool}
     */
    public function notifyManualWhatsapp(Invoice $invoice, string $template, bool $defer = false): array
    {
        if (! MessageTemplate::isManual($template)) {
            return ['ok' => false, 'message' => 'Template ini tidak bisa dikirim manual.'];
        }

        if (! $this->channelEnabled('whatsapp')) {
            return ['ok' => false, 'message' => 'WhatsApp belum aktif. Aktifkan di Notifikasi & Bot.'];
        }

        $invoice->loadMissing(['customer.package']);
        $customer = $invoice->customer;
        if (! $customer) {
            return ['ok' => false, 'message' => 'Tagihan tidak memiliki pelanggan.'];
        }

        $vars = match ($template) {
            MessageTemplate::INVOICE, MessageTemplate::REMINDER, MessageTemplate::PAID => $this->invoiceVars($invoice, $customer),
            default => $this->customerVars($customer),
        };

        if ($template === MessageTemplate::PAID) {
            $nextDue = $customer->due_date?->format('d/m/Y');
            if ($nextDue) {
                $vars['jatuh_tempo'] = $nextDue;
            }
        }

        $body = MessageTemplate::render($template, $vars);
        if ($body === '') {
            return ['ok' => false, 'message' => 'Isi template kosong.'];
        }

        $identity = MessagingIdentity::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('channel', 'whatsapp')
            ->orderByDesc('id')
            ->first();

        $target = $identity?->external_id
            ?: PhoneNumber::toInternational((string) $customer->phone);

        $phone = PhoneNumber::toInternational($target);
        if ($phone === '') {
            return ['ok' => false, 'message' => 'Nomor WhatsApp pelanggan kosong dan belum terikat.'];
        }

        if ($defer && $this->outbox->enabled()) {
            $queued = $this->outbox->enqueue(
                $template,
                $phone,
                $body,
                $customer->id,
                $invoice->id,
                $identity?->id,
            );

            if (! $queued) {
                return ['ok' => false, 'message' => 'Gagal memasukkan WhatsApp ke antrian.'];
            }

            return [
                'ok' => true,
                'queued' => true,
                'message' => 'WhatsApp masuk antrian pengiriman bertahap.',
            ];
        }

        return $this->deliver('whatsapp', $phone, $body, $identity, $template, $vars, $customer->id);
    }

    /**
     * @return array{enabled: bool, templates: list<array{value: string, label: string}>}
     */
    public function whatsappManualUi(): array
    {
        $templates = [];
        foreach (MessageTemplate::manualChoices() as $value => $label) {
            $templates[] = ['value' => $value, 'label' => $label];
        }

        return [
            'enabled' => $this->channelEnabled('whatsapp'),
            'templates' => $templates,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function invoiceVars(Invoice $invoice, PppoeCustomer $customer): array
    {
        return [
            ...$this->customerVars($customer),
            'nomor' => (string) $invoice->number,
            'total' => 'Rp '.number_format((int) $invoice->total, 0, ',', '.'),
            'jatuh_tempo' => $invoice->due_date?->format('d/m/Y') ?? '—',
            'paket' => (string) ($invoice->package_name ?: $customer->package?->name ?: '—'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function customerVars(PppoeCustomer $customer): array
    {
        return [
            ...MessageTemplate::sharedVars(),
            'nama' => (string) $customer->name,
            'username' => (string) $customer->username,
            'perusahaan' => AppSettings::companyName(),
            'phone' => $this->dash((string) ($customer->phone ?? '')),
            'portal' => url('/portal'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function welcomeVars(PppoeCustomer $customer, ?Invoice $invoice = null): array
    {
        $customer->loadMissing('package');
        $package = $customer->package;
        $officePhone = trim((string) AppSettings::get('whatsapp', ''))
            ?: trim((string) AppSettings::get('phone', ''));

        return [
            ...$this->customerVars($customer),
            'password' => $this->dash((string) $customer->password),
            'alamat' => $this->customerAddress($customer),
            'paket' => $this->dash((string) ($package?->name ?: '')),
            'harga_paket' => $package
                ? $this->rupiah((int) $package->price)
                : '—',
            'tanggal_mulai' => $customer->start_date?->format('d/m/Y') ?? '—',
            'hari_tagihan' => $customer->billing_day ? (string) $customer->billing_day : '—',
            'jatuh_tempo' => ($invoice?->due_date ?? $customer->due_date)?->format('d/m/Y') ?? '—',
            'tagihan_pertama' => $this->rupiah((int) ($invoice?->total ?? $customer->first_bill_amount ?? 0)),
            'hari_prorata' => $customer->first_bill_days ? (string) $customer->first_bill_days : '—',
            'nomor' => $this->dash((string) ($invoice?->number ?? '')),
            'portal' => url('/portal'),
            'telepon_kantor' => $this->dash($officePhone),
            'email_kantor' => $this->dash((string) AppSettings::get('email', '')),
        ];
    }

    private function channelEnabled(string $channel): bool
    {
        try {
            return $this->channels->driver($channel)->isEnabled();
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * @param  array<string, scalar|null>  $vars
     * @return array{ok: bool, message: string}
     */
    private function deliver(
        string $channel,
        string $externalId,
        string $body,
        ?MessagingIdentity $identity,
        string $template,
        array $vars = [],
        ?int $customerId = null,
    ): array {
        try {
            $body = AppSettings::replaceLegacyBrand($body);
            $result = $this->channels->send($channel, $externalId, $body);
            $ok = (bool) ($result['ok'] ?? false);
            if ($ok && $channel === 'whatsapp') {
                $this->outbox->markSentNow();
            }
            MessageLog::query()->create([
                'channel' => $channel,
                'direction' => 'outbound',
                'messaging_identity_id' => $identity?->id,
                'pppoe_customer_id' => $identity?->pppoe_customer_id ?? $customerId,
                'external_id' => $externalId,
                'command' => $template,
                'status' => $ok ? 'sent' : 'failed',
                'body' => Str::limit($this->redactSecrets($body, $vars), 480, ''),
                'error_message' => $ok ? null : ($result['message'] ?? 'Gagal mengirim'),
            ]);

            return [
                'ok' => $ok,
                'message' => $ok
                    ? 'Pesan WhatsApp terkirim.'
                    : (string) ($result['message'] ?? 'Gagal mengirim WhatsApp.'),
            ];
        } catch (Throwable $e) {
            Log::warning('CustomerNotifier gagal mengirim', [
                'channel' => $channel,
                'template' => $template,
                'message' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => 'Gagal mengirim WhatsApp: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, scalar|null>  $vars
     */
    private function redactSecrets(string $body, array $vars): string
    {
        $password = trim((string) ($vars['password'] ?? ''));
        if ($password !== '' && $password !== '—') {
            $body = str_replace($password, '••••', $body);
        }

        return $body;
    }

    private function customerAddress(PppoeCustomer $customer): string
    {
        $address = trim((string) ($customer->address ?? ''));
        if ($address !== '') {
            return $address;
        }

        if ($customer->latitude === null || $customer->longitude === null) {
            return '—';
        }

        $lat = number_format((float) $customer->latitude, 6, '.', '');
        $lng = number_format((float) $customer->longitude, 6, '.', '');

        return $lat.', '.$lng;
    }

    private function dash(string $value): string
    {
        $value = trim($value);

        return $value !== '' ? $value : '—';
    }

    private function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
