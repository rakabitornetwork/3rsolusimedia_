<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessagingIdentity extends Model
{
    protected $fillable = [
        'pppoe_customer_id',
        'channel',
        'external_id',
        'display_name',
        'username',
        'verified_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PppoeCustomer::class, 'pppoe_customer_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MessageLog::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel,
            'external_id' => $this->external_id,
            'display_name' => $this->display_name,
            'username' => $this->username,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'username' => $this->customer->username,
                'phone' => $this->customer->phone,
            ] : null,
        ];
    }
}
