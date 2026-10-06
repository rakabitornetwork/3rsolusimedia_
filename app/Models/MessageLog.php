<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageLog extends Model
{
    protected $fillable = [
        'channel',
        'direction',
        'messaging_identity_id',
        'pppoe_customer_id',
        'external_id',
        'command',
        'status',
        'body',
        'error_message',
    ];

    public function identity(): BelongsTo
    {
        return $this->belongsTo(MessagingIdentity::class, 'messaging_identity_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PppoeCustomer::class, 'pppoe_customer_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel,
            'direction' => $this->direction,
            'external_id' => $this->external_id,
            'command' => $this->command,
            'status' => $this->status,
            'body' => $this->body,
            'error_message' => $this->error_message,
            'customer_name' => $this->customer?->name,
            'customer_username' => $this->customer?->username,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
