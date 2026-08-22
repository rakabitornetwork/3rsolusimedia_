<?php

namespace App\Services\Messaging;

final class IncomingMessage
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $channel,
        public readonly string $externalId,
        public readonly string $text,
        public readonly bool $isPrivate = true,
        public readonly ?string $fromUsername = null,
        public readonly ?string $fromName = null,
        public readonly array $raw = [],
        public readonly ?string $callbackData = null,
        public readonly ?string $callbackQueryId = null,
        public readonly ?int $messageId = null,
    ) {
    }

    public function isCallback(): bool
    {
        return $this->callbackData !== null && $this->callbackData !== '';
    }
}
