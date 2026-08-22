<?php

namespace App\Services\Messaging;

use App\Models\Invoice;
use App\Models\MessageLog;
use App\Models\MessagingIdentity;
use App\Models\PppoeCustomer;
use App\Support\AppSettings;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CustomerNotifier
{
    public function __construct(private readonly MessagingManager $channels)
    {
    }

    public function notifyInvoice(Invoice $invoice): void
    {
        if (! AppSettings::bool('app_notif_whatsapp', false)) {
            return;
        }

        $invoice->loadMissing('customer');
        $customer = $invoice->customer;
        if (! $customer) {
            return;
        }

        $this->send($customer, MessageTemplate::INVOICE, $this->invoiceVars($invoice, $customer));
    }

    public function notifyReminder(Invoice $invoice): void
    {
        if (! AppSettings::bool('app_notif_whatsapp', false)) {
            return;
        }

        $invoice->loadMissing('customer');
        $customer = $invoice->customer;
        if (! $customer) {
            return;
        }

        $this->send($customer, MessageTemplate::REMINDER, $this->invoiceVars($invoice, $customer));
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

    /**
     * @param  array<string, scalar|null>  $vars
     */
    public function send(PppoeCustomer $customer, string $template, array $vars): void
    {
        $body = MessageTemplate::render($template, $vars);
        if ($body === '') {
            return;
        }

        $sentTo = [];

        $identities = MessagingIdentity::query()
            ->where('pppoe_customer_id', $customer->id)
            ->get();

        foreach ($identities as $identity) {
            $channel = $identity->channel;
            if (! $this->channelEnabled($channel)) {
                continue;
            }

            $this->deliver($channel, $identity->external_id, $body, $identity, $template);
            $sentTo[$channel.':'.$identity->external_id] = true;
        }

        if (! $this->channelEnabled('whatsapp')) {
            return;
        }

        $phone = PhoneNumber::toInternational((string) $customer->phone);
        if ($phone === '' || isset($sentTo['whatsapp:'.$phone])) {
            return;
        }

        $this->deliver('whatsapp', $phone, $body, null, $template, $customer->id);
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
            'nama' => (string) $customer->name,
            'username' => (string) $customer->username,
            'perusahaan' => AppSettings::companyName(),
            'phone' => (string) ($customer->phone ?? ''),
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

    private function deliver(
        string $channel,
        string $externalId,
        string $body,
        ?MessagingIdentity $identity,
        string $template,
        ?int $customerId = null,
    ): void {
        try {
            $result = $this->channels->send($channel, $externalId, $body);
            MessageLog::query()->create([
                'channel' => $channel,
                'direction' => 'outbound',
                'messaging_identity_id' => $identity?->id,
                'pppoe_customer_id' => $identity?->pppoe_customer_id ?? $customerId,
                'external_id' => $externalId,
                'command' => $template,
                'status' => ($result['ok'] ?? false) ? 'sent' : 'failed',
                'body' => Str::limit($body, 480, ''),
                'error_message' => ($result['ok'] ?? false) ? null : ($result['message'] ?? 'Gagal mengirim'),
            ]);
        } catch (Throwable $e) {
            Log::warning('CustomerNotifier gagal mengirim', [
                'channel' => $channel,
                'template' => $template,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
