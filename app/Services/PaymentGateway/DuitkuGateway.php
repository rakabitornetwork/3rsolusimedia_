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

    /**
     * Endpoint klasik (get payment method / inquiry lama).
     */
    protected function classicBaseUrl(): string
    {
        $mode = (string) AppSettings::get('duitku_mode', 'sandbox');

        return $mode === 'live'
            ? 'https://passport.duitku.com/webapi'
            : 'https://sandbox.duitku.com/webapi';
    }

    /**
     * Endpoint Duitku POP (createInvoice + halaman pilih metode).
     */
    protected function popCreateInvoiceUrl(): string
    {
        $mode = (string) AppSettings::get('duitku_mode', 'sandbox');

        return $mode === 'live'
            ? 'https://api-prod.duitku.com/api/merchant/createInvoice'
            : 'https://api-sandbox.duitku.com/api/merchant/createInvoice';
    }

    protected function hmacSign(string $stringToSign): string
    {
        return hash_hmac('sha256', $stringToSign, AppSettings::duitkuApiKey());
    }

    protected function customerEmail(?string $fallbackName = null): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: '';
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.test')) {
            return 'customer@mail.com';
        }

        return 'billing@'.$host;
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
            $response = Http::asJson()
                ->acceptJson()
                ->timeout(15)
                ->post($this->classicBaseUrl().'/api/merchant/paymentmethod/getpaymentmethod', [
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
            $transaction->update(['external_id' => $merchantOrderId]);
        }

        $email = $this->customerEmail();
        $phone = preg_replace('/\D+/', '', (string) ($invoice->customer?->phone ?? '')) ?: '08123456789';
        $customerName = trim((string) ($invoice->customer?->name ?: 'Pelanggan'));
        $nameParts = preg_split('/\s+/', $customerName, 2) ?: [$customerName];
        $firstName = $nameParts[0] ?: 'Pelanggan';
        $lastName = $nameParts[1] ?? $firstName;

        $timestamp = (string) (int) round(microtime(true) * 1000);
        $signature = $this->hmacSign($merchantCode.$timestamp);

        // Duitku POP: paymentMethod dikosongkan agar pelanggan pilih di halaman Duitku.
        $payload = [
            'paymentAmount' => $amount,
            'merchantOrderId' => $merchantOrderId,
            'productDetails' => 'Tagihan '.$invoice->number,
            'additionalParam' => (string) $invoice->id,
            'customerVaName' => mb_substr($customerName, 0, 20),
            'email' => $email,
            'phoneNumber' => $phone,
            'itemDetails' => [
                [
                    'name' => mb_substr('Tagihan '.$invoice->number, 0, 100),
                    'price' => $amount,
                    'quantity' => 1,
                ],
            ],
            'customerDetail' => [
                'firstName' => mb_substr($firstName, 0, 50),
                'lastName' => mb_substr($lastName, 0, 50),
                'email' => $email,
                'phoneNumber' => $phone,
            ],
            'callbackUrl' => url('/webhooks/duitku'),
            'returnUrl' => $successUrl,
            'expiryPeriod' => 1440,
        ];

        $response = Http::asJson()
            ->acceptJson()
            ->timeout(30)
            ->withHeaders([
                'x-duitku-merchantcode' => $merchantCode,
                'x-duitku-timestamp' => $timestamp,
                'x-duitku-signature' => $signature,
            ])
            ->post($this->popCreateInvoiceUrl(), $payload);

        $body = $response->json() ?? [];

        $statusCode = (string) ($body['statusCode'] ?? '');
        $checkoutUrl = (string) ($body['paymentUrl'] ?? '');

        if (! $response->successful() || ($statusCode !== '' && $statusCode !== '00') || $checkoutUrl === '') {
            $message = (string) (
                $body['statusMessage']
                ?? $body['responseMessage']
                ?? $body['Message']
                ?? $body['message']
                ?? ('Gagal membuat invoice Duitku (HTTP '.$response->status().').')
            );
            throw new RuntimeException($message);
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
