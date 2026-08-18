<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\AdminListState;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FinancialReportController extends Controller
{
    public function index(Request $request): Response
    {
        AdminListState::apply($request, AdminListState::BILLING_REPORTS, [
            'preset', 'from', 'to',
        ]);

        $preset = (string) $request->get('preset', 'this_month');
        [$from, $to, $preset] = $this->resolvePeriod(
            $preset,
            $request->get('from'),
            $request->get('to'),
        );

        $fromStart = $from->copy()->startOfDay();
        $toEnd = $to->copy()->endOfDay();
        $today = now()->toDateString();

        $paymentsInPeriod = Payment::query()
            ->whereBetween('paid_at', [$fromStart, $toEnd]);

        $collected = (int) (clone $paymentsInPeriod)->sum('amount');
        $transactions = (clone $paymentsInPeriod)->count();

        $unpaidQuery = Invoice::query()->where('status', 'unpaid');
        $unpaidTotal = (int) (clone $unpaidQuery)->sum('total');
        $unpaidCount = (clone $unpaidQuery)->count();

        $overdueQuery = Invoice::query()
            ->where('status', 'unpaid')
            ->whereDate('due_date', '<', $today);
        $overdueTotal = (int) (clone $overdueQuery)->sum('total');
        $overdueCount = (clone $overdueQuery)->count();

        $methodRows = Payment::query()
            ->select('method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->whereBetween('paid_at', [$fromStart, $toEnd])
            ->groupBy('method')
            ->get()
            ->keyBy('method');

        $methods = ['cash', 'transfer', 'qris', 'other'];
        $byMethod = collect($methods)->map(function (string $method) use ($methodRows, $collected) {
            $row = $methodRows->get($method);
            $total = (int) ($row->total ?? 0);
            $count = (int) ($row->count ?? 0);

            return [
                'method' => $method,
                'label' => $this->methodLabel($method),
                'count' => $count,
                'total' => $total,
                'total_label' => $this->rupiah($total),
                'percent' => $collected > 0 ? round(($total / $collected) * 100, 1) : 0,
            ];
        })->values()->all();

        $monthly = $this->monthlyTrend(12);

        $recentPayments = Payment::query()
            ->with(['invoice.customer', 'receiver'])
            ->whereBetween('paid_at', [$fromStart, $toEnd])
            ->latest('paid_at')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'invoice_number' => $payment->invoice?->number,
                'customer_name' => $payment->invoice?->customer?->name,
                'amount' => $payment->amount,
                'amount_label' => $this->rupiah($payment->amount),
                'method' => $payment->method,
                'method_label' => $this->methodLabel($payment->method),
                'paid_at' => $payment->paid_at?->format('Y-m-d H:i'),
                'receiver_name' => $payment->receiver?->name,
            ])
            ->values()
            ->all();

        $topUnpaid = Invoice::query()
            ->with('customer')
            ->where('status', 'unpaid')
            ->orderBy('due_date')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(function (Invoice $invoice) {
                $array = $invoice->toAdminArray();

                return [
                    'id' => $array['id'],
                    'number' => $array['number'],
                    'customer_name' => $array['customer']['name'] ?? null,
                    'due_date' => $array['due_date'],
                    'total' => $array['total'],
                    'total_label' => $array['total_label'],
                    'is_overdue' => $array['is_overdue'] ?? false,
                    'package_name' => $array['package_name'],
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Admin/Billing/Report', [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'preset' => $preset,
            ],
            'presets' => [
                ['value' => 'this_month', 'label' => 'Bulan ini'],
                ['value' => 'last_month', 'label' => 'Bulan lalu'],
                ['value' => 'last_3_months', 'label' => '3 bulan'],
                ['value' => 'this_year', 'label' => 'Tahun ini'],
                ['value' => 'custom', 'label' => 'Kustom'],
            ],
            'summary' => [
                'collected' => $collected,
                'collected_label' => $this->rupiah($collected),
                'transactions' => $transactions,
                'unpaid_total' => $unpaidTotal,
                'unpaid_total_label' => $this->rupiah($unpaidTotal),
                'unpaid_count' => $unpaidCount,
                'overdue_total' => $overdueTotal,
                'overdue_total_label' => $this->rupiah($overdueTotal),
                'overdue_count' => $overdueCount,
            ],
            'by_method' => $byMethod,
            'monthly' => $monthly,
            'recent_payments' => $recentPayments,
            'top_unpaid' => $topUnpaid,
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function resolvePeriod(string $preset, mixed $fromInput, mixed $toInput): array
    {
        $now = now();

        return match ($preset) {
            'last_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
                'last_month',
            ],
            'last_3_months' => [
                $now->copy()->subMonthsNoOverflow(2)->startOfMonth(),
                $now->copy()->endOfMonth(),
                'last_3_months',
            ],
            'this_year' => [
                $now->copy()->startOfYear(),
                $now->copy()->endOfYear(),
                'this_year',
            ],
            'custom' => (function () use ($fromInput, $toInput, $now) {
                $from = $this->parseDate($fromInput)?->startOfDay() ?? $now->copy()->startOfMonth();
                $to = $this->parseDate($toInput)?->endOfDay() ?? $now->copy()->endOfMonth();
                if ($from->greaterThan($to)) {
                    [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
                }

                return [$from, $to, 'custom'];
            })(),
            default => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
                'this_month',
            ],
        };
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function monthlyTrend(int $months): array
    {
        $start = now()->copy()->subMonthsNoOverflow($months - 1)->startOfMonth();
        $end = now()->copy()->endOfMonth();

        $driver = DB::connection()->getDriverName();
        $monthExpr = match ($driver) {
            'sqlite' => "strftime('%Y-%m', paid_at)",
            'pgsql' => "to_char(paid_at, 'YYYY-MM')",
            default => "DATE_FORMAT(paid_at, '%Y-%m')",
        };

        $rows = Payment::query()
            ->select(DB::raw("{$monthExpr} as month_key"), DB::raw('SUM(amount) as total'))
            ->whereBetween('paid_at', [$start, $end])
            ->groupBy(DB::raw($monthExpr))
            ->pluck('total', 'month_key');

        $max = max(1, (int) $rows->max());
        $series = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonthsNoOverflow($i);
            $key = $month->format('Y-m');
            $total = (int) ($rows[$key] ?? 0);

            $series[] = [
                'month' => $key,
                'label' => $month->copy()->locale('id')->translatedFormat('M Y'),
                'total' => $total,
                'total_label' => $this->rupiah($total),
                'percent' => round(($total / $max) * 100, 1),
            ];
        }

        return $series;
    }

    private function methodLabel(string $method): string
    {
        return match ($method) {
            'cash' => 'Tunai',
            'transfer' => 'Transfer',
            'qris' => 'QRIS',
            'other' => 'Lainnya',
            default => $method,
        };
    }

    private function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
