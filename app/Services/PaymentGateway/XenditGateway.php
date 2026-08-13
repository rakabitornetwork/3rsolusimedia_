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

class XenditGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'xendit';
    }

    public function isConfigured(): bool
    {
        return AppSettings::xenditSecretKey() !== '';
    }

    public function isEnabled(): bool
    {
        return AppSettings::bool('xendit_enabled', false) && $this->isConfigured();
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Secret key Xendit belum diisi.'];
        }

        $started = microtime(true);

        try {
            $response = Http::withBasicAuth(AppSettings::xenditSecretKey(), '')
                ->acceptJson()
                ->timeout(15)
                ->get('https://api.xendit.co/balance');

            $latency = (int) round((microtime(true) - $started) * 1000);

            if ($response->successful()) {
                return [
                    'ok' => true,
                    'message' => 'Koneksi Xendit berhasil.',
                    'latency_ms' => $latency,
                ];
            }

            return [
                'ok' => false,
                'message' => 'Xendit menolak kredensial (HTTP '.$response->status().').',
                'latency_ms' => $latency,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Gagal menghubungi Xendit: '.$e->getMessage(),
            ];
        }
    }

    public function createCheckout(Invoice $invoice, PaymentTransaction $transaction, string $successUrl, string $failureUrl): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Xendit belum dikonfigurasi.');
        }

        $invoice->loadMissing('customer');

        $payload = [
            'external_id' => $transaction->external_id,
            'amount' => (int) $transaction->amount,
            'description' => 'Tagihan '.$invoice->number,
            'invoice_duration' => 86400,
            'currency' => 'IDR',
            'success_redirect_url' => $successUrl,
            'failure_redirect_url' => $failureUrl,
            'customer' => array_filter([
                'given_names' => $invoice->customer?->name,
                'mobile_number' => $invoice->customer?->phone,
            ]),
            'metadata' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->number,
            ],
        ];

        $response = Http::withBasicAuth(AppSettings::xenditSecretKey(), '')
            ->acceptJson()
            ->timeout(30)
            ->post('https://api.xendit.co/v2/invoices', $payload);

        $body = $response->json() ?? [];

        if (! $response->successful()) {
            $message = is_array($body)
                ? ($body['message'] ?? $body['error_code'] ?? 'Gagal membuat invoice Xendit.')
                : 'Gagal membuat invoice Xendit.';
            throw new RuntimeException((string) $message);
        }

        $checkoutUrl = (string) ($body['invoice_url'] ?? '');
        if ($checkoutUrl === '') {
            throw new RuntimeException('Xendit tidak mengembalikan URL pembayaran.');
        }

        return [
            'checkout_url' => $checkoutUrl,
            'gateway_reference' => isset($body['id']) ? (string) $body['id'] : null,
            'raw_request' => $payload,
            'raw_response' => $body,
        ];
    }

    public function parseWebhook(Request $request): array
    {
        $token = AppSettings::xenditCallbackToken();
        if ($token !== '') {
            $incoming = (string) $request->header('x-callback-token', '');
            if (! hash_equals($token, $incoming)) {
                throw new InvalidArgumentException('Callback token Xendit tidak valid.');
            }
        }

        $payload = $request->all();
        $externalId = (string) ($payload['external_id'] ?? '');
        if ($externalId === '') {
            throw new InvalidArgumentException('external_id Xendit tidak ditemukan.');
        }

        $statusRaw = strtoupper((string) ($payload['status'] ?? ''));
        $status = match ($statusRaw) {
            'PAID', 'SETTLED' => 'paid',
            'EXPIRED' => 'expired',
            default => 'pending',
        };

        $paidAt = null;
        if ($status === 'paid') {
            $paidAt = ! empty($payload['paid_at'])
                ? Carbon::parse($payload['paid_at'])
                : now();
        }

        return [
            'external_id' => $externalId,
            'status' => $status,
            'paid_at' => $paidAt,
            'gateway_reference' => isset($payload['id']) ? (string) $payload['id'] : null,
            'raw' => $payload,
        ];
    }
}
