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

    /**
     * Signature API Duitku (sejak Apr 2026): HMAC-SHA256.
     */
    protected function hmacSign(string $stringToSign): string
    {
        return hash_hmac('sha256', $stringToSign, AppSettings::duitkuApiKey());
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Merchant code / API key Duitku belum diisi.'];
        }

        $started = microtime(true);
        $merchantCode = AppSettings::duitkuMerchantCode();
        $amount = 10000;
        $datetime = now()->format('Y-m-d H:i:s');
        $signature = $this->hmacSign($merchantCode.$amount.$datetime);

        try {
            // Docs: Content-Type application/json + HMAC SHA256(merchantcode + amount + datetime, apiKey)
            $response = Http::asJson()
                ->acceptJson()
                ->timeout(15)
                ->post($this->baseUrl().'/api/merchant/paymentmethod/getpaymentmethod', [
                    'merchantcode' => $merchantCode,
                    'amount' => $amount,
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

            $message = (string) (
                $body['responseMessage']
                ?? $body['Message']
                ?? $body['message']
                ?? ('HTTP '.$response->status())
            );

            if ($response->status() === 401 || str_contains(strtolower($message), 'signature')) {
                $message = 'Signature / API key ditolak. Pastikan mode (sandbox/live) cocok dengan kredensial dashboard.';
            } elseif ($response->status() === 404) {
                $message = 'Merchant code tidak ditemukan di lingkungan '.AppSettings::get('duitku_mode', 'sandbox').'.';
            } elseif ($response->status() === 403) {
                $message = 'Akses ditolak (HTTP 403). Periksa merchant code, API key, dan mode sandbox/live.';
            }

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

        if (strlen($merchantOrderId) > 50) {
            $merchantOrderId = substr($merchantOrderId, 0, 50);
        }

        // Docs: HMAC_SHA256(merchantCode + merchantOrderId + paymentAmount, apiKey)
        $signature = $this->hmacSign($merchantCode.$merchantOrderId.$amount);

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

        $response = Http::asJson()
            ->acceptJson()
            ->timeout(30)
            ->post($this->baseUrl().'/api/merchant/v2/inquiry', $payload);

        $body = $response->json() ?? [];

        if (! $response->successful() || (string) ($body['statusCode'] ?? '') !== '00') {
            $message = (string) (
                $body['statusMessage']
                ?? $body['responseMessage']
                ?? $body['Message']
                ?? ('Gagal membuat invoice Duitku (HTTP '.$response->status().').')
            );
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

        $apiKey = AppSettings::duitkuApiKey();
        // Docs baru: HMAC; legacy md5 masih diterima untuk kompatibilitas callback lama.
        $hmacExpected = hash_hmac('sha256', $merchantCode.$amount.$merchantOrderId, $apiKey);
        $md5Expected = md5($merchantCode.$amount.$merchantOrderId.$apiKey);

        if (! hash_equals($hmacExpected, $signature) && ! hash_equals($md5Expected, $signature)) {
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
