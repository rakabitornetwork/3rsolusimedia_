<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageOutbox extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $table = 'message_outbox';

    protected $fillable = [
        'channel',
        'template',
        'invoice_id',
        'pppoe_customer_id',
        'messaging_identity_id',
        'external_id',
        'body',
        'available_at',
        'sent_at',
        'status',
        'attempts',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'available_at' => 'datetime',
            'sent_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PppoeCustomer::class, 'pppoe_customer_id');
    }

    public function identity(): BelongsTo
    {
        return $this->belongsTo(MessagingIdentity::class, 'messaging_identity_id');
    }
}
