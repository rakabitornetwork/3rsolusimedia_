<?php

namespace App\Services\PaymentGateway;

use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Services\BillingService;
use App\Services\PaymentGateway\Contracts\PaymentGatewayInterface;
use App\Support\AppSettings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class PaymentGatewayManager
{
    /** @var array<string, PaymentGatewayInterface> */
    private array $drivers;

    public function __construct(
        private readonly BillingService $billing,
        XenditGateway $xendit,
        MidtransGateway $midtrans,
        DuitkuGateway $duitku,
    ) {
        $this->drivers = [
            'xendit' => $xendit,
            'midtrans' => $midtrans,
            'duitku' => $duitku,
        ];
    }

    public function driver(?string $name = null): PaymentGatewayInterface
    {
        $name ??= (string) AppSettings::get('pg_default', 'xendit');

        if (! isset($this->drivers[$name])) {
            throw new InvalidArgumentException("Gateway \"{$name}\" tidak dikenal.");
        }

        return $this->drivers[$name];
    }

    /**
     * @return list<string>
     */
    public function enabledGateways(): array
    {
        return array_values(array_filter(
            array_keys($this->drivers),
            fn (string $name) => $this->drivers[$name]->isEnabled()
        ));
    }

    public function hasEnabledGateway(): bool
    {
        return count($this->enabledGateways()) > 0;
    }

    public function defaultEnabledDriver(): PaymentGatewayInterface
    {
        $default = (string) AppSettings::get('pg_default', 'xendit');
        $driver = $this->driver($default);

        if ($driver->isEnabled()) {
            return $driver;
        }

        foreach ($this->enabledGateways() as $name) {
            return $this->driver($name);
        }

        throw new RuntimeException('Tidak ada payment gateway yang aktif. Aktifkan di menu Payment Gateway.');
    }

    /**
     * @return array{transaction: PaymentTransaction, checkout_url: string}
     */
    public function createPayment(
        Invoice $invoice,
        string $successUrl,
        string $failureUrl,
        ?string $gateway = null,
    ): array {
        if (! $invoice->isUnpaid()) {
            throw new InvalidArgumentException('Tagihan ini sudah tidak berstatus belum bayar.');
        }

        $driver = $gateway
            ? $this->driver($gateway)
            : $this->defaultEnabledDriver();

        if (! $driver->isEnabled()) {
            throw new RuntimeException('Gateway '.$driver->name().' belum aktif atau belum dikonfigurasi.');
        }

        $externalId = $this->makeExternalId($invoice, $driver->name());

        $transaction = PaymentTransaction::query()->create([
            'invoice_id' => $invoice->id,
            'gateway' => $driver->name(),
            'external_id' => $externalId,
            'amount' => (int) $invoice->total,
            'status' => 'pending',
        ]);

        try {
            $result = $driver->createCheckout($invoice, $transaction, $successUrl, $failureUrl);
        } catch (\Throwable $e) {
            $transaction->update([
                'status' => 'failed',
                'raw_response' => ['error' => $e->getMessage()],
            ]);
            throw $e;
        }

        $transaction->update([
            'checkout_url' => $result['checkout_url'],
            'gateway_reference' => $result['gateway_reference'] ?? null,
            'raw_request' => $result['raw_request'] ?? null,
            'raw_response' => $result['raw_response'] ?? null,
        ]);

        return [
            'transaction' => $transaction->fresh(),
            'checkout_url' => $result['checkout_url'],
        ];
    }

    public function handleWebhook(string $gateway, Request $request): PaymentTransaction
    {
        $driver = $this->driver($gateway);
        $parsed = $driver->parseWebhook($request);

        $transaction = PaymentTransaction::query()
            ->where('external_id', $parsed['external_id'])
            ->where('gateway', $gateway)
            ->first();

        if (! $transaction) {
            throw new InvalidArgumentException('Transaksi tidak ditemukan untuk external_id tersebut.');
        }

        return $this->applyWebhookResult($transaction, $parsed);
    }

    /**
     * @param  array{external_id: string, status: string, paid_at: ?Carbon, gateway_reference: ?string, raw: array}  $parsed
     */
    public function applyWebhookResult(PaymentTransaction $transaction, array $parsed): PaymentTransaction
    {
        return DB::transaction(function () use ($transaction, $parsed) {
            /** @var PaymentTransaction $locked */
            $locked = PaymentTransaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->raw_response = $parsed['raw'] ?? $locked->raw_response;
            if (! empty($parsed['gateway_reference'])) {
                $locked->gateway_reference = $parsed['gateway_reference'];
            }

            if ($locked->isPaid()) {
                $locked->save();

                return $locked;
            }

            $status = (string) ($parsed['status'] ?? 'pending');

            if ($status === 'paid') {
                $invoice = Invoice::query()
                    ->whereKey($locked->invoice_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $paidAt = $parsed['paid_at'] instanceof Carbon
                    ? $parsed['paid_at']
                    : now();

                $locked->status = 'paid';
                $locked->paid_at = $paidAt;
                $locked->save();

                if ($invoice->isUnpaid()) {
                    $this->billing->markPaid(
                        invoice: $invoice,
                        method: $locked->gateway,
                        reference: $locked->gateway_reference ?: $locked->external_id,
                        notes: 'Pembayaran online via '.ucfirst($locked->gateway),
                        receivedBy: null,
                        paidAt: $paidAt,
                    );
                }

                return $locked->fresh();
            }

            if (in_array($status, ['expired', 'failed', 'cancelled'], true)) {
                $locked->status = $status;
                $locked->save();
            } else {
                $locked->save();
            }

            return $locked->fresh();
        });
    }

    protected function makeExternalId(Invoice $invoice, string $gateway): string
    {
        return strtoupper($gateway).'-'.$invoice->id.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
    }
}
