<?php

namespace App\Services\Messaging;

use App\Services\Messaging\Contracts\MessagingChannelInterface;
use InvalidArgumentException;

class MessagingManager
{
    /** @var array<string, MessagingChannelInterface> */
    private array $drivers;

    public function __construct(
        TelegramChannel $telegram,
        EvolutionChannel $whatsapp,
    ) {
        $this->drivers = [
            'telegram' => $telegram,
            'whatsapp' => $whatsapp,
        ];
    }

    public function driver(?string $name = null): MessagingChannelInterface
    {
        $name ??= 'telegram';

        if (! isset($this->drivers[$name])) {
            throw new InvalidArgumentException("Kanal messaging \"{$name}\" tidak dikenal.");
        }

        return $this->drivers[$name];
    }

    /**
     * @return list<string>
     */
    public function enabledChannels(): array
    {
        return array_values(array_filter(
            array_keys($this->drivers),
            fn (string $name) => $this->drivers[$name]->isEnabled()
        ));
    }

    /**
     * @param  array<string, mixed>|null  $replyMarkup
     * @return array{ok: bool, message: string, latency_ms?: int}
     */
    public function send(string $channel, string $externalId, string $text, ?array $replyMarkup = null): array
    {
        $driver = $this->driver($channel);
        if ($replyMarkup !== null && $replyMarkup !== [] && $driver instanceof TelegramChannel) {
            return $driver->send($externalId, $text, $replyMarkup);
        }

        return $driver->send($externalId, $text);
    }
}
