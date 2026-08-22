<?php

namespace App\Services\Messaging;

use App\Services\Messaging\Contracts\MessagingChannelInterface;
use App\Support\AppSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TelegramChannel implements MessagingChannelInterface
{
    public function name(): string
    {
        return 'telegram';
    }

    public function isConfigured(): bool
    {
        return AppSettings::telegramBotToken() !== '';
    }

    public function isEnabled(): bool
    {
        return AppSettings::bool('telegram_enabled', false) && $this->isConfigured();
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Token bot Telegram belum diisi.'];
        }

        $started = microtime(true);

        try {
            $response = $this->client()->get($this->methodUrl('getMe'));
            $latency = (int) round((microtime(true) - $started) * 1000);

            if ($this->isOk($response->json())) {
                $username = ltrim((string) data_get($response->json(), 'result.username', ''), '@');

                return [
                    'ok' => true,
                    'message' => $username !== ''
                        ? 'Koneksi Telegram berhasil (@'.$username.').'
                        : 'Koneksi Telegram berhasil.',
                    'latency_ms' => $latency,
                    'username' => $username !== '' ? $username : null,
                ];
            }

            return [
                'ok' => false,
                'message' => $this->errorMessage($response->json(), $response->status()),
                'latency_ms' => $latency,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Gagal menghubungi Telegram: '.$e->getMessage(),
            ];
        }
    }

    public function send(string $externalId, string $text): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Token bot Telegram belum diisi.'];
        }

        $started = microtime(true);

        try {
            $response = $this->client()->post($this->methodUrl('sendMessage'), [
                'chat_id' => $externalId,
                'text' => $text,
            ]);
            $latency = (int) round((microtime(true) - $started) * 1000);

            if ($this->isOk($response->json())) {
                return [
                    'ok' => true,
                    'message' => 'Pesan terkirim.',
                    'latency_ms' => $latency,
                ];
            }

            return [
                'ok' => false,
                'message' => $this->errorMessage($response->json(), $response->status()),
                'latency_ms' => $latency,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Gagal mengirim pesan Telegram: '.$e->getMessage(),
            ];
        }
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = AppSettings::telegramWebhookSecret();
        if ($secret === '') {
            return true;
        }

        $header = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        return $header !== '' && hash_equals($secret, $header);
    }

    public function parseIncoming(Request $request): ?IncomingMessage
    {
        $payload = $request->all();
        $message = $payload['message'] ?? null;
        if (! is_array($message)) {
            return null;
        }

        $text = trim((string) ($message['text'] ?? $message['caption'] ?? ''));
        $chat = is_array($message['chat'] ?? null) ? $message['chat'] : [];
        $from = is_array($message['from'] ?? null) ? $message['from'] : [];
        $chatId = (string) ($chat['id'] ?? '');

        if ($chatId === '') {
            return null;
        }

        $first = trim((string) ($from['first_name'] ?? ''));
        $last = trim((string) ($from['last_name'] ?? ''));
        $name = trim($first.' '.$last);

        return new IncomingMessage(
            channel: $this->name(),
            externalId: $chatId,
            text: $text,
            isPrivate: ($chat['type'] ?? 'private') === 'private',
            fromUsername: isset($from['username']) ? (string) $from['username'] : null,
            fromName: $name !== '' ? $name : null,
            raw: $payload,
        );
    }

    public function setWebhook(string $url, string $secret): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Token bot Telegram belum diisi.'];
        }

        try {
            $payload = [
                'url' => $url,
                'allowed_updates' => ['message'],
                'drop_pending_updates' => true,
            ];
            if ($secret !== '') {
                $payload['secret_token'] = $secret;
            }

            $response = $this->client()->post($this->methodUrl('setWebhook'), $payload);

            if ($this->isOk($response->json())) {
                return ['ok' => true, 'message' => 'Webhook Telegram dipasang.'];
            }

            return [
                'ok' => false,
                'message' => $this->errorMessage($response->json(), $response->status()),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Gagal memasang webhook: '.$e->getMessage(),
            ];
        }
    }

    public function webhookInfo(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Token bot Telegram belum diisi.'];
        }

        try {
            $response = $this->client()->get($this->methodUrl('getWebhookInfo'));
            $json = $response->json();

            if (! $this->isOk($json)) {
                return [
                    'ok' => false,
                    'message' => $this->errorMessage($json, $response->status()),
                ];
            }

            $result = is_array($json['result'] ?? null) ? $json['result'] : [];
            $url = trim((string) ($result['url'] ?? ''));
            $pending = (int) ($result['pending_update_count'] ?? 0);
            $lastError = trim((string) ($result['last_error_message'] ?? ''));

            $message = $url === ''
                ? 'Webhook belum dipasang.'
                : 'Webhook aktif.';
            if ($lastError !== '') {
                $message .= ' Error terakhir: '.$lastError;
            }

            return [
                'ok' => $url !== '',
                'message' => $message,
                'url' => $url !== '' ? $url : null,
                'pending' => $pending,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Gagal membaca status webhook: '.$e->getMessage(),
            ];
        }
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::acceptJson()->asJson()->timeout(15);
    }

    private function methodUrl(string $method): string
    {
        return 'https://api.telegram.org/bot'.AppSettings::telegramBotToken().'/'.$method;
    }

    /**
     * @param  mixed  $json
     */
    private function isOk(mixed $json): bool
    {
        return is_array($json) && ($json['ok'] ?? false) === true;
    }

    /**
     * @param  mixed  $json
     */
    private function errorMessage(mixed $json, int $status): string
    {
        $description = is_array($json) ? trim((string) ($json['description'] ?? '')) : '';
        if ($description !== '') {
            return 'Telegram: '.$description;
        }

        return 'Telegram menolak permintaan (HTTP '.$status.').';
    }
}
