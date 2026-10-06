<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotspotVoucher extends Model
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_USED = 'used';

    public const STATUS_DELETED = 'deleted';

    protected $fillable = [
        'batch_id',
        'mikrotik_router_id',
        'agent_id',
        'created_by',
        'username',
        'password',
        'profile',
        'server',
        'limit_uptime',
        'limit_bytes_total',
        'comment',
        'code_format',
        'agent_name',
        'base_price',
        'commission',
        'sell_price',
        'status',
        'used_at',
        'deleted_from_router_at',
    ];

    protected function casts(): array
    {
        return [
            'limit_bytes_total' => 'integer',
            'base_price' => 'integer',
            'commission' => 'integer',
            'sell_price' => 'integer',
            'used_at' => 'datetime',
            'deleted_from_router_at' => 'datetime',
        ];
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(MikrotikRouter::class, 'mikrotik_router_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function toCardArray(): array
    {
        return [
            'id' => $this->id,
            'batch_id' => $this->batch_id,
            'username' => $this->username,
            'password' => $this->password,
            'profile' => $this->profile,
            'server' => $this->server,
            'limit_uptime' => $this->limit_uptime,
            'agent_name' => $this->agent_name,
            'base_price' => $this->base_price,
            'commission' => $this->commission,
            'sell_price' => $this->sell_price,
            'sell_price_label' => 'Rp '.number_format($this->sell_price, 0, ',', '.'),
            'base_price_label' => 'Rp '.number_format($this->base_price, 0, ',', '.'),
            'commission_label' => 'Rp '.number_format($this->commission, 0, ',', '.'),
            'comment' => $this->comment,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
