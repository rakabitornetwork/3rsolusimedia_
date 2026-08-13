<?php

namespace App\Services\PaymentGateway;

use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Services\PaymentGateway\Contracts\PaymentGatewayInterface;
use App\Support\AppSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class DuitkuGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'duitku';
    }

    public function isConfigured(): bool
    {
        return AppSettings::duitkuMerchantCode() !== ''
            && AppSettings::duitkuApiKey() !== '';
    }

    public function isEnabled(): bool
    {
        return AppSettings::bool('duitku_enabled', false) && $this->isConfigured();
    }

    protected function baseUrl(): string
    {
        $mode = (string) AppSettings::get('duitku_mode', 'sandbox');

        return $mode === 'live'
            ? 'https://passport.duitku.com/webapi'
            : 'https://sandbox.duitku.com/webapi';
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Merchant code / API key Duitku belum diisi.'];
        }

        $started = microtime(true);
        $merchantCode = AppSettings::duitkuMerchantCode();
        $datetime = now()->format('Y-m-d H:i:s');
        $signature = hash('sha256', $merchantCode.AppSettings::duitkuApiKey().$datetime);

        try {
            $response = Http::acceptJson()
                ->timeout(15)
                ->post($this->baseUrl().'/api/merchant/paymentmethod/getpaymentmethod', [
                    'merchantcode' => $merchantCode,
                    'amount' => 10000,
                    'datetime' => $datetime,
                    'signature' => $signature,
                ]);

            $latency = (int) round((microtime(true) - $started) * 1000);
            $body = $response->json() ?? [];

            if ($response->successful() && (string) ($body['responseCode'] ?? '') === '00') {
                return [
                    'ok' => true,
                    'message' => 'Koneksi Duitku berhasil.',
                    'latency_ms' => $latency,
                ];
            }

            $message = (string) ($body['responseMessage'] ?? ('HTTP '.$response->status()));

            return [
                'ok' => false,
                'message' => 'Duitku: '.$message,
                'latency_ms' => $latency,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Gagal menghubungi Duitku: '.$e->getMessage(),
            ];
        }
    }

    public function createCheckout(Invoice $invoice, PaymentTransaction $transaction, string $successUrl, string $failureUrl): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Duitku belum dikonfigurasi.');
        }

        $invoice->loadMissing('customer');

        $merchantCode = AppSettings::duitkuMerchantCode();
        $amount = (int) $transaction->amount;
        $merchantOrderId = $transaction->external_id;
        $signature = md5($merchantCode.$merchantOrderId.$amount.AppSettings::duitkuApiKey());

        $payload = [
            'merchantCode' => $merchantCode,
            'paymentAmount' => $amount,
            'merchantOrderId' => $merchantOrderId,
            'productDetails' => 'Tagihan '.$invoice->number,
            'email' => 'billing@'.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost'),
            'customerVaName' => $invoice->customer?->name ?: 'Pelanggan',
            'phoneNumber' => preg_replace('/\D+/', '', (string) ($invoice->customer?->phone ?? '')) ?: '08123456789',
            'callbackUrl' => url('/webhooks/duitku'),
            'returnUrl' => $successUrl,
            'expiryPeriod' => 1440,
            'signature' => $signature,
        ];

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(30)
            ->post($this->baseUrl().'/api/merchant/v2/inquiry', $payload);

        $body = $response->json() ?? [];

        if (! $response->successful() || (string) ($body['statusCode'] ?? '') !== '00') {
            $message = (string) ($body['statusMessage'] ?? 'Gagal membuat invoice Duitku.');
            throw new RuntimeException($message);
        }

        $checkoutUrl = (string) ($body['paymentUrl'] ?? '');
        if ($checkoutUrl === '') {
            throw new RuntimeException('Duitku tidak mengembalikan URL pembayaran.');
        }

        return [
            'checkout_url' => $checkoutUrl,
            'gateway_reference' => isset($body['reference']) ? (string) $body['reference'] : null,
            'raw_request' => $payload,
            'raw_response' => $body,
        ];
    }

    public function parseWebhook(Request $request): array
    {
        $payload = $request->all();
        $merchantCode = (string) ($payload['merchantCode'] ?? '');
        $amount = (string) ($payload['amount'] ?? '');
        $merchantOrderId = (string) ($payload['merchantOrderId'] ?? '');
        $signature = (string) ($payload['signature'] ?? '');
        $resultCode = (string) ($payload['resultCode'] ?? '');

        if ($merchantOrderId === '' || $signature === '') {
            throw new InvalidArgumentException('Payload callback Duitku tidak lengkap.');
        }

        $expected = md5($merchantCode.$amount.$merchantOrderId.AppSettings::duitkuApiKey());
        if (! hash_equals($expected, $signature)) {
            throw new InvalidArgumentException('Signature Duitku tidak valid.');
        }

        $status = match ($resultCode) {
            '00' => 'paid',
            '01' => 'pending',
            default => 'failed',
        };

        return [
            'external_id' => $merchantOrderId,
            'status' => $status,
            'paid_at' => $status === 'paid' ? now() : null,
            'gateway_reference' => isset($payload['reference']) ? (string) $payload['reference'] : null,
            'raw' => $payload,
        ];
    }
}
