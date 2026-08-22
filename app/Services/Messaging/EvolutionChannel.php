<?php

namespace App\Services\Messaging;

use App\Services\Messaging\Contracts\MessagingChannelInterface;
use App\Support\AppSettings;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class EvolutionChannel implements MessagingChannelInterface
{
    public function name(): string
    {
        return 'whatsapp';
    }

    public function isConfigured(): bool
    {
        return AppSettings::whatsappBaseUrl() !== ''
            && AppSettings::whatsappApiKey() !== ''
            && AppSettings::whatsappInstance() !== '';
    }

    public function isEnabled(): bool
    {
        return AppSettings::bool('whatsapp_enabled', false) && $this->isConfigured();
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'URL, API key, dan nama instance Evolution belum lengkap.'];
        }

        $started = microtime(true);
        $status = $this->connectionStatus();
        $latency = (int) round((microtime(true) - $started) * 1000);

        if (! ($status['reachable'] ?? false)) {
            return [
                'ok' => false,
                'message' => $status['message'] ?? 'Gagal menghubungi Evolution API.',
                'latency_ms' => $latency,
            ];
        }

        $state = (string) ($status['state'] ?? 'unknown');

        return [
            'ok' => true,
            'message' => $state === 'open'
                ? 'Evolution API terhubung (WhatsApp open).'
                : 'Evolution API merespons. Status sesi: '.$state.'.',
            'latency_ms' => $latency,
            'username' => AppSettings::whatsappInstance(),
        ];
    }

    public function send(string $externalId, string $text): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'WhatsApp (Evolution API) belum dikonfigurasi.'];
        }

        $number = PhoneNumber::toInternational($externalId);
        if ($number === '' || strlen($number) < 10) {
            return ['ok' => false, 'message' => 'Nomor WhatsApp tidak valid.'];
        }

        $started = microtime(true);

        try {
            $response = $this->client()->post(
                $this->url('/message/sendText/'.$this->instance()),
                [
                    'number' => $number,
                    'text' => $text,
                ],
            );
            $latency = (int) round((microtime(true) - $started) * 1000);
            $json = $response->json();

            if ($response->successful() && ! $this->looksLikeError($json, $response->status())) {
                return [
                    'ok' => true,
                    'message' => 'Pesan WhatsApp terkirim.',
                    'latency_ms' => $latency,
                ];
            }

            return [
                'ok' => false,
                'message' => $this->errorMessage($json, $response->status()),
                'latency_ms' => $latency,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Gagal mengirim WhatsApp: '.$e->getMessage(),
            ];
        }
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = AppSettings::whatsappWebhookSecret();
        if ($secret === '') {
            return true;
        }

        $provided = (string) (
            $request->query('token')
            ?: $request->header('X-Teslatech-Webhook-Secret')
            ?: $request->header('Authorization', '')
        );
        $provided = Str::of($provided)->after('Bearer ')->trim()->toString();

        return $provided !== '' && hash_equals($secret, $provided);
    }

    public function parseIncoming(Request $request): ?IncomingMessage
    {
        $payload = $request->all();
        $event = strtoupper((string) ($payload['event'] ?? ''));
        $event = str_replace('.', '_', $event);

        if ($event !== '' && $event !== 'MESSAGES_UPSERT') {
            return null;
        }

        $data = $payload['data'] ?? $payload;
        if (isset($data[0]) && is_array($data[0])) {
            $data = $data[0];
        }
        if (! is_array($data)) {
            return null;
        }

        $key = is_array($data['key'] ?? null) ? $data['key'] : [];
        if (($key['fromMe'] ?? false) === true) {
            return null;
        }

        $remoteJid = (string) ($key['remoteJid'] ?? $payload['sender'] ?? '');
        if ($remoteJid === '' || str_contains($remoteJid, '@g.us') || str_contains($remoteJid, '@broadcast')) {
            return null;
        }

        $number = PhoneNumber::toInternational($remoteJid);
        if ($number === '') {
            return null;
        }

        $text = $this->extractText($data);
        $name = trim((string) ($data['pushName'] ?? ''));

        return new IncomingMessage(
            channel: $this->name(),
            externalId: $number,
            text: $text,
            isPrivate: true,
            fromUsername: null,
            fromName: $name !== '' ? $name : null,
            raw: $payload,
        );
    }

    public function setWebhook(string $url, string $secret): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'WhatsApp (Evolution API) belum dikonfigurasi.'];
        }

        $webhookUrl = $url;
        if ($secret !== '') {
            $webhookUrl .= (str_contains($url, '?') ? '&' : '?').'token='.urlencode($secret);
        }

        $body = [
            'webhook' => [
                'enabled' => true,
                'url' => $webhookUrl,
                'byEvents' => false,
                'base64' => false,
                'events' => ['MESSAGES_UPSERT', 'CONNECTION_UPDATE', 'QRCODE_UPDATED'],
                'headers' => $secret !== '' ? [
                    'X-Teslatech-Webhook-Secret' => $secret,
                ] : new \stdClass,
            ],
        ];

        try {
            $response = $this->client()->post($this->url('/webhook/set/'.$this->instance()), $body);
            $json = $response->json();

            if ($response->successful() && ! $this->looksLikeError($json, $response->status())) {
                return ['ok' => true, 'message' => 'Webhook Evolution dipasang.'];
            }

            // Format datar (beberapa rilis v2).
            $flat = [
                'enabled' => true,
                'url' => $webhookUrl,
                'webhookByEvents' => false,
                'webhookBase64' => false,
                'events' => ['MESSAGES_UPSERT', 'CONNECTION_UPDATE', 'QRCODE_UPDATED'],
            ];
            $retry = $this->client()->post($this->url('/webhook/set/'.$this->instance()), $flat);
            $retryJson = $retry->json();

            if ($retry->successful() && ! $this->looksLikeError($retryJson, $retry->status())) {
                return ['ok' => true, 'message' => 'Webhook Evolution dipasang.'];
            }

            return [
                'ok' => false,
                'message' => $this->errorMessage($json, $response->status()),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Gagal memasang webhook Evolution: '.$e->getMessage(),
            ];
        }
    }

    public function webhookInfo(): array
    {
        $status = $this->connectionStatus();

        return [
            'ok' => ($status['state'] ?? '') === 'open',
            'message' => $status['message'] ?? 'Status Evolution belum diketahui.',
            'url' => url('/webhooks/evolution'),
            'pending' => 0,
        ];
    }

    /**
     * @return array{ok: bool, reachable: bool, state: string, message: string}
     */
    public function connectionStatus(): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'reachable' => false,
                'state' => 'unconfigured',
                'message' => 'Evolution API belum dikonfigurasi.',
            ];
        }

        try {
            $response = $this->client()->get($this->url('/instance/connectionState/'.$this->instance()));
            $json = $response->json();

            if ($response->status() === 404) {
                return [
                    'ok' => false,
                    'reachable' => $response->status() !== 0,
                    'state' => 'missing',
                    'message' => 'Instance "'.$this->instance().'" belum ada. Klik Hubungkan untuk membuatnya.',
                ];
            }

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'reachable' => true,
                    'state' => 'error',
                    'message' => $this->errorMessage($json, $response->status()),
                ];
            }

            $state = $this->readState($json);

            return [
                'ok' => $state === 'open',
                'reachable' => true,
                'state' => $state,
                'message' => match ($state) {
                    'open' => 'WhatsApp terhubung.',
                    'connecting' => 'Menunggu scan QR.',
                    'close' => 'Sesi WhatsApp terputus. Scan QR untuk menghubungkan.',
                    default => 'Status sesi: '.$state.'.',
                },
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'reachable' => false,
                'state' => 'unreachable',
                'message' => 'Gagal menghubungi Evolution API: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, message: string, state?: string, qr_base64?: ?string, pairing_code?: ?string}
     */
    public function connect(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Evolution API belum dikonfigurasi.'];
        }

        $created = $this->ensureInstance();
        if (! ($created['ok'] ?? false)) {
            return $created;
        }

        try {
            $response = $this->client()->get($this->url('/instance/connect/'.$this->instance()));
            $json = $response->json();

            if (! $response->successful() && $response->status() !== 201) {
                return [
                    'ok' => false,
                    'message' => $this->errorMessage($json, $response->status()),
                ];
            }

            $qr = $this->readQr($json);
            $pairing = $this->scalar($json, ['pairingCode', 'pairing_code']);
            $state = $this->readState($json) ?: 'connecting';

            return [
                'ok' => true,
                'message' => $qr
                    ? 'Scan QR di WhatsApp (Perangkat tertaut).'
                    : ($state === 'open' ? 'WhatsApp sudah terhubung.' : 'Menunggu koneksi WhatsApp.'),
                'state' => $state,
                'qr_base64' => $qr,
                'pairing_code' => $pairing !== '' ? $pairing : null,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Gagal mengambil QR: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function ensureInstance(): array
    {
        $status = $this->connectionStatus();
        if (($status['state'] ?? '') !== 'missing' && ($status['reachable'] ?? false)) {
            return ['ok' => true, 'message' => 'Instance siap.'];
        }

        if (! ($status['reachable'] ?? false) && ($status['state'] ?? '') === 'unreachable') {
            return ['ok' => false, 'message' => $status['message']];
        }

        try {
            $response = $this->client()->post($this->url('/instance/create'), [
                'instanceName' => $this->instance(),
                'integration' => 'WHATSAPP-BAILEYS',
                'qrcode' => true,
            ]);
            $json = $response->json();

            if ($response->successful() || in_array($response->status(), [403, 409], true)) {
                return ['ok' => true, 'message' => 'Instance Evolution siap.'];
            }

            $message = $this->errorMessage($json, $response->status());
            if (str_contains(strtolower($message), 'already') || str_contains(strtolower($message), 'exist')) {
                return ['ok' => true, 'message' => 'Instance sudah ada.'];
            }

            return ['ok' => false, 'message' => $message];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Gagal membuat instance: '.$e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractText(array $data): string
    {
        $message = is_array($data['message'] ?? null) ? $data['message'] : [];

        $candidates = [
            $message['conversation'] ?? null,
            data_get($message, 'extendedTextMessage.text'),
            data_get($message, 'ephemeralMessage.message.conversation'),
            data_get($message, 'ephemeralMessage.message.extendedTextMessage.text'),
            $data['conversation'] ?? null,
        ];

        foreach ($candidates as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    /**
     * @param  mixed  $json
     */
    private function readState(mixed $json): string
    {
        if (! is_array($json)) {
            return 'unknown';
        }

        $state = $json['state']
            ?? data_get($json, 'instance.state')
            ?? data_get($json, 'instance.instance.state')
            ?? '';

        return $state !== '' ? strtolower((string) $state) : 'unknown';
    }

    /**
     * @param  mixed  $json
     */
    private function readQr(mixed $json): ?string
    {
        if (! is_array($json)) {
            return null;
        }

        $raw = $json['base64']
            ?? data_get($json, 'qrcode.base64')
            ?? data_get($json, 'instance.qrcode.base64')
            ?? null;

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        if (! str_starts_with($raw, 'data:')) {
            $raw = 'data:image/png;base64,'.$raw;
        }

        return $raw;
    }

    /**
     * @param  mixed  $json
     * @param  list<string>  $keys
     */
    private function scalar(mixed $json, array $keys): string
    {
        if (! is_array($json)) {
            return '';
        }

        foreach ($keys as $key) {
            $value = data_get($json, $key);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout(20)
            ->withHeaders([
                'apikey' => AppSettings::whatsappApiKey(),
            ]);
    }

    private function url(string $path): string
    {
        return rtrim(AppSettings::whatsappBaseUrl(), '/').$path;
    }

    private function instance(): string
    {
        return AppSettings::whatsappInstance();
    }

    /**
     * @param  mixed  $json
     */
    private function looksLikeError(mixed $json, int $status): bool
    {
        if ($status >= 400) {
            return true;
        }

        if (! is_array($json)) {
            return false;
        }

        if (($json['status'] ?? null) >= 400) {
            return true;
        }

        return ($json['error'] ?? false) === true || is_string($json['error'] ?? null);
    }

    /**
     * @param  mixed  $json
     */
    private function errorMessage(mixed $json, int $status): string
    {
        if (is_array($json)) {
            $fromResponse = data_get($json, 'response.message');
            if (is_array($fromResponse)) {
                $fromResponse = implode(' ', array_map('strval', $fromResponse));
            }

            foreach ([
                $json['message'] ?? null,
                $json['error'] ?? null,
                $fromResponse,
                data_get($json, 'response.error'),
            ] as $candidate) {
                if (is_string($candidate) && trim($candidate) !== '' && $candidate !== 'true') {
                    return 'Evolution: '.trim($candidate);
                }
            }
        }

        return 'Evolution API menolak permintaan (HTTP '.$status.').';
    }
}
