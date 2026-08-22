<?php

namespace App\Services\Messaging\Contracts;

use App\Services\Messaging\IncomingMessage;
use Illuminate\Http\Request;

interface MessagingChannelInterface
{
    public function name(): string;

    public function isConfigured(): bool;

    public function isEnabled(): bool;

    /**
     * @return array{ok: bool, message: string, latency_ms?: int, username?: ?string}
     */
    public function testConnection(): array;

    /**
     * @return array{ok: bool, message: string, latency_ms?: int}
     */
    public function send(string $externalId, string $text): array;

    public function verifyWebhook(Request $request): bool;

    public function parseIncoming(Request $request): ?IncomingMessage;

    /**
     * @return array{ok: bool, message: string}
     */
    public function setWebhook(string $url, string $secret): array;

    /**
     * @return array{ok: bool, message: string, url?: ?string, pending?: int}
     */
    public function webhookInfo(): array;
}
