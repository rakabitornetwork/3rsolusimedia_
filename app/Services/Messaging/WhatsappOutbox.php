<?php

namespace App\Services\Messaging;

use App\Models\MessageLog;
use App\Models\MessageOutbox;
use App\Support\AppSettings;
use App\Support\PhoneNumber;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class WhatsappOutbox
{
    public const LAST_SENT_CACHE_KEY = 'whatsapp:last_sent_at';

    public function enqueue(
        string $template,
        string $externalId,
        string $body,
        ?int $customerId = null,
        ?int $invoiceId = null,
        ?int $identityId = null,
    ): ?MessageOutbox {
        if (! $this->ready()) {
            return null;
        }

        $phone = PhoneNumber::toInternational($externalId);
        if ($phone === '' || $body === '') {
            return null;
        }

        $existing = MessageOutbox::query()
            ->where('status', MessageOutbox::STATUS_PENDING)
            ->where('channel', 'whatsapp')
            ->where('template', $template)
            ->where('external_id', $phone)
            ->when(
                $invoiceId,
                fn ($query) => $query->where('invoice_id', $invoiceId),
                fn ($query) => $query->whereNull('invoice_id'),
            )
            ->first();

        if ($existing) {
            return $existing;
        }

        return MessageOutbox::query()->create([
            'channel' => 'whatsapp',
            'template' => $template,
            'invoice_id' => $invoiceId,
            'pppoe_customer_id' => $customerId,
            'messaging_identity_id' => $identityId,
            'external_id' => $phone,
            'body' => $body,
            'available_at' => $this->nextAvailableAt(),
            'status' => MessageOutbox::STATUS_PENDING,
            'attempts' => 0,
        ]);
    }

    /**
     * @param  callable(MessageOutbox): array{ok: bool, message: string}  $send
     * @return array{sent: int, failed: int, postponed: int}
     */
    public function dispatch(callable $send): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'postponed' => 0];

        if (! $this->ready()) {
            return $result;
        }

        $this->releaseStuck();

        $limit = AppSettings::whatsappSendBatch();
        $dailyLimit = AppSettings::whatsappDailyLimit();
        $sentToday = $this->sentToday();

        $due = MessageOutbox::query()
            ->with('identity')
            ->where('channel', 'whatsapp')
            ->where('status', MessageOutbox::STATUS_PENDING)
            ->where('available_at', '<=', now())
            ->orderBy('available_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($due as $item) {
            if ($dailyLimit > 0 && $sentToday >= $dailyLimit) {
                $item->update([
                    'available_at' => now()->addDay()->setTime(8, 0),
                    'error_message' => 'Ditunda: batas harian WhatsApp tercapai.',
                ]);
                $result['postponed']++;

                continue;
            }

            $claimed = MessageOutbox::query()
                ->where('id', $item->id)
                ->where('status', MessageOutbox::STATUS_PENDING)
                ->update([
                    'status' => MessageOutbox::STATUS_SENDING,
                    'attempts' => $item->attempts + 1,
                ]);

            if ($claimed !== 1) {
                continue;
            }

            $item->refresh();
            $response = $send($item);
            $ok = (bool) ($response['ok'] ?? false);

            if ($ok) {
                $item->update([
                    'status' => MessageOutbox::STATUS_SENT,
                    'sent_at' => now(),
                    'error_message' => null,
                ]);
                $this->markSentNow();
                $result['sent']++;
                $sentToday++;

                continue;
            }

            $failed = $item->attempts >= 3;
            $item->update([
                'status' => $failed ? MessageOutbox::STATUS_FAILED : MessageOutbox::STATUS_PENDING,
                'available_at' => $failed ? $item->available_at : now()->addMinutes(5),
                'error_message' => mb_substr((string) ($response['message'] ?? 'Gagal mengirim'), 0, 500),
            ]);
            $result['failed']++;
        }

        return $result;
    }

    public function pendingCount(): int
    {
        if (! $this->ready()) {
            return 0;
        }

        return MessageOutbox::query()
            ->where('channel', 'whatsapp')
            ->whereIn('status', [MessageOutbox::STATUS_PENDING, MessageOutbox::STATUS_SENDING])
            ->count();
    }

    public function enabled(): bool
    {
        return $this->ready();
    }

    public function markSentNow(): void
    {
        Cache::put(self::LAST_SENT_CACHE_KEY, now()->toIso8601String(), now()->addDay());
    }

    public function nextAvailableAt(): Carbon
    {
        $min = AppSettings::whatsappDelayMin();
        $max = AppSettings::whatsappDelayMax();
        $jitter = random_int($min, $max);
        $last = $this->lastSlot();

        if ($last === null || $last->lte(now()->subSeconds($min))) {
            return now();
        }

        return $last->copy()->addSeconds($jitter);
    }

    private function lastSlot(): ?Carbon
    {
        $latest = null;
        $cached = Cache::get(self::LAST_SENT_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            $latest = $this->later($latest, Carbon::parse($cached));
        } elseif (is_numeric($cached)) {
            $latest = $this->later($latest, Carbon::createFromTimestamp((int) $cached));
        }

        if (! $this->ready()) {
            return $latest;
        }

        $pending = MessageOutbox::query()
            ->where('channel', 'whatsapp')
            ->whereIn('status', [MessageOutbox::STATUS_PENDING, MessageOutbox::STATUS_SENDING])
            ->max('available_at');

        if ($pending) {
            $latest = $this->later($latest, Carbon::parse($pending));
        }

        $sent = MessageOutbox::query()
            ->where('channel', 'whatsapp')
            ->where('status', MessageOutbox::STATUS_SENT)
            ->max('sent_at');

        if ($sent) {
            $latest = $this->later($latest, Carbon::parse($sent));
        }

        return $latest;
    }

    private function later(?Carbon $current, Carbon $candidate): Carbon
    {
        if ($current === null || $candidate->gt($current)) {
            return $candidate;
        }

        return $current;
    }

    private function sentToday(): int
    {
        if (! Schema::hasTable('message_logs')) {
            return 0;
        }

        return MessageLog::query()
            ->where('channel', 'whatsapp')
            ->where('direction', 'outbound')
            ->where('status', 'sent')
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }

    private function releaseStuck(): void
    {
        MessageOutbox::query()
            ->where('status', MessageOutbox::STATUS_SENDING)
            ->where('updated_at', '<', now()->subMinutes(10))
            ->update(['status' => MessageOutbox::STATUS_PENDING]);
    }

    private function ready(): bool
    {
        return Schema::hasTable('message_outbox');
    }
}
