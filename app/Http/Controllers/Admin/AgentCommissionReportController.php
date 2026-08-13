<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AgentCommissionReportController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();
        $preset = (string) $request->get('preset', 'this_month');
        [$from, $to, $preset] = $this->resolvePeriod(
            $preset,
            $request->get('from'),
            $request->get('to'),
        );

        $fromStart = $from->copy()->startOfDay();
        $toEnd = $to->copy()->endOfDay();

        $agentFilter = $actor->isAgen()
            ? (int) $actor->id
            : ($request->integer('agent_id') ?: null);

        $baseQuery = Payment::query()
            ->whereNotNull('agent_id')
            ->where('agent_commission', '>', 0)
            ->whereBetween('paid_at', [$fromStart, $toEnd])
            ->whereHas('invoice', fn ($q) => $q->where('status', 'paid'))
            ->when($agentFilter, fn ($q) => $q->where('agent_id', $agentFilter));

        $paymentCount = (clone $baseQuery)->count();
        $collected = (int) (clone $baseQuery)->sum('amount');
        $commissionTotal = (int) (clone $baseQuery)->sum('agent_commission');

        $byAgentRows = (clone $baseQuery)
            ->select(
                'agent_id',
                DB::raw('COUNT(*) as payment_count'),
                DB::raw('SUM(amount) as collected_total'),
                DB::raw('SUM(agent_commission) as commission_total'),
            )
            ->groupBy('agent_id')
            ->orderByDesc('commission_total')
            ->get();

        $agentNames = User::query()
            ->whereIn('id', $byAgentRows->pluck('agent_id')->filter()->all())
            ->pluck('name', 'id');

        $byAgent = $byAgentRows
            ->map(function ($row) use ($agentNames) {
                $collected = (int) ($row->collected_total ?? 0);
                $commission = (int) ($row->commission_total ?? 0);

                return [
                    'agent_id' => $row->agent_id,
                    'agent_name' => $agentNames[$row->agent_id] ?? ('Agen #'.$row->agent_id),
                    'payment_count' => (int) $row->payment_count,
                    'collected_total' => $collected,
                    'collected_total_label' => $this->rupiah($collected),
                    'commission_total' => $commission,
                    'commission_total_label' => $this->rupiah($commission),
                ];
            })
            ->values()
            ->all();

        $recent = (clone $baseQuery)
            ->with(['invoice.customer:id,name,username', 'agent:id,name'])
            ->latest('paid_at')
            ->latest('id')
            ->limit(40)
            ->get()
            ->map(fn (Payment $payment) => [
                ...$payment->toAdminArray(),
                'invoice_number' => $payment->invoice?->number,
                'customer_name' => $payment->invoice?->customer?->name,
                'customer_username' => $payment->invoice?->customer?->username,
                'agent_name' => $payment->agent?->name,
            ])
            ->all();

        $agents = $actor->isAgen()
            ? collect([[
                'id' => $actor->id,
                'name' => $actor->name,
            ]])
            : User::query()
                ->where('role', User::ROLE_AGEN)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                ])
                ->values();

        return Inertia::render('Admin/Billing/AgentCommissions/Index', [
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
            'can_filter_agent' => ! $actor->isAgen(),
            'summary' => [
                'payment_count' => $paymentCount,
                'collected_total' => $collected,
                'collected_total_label' => $this->rupiah($collected),
                'commission_total' => $commissionTotal,
                'commission_total_label' => $this->rupiah($commissionTotal),
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
