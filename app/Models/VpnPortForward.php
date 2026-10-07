<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VpnPortForward extends Model
{
    public const KIND_STANDARD = 'standard';

    public const KIND_CUSTOM = 'custom';

    protected $fillable = [
        'pppoe_customer_id',
        'public_port',
        'dst_port',
        'kind',
        'label',
        'pushed_at',
    ];

    protected function casts(): array
    {
        return [
            'public_port' => 'integer',
            'dst_port' => 'integer',
            'pushed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PppoeCustomer::class, 'pppoe_customer_id');
    }

    public function comment(): string
    {
        return 'vpn-pf-'.$this->pppoe_customer_id.'-'.$this->public_port;
    }
}
