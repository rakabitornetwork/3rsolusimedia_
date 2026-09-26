<?php

namespace App\Services\Portal;

use App\Models\PppoeCustomer;
use App\Services\Messaging\EvolutionChannel;
use App\Services\Messaging\WhatsAppIdentityBinder;
use App\Support\AppSettings;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Cache;

class PortalWhatsappLogin
{
    public const SESSION_PHONE = 'portal_otp_phone';

    private const CODE_TTL_SECONDS = 300;

    private const MAX_ATTEMPTS = 3;

    private const COOLDOWN_SECONDS = 60;

    private const HOURLY_LIMIT = 5;

    public function __construct(
        private readonly WhatsAppIdentityBinder $binder,
        private readonly EvolutionChannel $whatsapp,
    ) {}

    public function isEnabled(): bool
    {
        return $this->whatsapp->isEnabled();
    }

    /**
     * @return array{pending: bool, phone: string, phone_mask: string, enabled: bool}
     */
    public function pageState(?string $sessionPhone): array
    {
        $phone = trim((string) $sessionPhone);

        return [
            'enabled' => $this->isEnabled(),
            'pending' => $phone !== '',
            'phone' => $phone,
            'phone_mask' => $phone !== '' ? $this->mask($phone) : '',
        ];
    }

    /**
     * @return array{status: string, pending: bool, phone: string, message: string}
     */
    public function requestCode(string $phone): array
    {
        $intl = PhoneNumber::toInternational($phone);
        if ($intl === '' || strlen($intl) < 10) {
            return $this->result('invalid', false, '', 'Nomor WhatsApp tidak valid.');
        }

        if (! $this->isEnabled()) {
            return $this->result(
                'unavailable',
                false,
                '',
                'Login WhatsApp belum aktif. Masuk dengan username PPPoE dan nomor telepon.',
            );
        }

        if (Cache::has($this->cooldownKey($intl))) {
            return $this->result('limited', false, $intl, 'Tunggu sebentar sebelum meminta kode baru.');
        }

        $hourKey = $this->hourlyKey($intl);
        if ((int) Cache::get($hourKey, 0) >= self::HOURLY_LIMIT) {
            return $this->result(
                'limited',
                false,
                $intl,
                'Terlalu banyak permintaan kode untuk nomor ini. Coba lagi nanti, atau masuk dengan username PPPoE.',
            );
        }

        $matches = $this->binder->customersForNumber($intl);
        if ($matches->count() > 1) {
            return $this->result(
                'ambiguous',
                false,
                '',
                'Nomor ini terdaftar pada lebih dari satu akun. Masuk dengan username PPPoE.',
            );
        }

        if ($matches->isEmpty()) {
            $this->markRequested($intl);

            return $this->result(
                'silent',
                true,
                $intl,
                'Jika nomor ini terdaftar, kode masuk sudah dikirim ke WhatsApp. Berlaku 5 menit.',
            );
        }

        /** @var PppoeCustomer $customer */
        $customer = $matches->first();
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $company = AppSettings::companyName();
        $body = "Kode masuk portal {$company}: {$code}\nBerlaku 5 menit. Jangan berikan kode ini kepada siapa pun.";

        $sent = $this->whatsapp->send($intl, $body);
        if (! ($sent['ok'] ?? false)) {
            $this->markRequested($intl);

            return $this->result(
                'unavailable',
                false,
                '',
                'WhatsApp belum bisa mengirim kode saat ini. Masuk dengan username PPPoE dan nomor telepon.',
            );
        }

        Cache::put($this->codeKey($intl), [
            'customer_id' => $customer->id,
            'hash' => $this->hashCode($code),
            'attempts' => 0,
            'expires_at' => now()->addSeconds(self::CODE_TTL_SECONDS)->getTimestamp(),
        ], now()->addSeconds(self::CODE_TTL_SECONDS));

        $this->markRequested($intl);

        return $this->result(
            'sent',
            true,
            $intl,
            'Jika nomor ini terdaftar, kode masuk sudah dikirim ke WhatsApp. Berlaku 5 menit.',
        );
    }

    /**
     * @return array{ok: bool, message: string, customer: ?PppoeCustomer}
     */
    public function verifyCode(string $phone, string $code): array
    {
        $intl = PhoneNumber::toInternational($phone);
        $digits = preg_replace('/\D+/', '', $code) ?? '';
        if ($intl === '' || strlen($digits) !== 6) {
            return [
                'ok' => false,
                'message' => 'Masukkan 6 digit kode dari WhatsApp.',
                'customer' => null,
            ];
        }

        $key = $this->codeKey($intl);
        $payload = Cache::get($key);
        if (! is_array($payload) || (int) ($payload['expires_at'] ?? 0) < now()->getTimestamp()) {
            Cache::forget($key);

            return [
                'ok' => false,
                'message' => 'Kode tidak berlaku atau sudah kedaluwarsa. Minta kode baru.',
                'customer' => null,
            ];
        }

        if ((int) ($payload['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
            Cache::forget($key);

            return [
                'ok' => false,
                'message' => 'Kode salah terlalu banyak. Minta kode baru.',
                'customer' => null,
            ];
        }

        if (! hash_equals((string) ($payload['hash'] ?? ''), $this->hashCode($digits))) {
            $attempts = (int) $payload['attempts'] + 1;
            if ($attempts >= self::MAX_ATTEMPTS) {
                Cache::forget($key);

                return [
                    'ok' => false,
                    'message' => 'Kode salah terlalu banyak. Minta kode baru.',
                    'customer' => null,
                ];
            }

            $payload['attempts'] = $attempts;
            $ttl = max(1, (int) $payload['expires_at'] - now()->getTimestamp());
            Cache::put($key, $payload, now()->addSeconds($ttl));

            return [
                'ok' => false,
                'message' => 'Kode tidak sesuai.',
                'customer' => null,
            ];
        }

        $customer = PppoeCustomer::query()->find((int) ($payload['customer_id'] ?? 0));
        $unique = $this->binder->uniqueCustomerForNumber($intl);
        if (! $customer || ! $unique || (int) $unique->id !== (int) $customer->id) {
            Cache::forget($key);

            return [
                'ok' => false,
                'message' => 'Kode tidak berlaku atau sudah kedaluwarsa. Minta kode baru.',
                'customer' => null,
            ];
        }

        Cache::forget($key);

        return [
            'ok' => true,
            'message' => '',
            'customer' => $customer,
        ];
    }

    public function forget(string $phone): void
    {
        $intl = PhoneNumber::toInternational($phone);
        if ($intl === '') {
            return;
        }

        Cache::forget($this->codeKey($intl));
    }

    public function mask(string $phone): string
    {
        $digits = PhoneNumber::toInternational($phone);
        if (strlen($digits) < 8) {
            return $digits;
        }

        return substr($digits, 0, 4).str_repeat('*', max(1, strlen($digits) - 7)).substr($digits, -3);
    }

    private function markRequested(string $intl): void
    {
        Cache::put($this->cooldownKey($intl), 1, now()->addSeconds(self::COOLDOWN_SECONDS));
        $hourKey = $this->hourlyKey($intl);
        Cache::put($hourKey, (int) Cache::get($hourKey, 0) + 1, now()->addHour());
    }

    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function codeKey(string $intl): string
    {
        return 'portal_otp:code:'.$intl;
    }

    private function cooldownKey(string $intl): string
    {
        return 'portal_otp:cooldown:'.$intl;
    }

    private function hourlyKey(string $intl): string
    {
        return 'portal_otp:hour:'.$intl;
    }

    /**
     * @return array{status: string, pending: bool, phone: string, message: string}
     */
    private function result(string $status, bool $pending, string $phone, string $message): array
    {
        return [
            'status' => $status,
            'pending' => $pending,
            'phone' => $phone,
            'message' => $message,
        ];
    }
}
