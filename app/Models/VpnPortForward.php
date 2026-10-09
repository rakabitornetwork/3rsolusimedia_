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
        'vpn_router_id',
        'public_port',
        'public_port_end',
        'dst_port',
        'dst_port_end',
        'kind',
        'label',
        'pushed_at',
    ];

    protected function casts(): array
    {
        return [
            'public_port' => 'integer',
            'public_port_end' => 'integer',
            'dst_port' => 'integer',
            'dst_port_end' => 'integer',
            'pushed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PppoeCustomer::class, 'pppoe_customer_id');
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(VpnRouter::class, 'vpn_router_id');
    }

    public function comment(): string
    {
        return 'vpn-pf-'.$this->pppoe_customer_id.'-'.$this->public_port;
    }

    public function publicPortSpec(): string
    {
        return $this->portSpec($this->public_port, $this->public_port_end);
    }

    public function dstPortSpec(): string
    {
        return $this->portSpec($this->dst_port, $this->dst_port_end);
    }

    private function portSpec(int $start, ?int $end): string
    {
        if ($end !== null && $end > $start) {
            return $start.'-'.$end;
        }

        return (string) $start;
    }
}
