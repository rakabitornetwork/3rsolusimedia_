<?php

namespace App\Services\PaymentGateway;

use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Services\PaymentGateway\Contracts\PaymentGatewayInterface;
use App\Support\AppSettings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class MidtransGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'midtrans';
    }

    public function isConfigured(): bool
    {
        return AppSettings::midtransServerKey() !== '';
    }

    public function isEnabled(): bool
    {
        return AppSettings::bool('midtrans_enabled', false) && $this->isConfigured();
    }

    protected function baseUrl(): string
    {
        $mode = (string) AppSettings::get('midtrans_mode', 'sandbox');

        return $mode === 'live'
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Server key Midtrans belum diisi.'];
        }

        $started = microtime(true);

        try {
            // Endpoint status dengan order_id fiktif: 404 = auth OK, 401 = key salah.
            $response = Http::withBasicAuth(AppSettings::midtransServerKey(), '')
                ->acceptJson()
                ->timeout(15)
                ->get($this->baseUrl().'/v2/teslatech-pg-ping/status');

            $latency = (int) round((microtime(true) - $started) * 1000);

            if ($response->status() === 401 || $response->status() === 403) {
                return [
                    'ok' => false,
                    'message' => 'Server key Midtrans ditolak.',
                    'latency_ms' => $latency,
                ];
            }

            return [
                'ok' => true,
                'message' => 'Koneksi Midtrans berhasil (mode '.AppSettings::get('midtrans_mode', 'sandbox').').',
                'latency_ms' => $latency,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Gagal menghubungi Midtrans: '.$e->getMessage(),
            ];
        }
    }

    public function createCheckout(Invoice $invoice, PaymentTransaction $transaction, string $successUrl, string $failureUrl): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Midtrans belum dikonfigurasi.');
        }

        $invoice->loadMissing('customer');

        $payload = [
            'transaction_details' => [
                'order_id' => $transaction->external_id,
                'gross_amount' => (int) $transaction->amount,
            ],
            'item_details' => [
                [
                    'id' => 'invoice-'.$invoice->id,
                    'price' => (int) $transaction->amount,
                    'quantity' => 1,
                    'name' => 'Tagihan '.$invoice->number,
                ],
            ],
            'customer_details' => array_filter([
                'first_name' => $invoice->customer?->name,
                'phone' => $invoice->customer?->phone,
            ]),
            'usage_limit' => 1,
            'expiry' => [
                'duration' => 1,
                'unit' => 'days',
            ],
            'callbacks' => [
                'finish' => $successUrl,
                'error' => $failureUrl,
            ],
        ];

        $response = Http::withBasicAuth(AppSettings::midtransServerKey(), '')
            ->acceptJson()
            ->timeout(30)
            ->post($this->baseUrl().'/v1/payment-links', $payload);

        $body = $response->json() ?? [];

        if (! $response->successful()) {
            $message = is_array($body)
                ? ($body['error_messages'][0] ?? $body['status_message'] ?? 'Gagal membuat payment link Midtrans.')
                : 'Gagal membuat payment link Midtrans.';
            if (is_array($message)) {
                $message = implode(', ', $message);
            }
            throw new RuntimeException((string) $message);
        }

        $checkoutUrl = (string) ($body['payment_url'] ?? '');
        if ($checkoutUrl === '') {
            throw new RuntimeException('Midtrans tidak mengembalikan URL pembayaran.');
        }

        return [
            'checkout_url' => $checkoutUrl,
            'gateway_reference' => isset($body['order_id'])
                ? (string) $body['order_id']
                : (isset($body['id']) ? (string) $body['id'] : null),
            'raw_request' => $payload,
            'raw_response' => $body,
        ];
    }

    public function parseWebhook(Request $request): array
    {
        $payload = $request->all();
        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $signature = (string) ($payload['signature_key'] ?? '');

        if ($orderId === '' || $signature === '') {
            throw new InvalidArgumentException('Payload notifikasi Midtrans tidak lengkap.');
        }

        $expected = hash(
            'sha512',
            $orderId.$statusCode.$grossAmount.AppSettings::midtransServerKey()
        );

        if (! hash_equals($expected, $signature)) {
            throw new InvalidArgumentException('Signature Midtrans tidak valid.');
        }

        $transactionStatus = strtolower((string) ($payload['transaction_status'] ?? ''));
        $fraudStatus = strtolower((string) ($payload['fraud_status'] ?? 'accept'));

        $status = 'pending';
        if (in_array($transactionStatus, ['capture', 'settlement'], true)) {
            $status = $fraudStatus === 'challenge' ? 'pending' : 'paid';
        } elseif (in_array($transactionStatus, ['deny', 'cancel'], true)) {
            $status = 'cancelled';
        } elseif ($transactionStatus === 'expire') {
            $status = 'expired';
        } elseif ($transactionStatus === 'failure') {
            $status = 'failed';
        }

        $paidAt = null;
        if ($status === 'paid') {
            $paidAt = ! empty($payload['settlement_time'])
                ? Carbon::parse($payload['settlement_time'])
                : (! empty($payload['transaction_time'])
                    ? Carbon::parse($payload['transaction_time'])
                    : now());
        }

        return [
            'external_id' => $orderId,
            'status' => $status,
            'paid_at' => $paidAt,
            'gateway_reference' => isset($payload['transaction_id'])
                ? (string) $payload['transaction_id']
                : null,
            'raw' => $payload,
        ];
    }
}
