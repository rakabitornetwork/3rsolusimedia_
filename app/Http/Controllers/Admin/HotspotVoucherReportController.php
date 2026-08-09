<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HotspotVoucher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HotspotVoucherReportController extends Controller
{
    public function index(Request $request): Response
    {
        $preset = (string) $request->get('preset', 'this_month');
        [$from, $to, $preset] = $this->resolvePeriod(
            $preset,
            $request->get('from'),
            $request->get('to'),
        );

        $fromStart = $from->copy()->startOfDay();
        $toEnd = $to->copy()->endOfDay();
        $agentFilter = $request->integer('agent_id') ?: null;

        $baseQuery = HotspotVoucher::query()
            ->whereBetween('created_at', [$fromStart, $toEnd])
            ->when($agentFilter, fn ($q) => $q->where('agent_id', $agentFilter));

        $voucherCount = (clone $baseQuery)->count();
        $baseSales = (int) (clone $baseQuery)->sum('base_price');
        $commissionTotal = (int) (clone $baseQuery)->sum('commission');
        $grossTotal = (int) (clone $baseQuery)->sum('sell_price');

        $usedCount = (clone $baseQuery)->where('status', HotspotVoucher::STATUS_USED)->count();
        $availableCount = (clone $baseQuery)->where('status', HotspotVoucher::STATUS_AVAILABLE)->count();

        $byAgent = HotspotVoucher::query()
            ->select(
                'agent_id',
                'agent_name',
                DB::raw('COUNT(*) as voucher_count'),
                DB::raw('SUM(base_price) as base_total'),
                DB::raw('SUM(commission) as commission_total'),
                DB::raw('SUM(sell_price) as sell_total'),
            )
            ->whereBetween('created_at', [$fromStart, $toEnd])
            ->when($agentFilter, fn ($q) => $q->where('agent_id', $agentFilter))
            ->groupBy('agent_id', 'agent_name')
            ->orderByDesc('sell_total')
            ->get()
            ->map(function ($row) {
                $base = (int) ($row->base_total ?? 0);
                $commission = (int) ($row->commission_total ?? 0);
                $sell = (int) ($row->sell_total ?? 0);

                return [
                    'agent_id' => $row->agent_id,
                    'agent_name' => $row->agent_name ?: 'Tanpa agen (penjualan langsung)',
                    'voucher_count' => (int) $row->voucher_count,
                    'base_total' => $base,
                    'base_total_label' => $this->rupiah($base),
                    'commission_total' => $commission,
                    'commission_total_label' => $this->rupiah($commission),
                    'sell_total' => $sell,
                    'sell_total_label' => $this->rupiah($sell),
                ];
            })
            ->values()
            ->all();

        $recent = HotspotVoucher::query()
            ->with(['router:id,name'])
            ->whereBetween('created_at', [$fromStart, $toEnd])
            ->when($agentFilter, fn ($q) => $q->where('agent_id', $agentFilter))
            ->latest('id')
            ->limit(40)
            ->get()
            ->map(fn (HotspotVoucher $v) => [
                ...$v->toCardArray(),
                'router_name' => $v->router?->name,
                'status_label' => match ($v->status) {
                    HotspotVoucher::STATUS_USED => 'Terpakai',
                    HotspotVoucher::STATUS_DELETED => 'Dihapus',
                    default => 'Tersedia',
                },
            ])
            ->all();

        $agents = User::query()
            ->where('role', User::ROLE_AGEN)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
            ])
            ->values();

        return Inertia::render('Admin/Network/Hotspot/Report', [
            'filters' => [
                'preset' => $preset,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'agent_id' => $agentFilter,
            ],
            'presets' => [
                ['value' => 'today', 'label' => 'Hari ini'],
                ['value' => 'this_week', 'label' => 'Minggu ini'],
                ['value' => 'this_month', 'label' => 'Bulan ini'],
                ['value' => 'last_month', 'label' => 'Bulan lalu'],
                ['value' => 'custom', 'label' => 'Kustom'],
            ],
            'agents' => $agents,
            'summary' => [
                'voucher_count' => $voucherCount,
                'available_count' => $availableCount,
                'used_count' => $usedCount,
                'base_sales' => $baseSales,
                'base_sales_label' => $this->rupiah($baseSales),
                'commission_total' => $commissionTotal,
                'commission_total_label' => $this->rupiah($commissionTotal),
                'gross_total' => $grossTotal,
                'gross_total_label' => $this->rupiah($grossTotal),
            ],
            'by_agent' => $byAgent,
            'recent' => $recent,
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function resolvePeriod(string $preset, mixed $from, mixed $to): array
    {
        $now = now();

        return match ($preset) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'today'],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek(), 'this_week'],
            'last_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
                'last_month',
            ],
            'custom' => [
                Carbon::parse($from ?: $now->toDateString())->startOfDay(),
                Carbon::parse($to ?: $now->toDateString())->endOfDay(),
                'custom',
            ],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'this_month'],
        };
    }

    private function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
