<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\Payment;
use App\Models\PppoeCustomer;
use App\Models\SubscriptionPackage;
use App\Services\GitUpdateService;
use App\Support\AppSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly GitUpdateService $git)
    {
    }

    public function index(\Illuminate\Http\Request $request): Response
    {
        $user = $request->user();
        $today = now()->toDateString();
        $monthStart = now()->copy()->startOfMonth();

        $customerQuery = PppoeCustomer::query();
        $invoiceQuery = Invoice::query();
        $paymentQuery = Payment::query();

        if ($user->isAgen()) {
            $customerQuery->where('agent_id', $user->id);
            $invoiceQuery->whereHas('customer', fn ($c) => $c->where('agent_id', $user->id));
            $paymentQuery->whereHas('invoice.customer', fn ($c) => $c->where('agent_id', $user->id));
        }

        $customersTotal = (clone $customerQuery)->count();
        $customersActive = (clone $customerQuery)->where('status', 'active')->count();
        $customersIsolated = (clone $customerQuery)->where('status', 'isolated')->count();
        $customersOverdue = (clone $customerQuery)
            ->whereDate('due_date', '<', $today)
            ->where('is_active', true)
            ->count();
        $customersDisabled = (clone $customerQuery)->where('status', 'disabled')->count();
        $syncErrors = (clone $customerQuery)->where('sync_status', 'error')->count();

        $invoicesUnpaid = (clone $invoiceQuery)->where('status', 'unpaid')->count();
        $invoicesOverdue = (clone $invoiceQuery)
            ->where('status', 'unpaid')
            ->whereDate('due_date', '<', $today)
            ->count();
        $collectedThisMonth = (int) (clone $paymentQuery)
            ->where('paid_at', '>=', $monthStart)
            ->sum('amount');
        $paidThisMonth = (clone $invoiceQuery)
            ->where('status', 'paid')
            ->where('paid_at', '>=', $monthStart)
            ->count();

        $routersTotal = MikrotikRouter::query()->count();
        $routersActive = MikrotikRouter::query()->where('is_active', true)->count();
        $packagesActive = SubscriptionPackage::query()->where('is_active', true)->count();

        $dueSoon = (clone $customerQuery)
            ->with('package')
            ->where('is_active', true)
            ->whereDate('due_date', '>=', $today)
            ->whereDate('due_date', '<=', now()->copy()->addDays(7)->toDateString())
            ->orderBy('due_date')
            ->limit(6)
            ->get()
            ->map(fn (PppoeCustomer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'username' => $customer->username,
                'due_date' => $customer->due_date?->format('Y-m-d'),
                'package' => $customer->package?->name,
                'status' => $customer->status,
            ]);

        $attentionInvoices = (clone $invoiceQuery)
            ->with('customer')
            ->where('status', 'unpaid')
            ->whereDate('due_date', '<=', now()->copy()->addDays(7)->toDateString())
            ->orderBy('due_date')
            ->limit(6)
            ->get()
            ->map(fn (Invoice $invoice) => $invoice->toAdminArray());

        $trafficRouters = $user->isAgen()
            ? []
            : MikrotikRouter::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'host'])
                ->map(fn (MikrotikRouter $router) => [
                    'id' => $router->id,
                    'name' => $router->name,
                    'host' => $router->host,
                ])
                ->values();

        return Inertia::render('Admin/Dashboard', [
            'company' => AppSettings::companyName(),
            'traffic_routers' => $trafficRouters,
            'update_notice' => $this->git->dashboardNotice(),
            'stats' => [
                'customers_total' => $customersTotal,
                'customers_active' => $customersActive,
                'customers_isolated' => $customersIsolated,
                'customers_overdue' => $customersOverdue,
                'customers_disabled' => $customersDisabled,
                'sync_errors' => $syncErrors,
                'invoices_unpaid' => $invoicesUnpaid,
                'invoices_overdue' => $invoicesOverdue,
                'paid_this_month' => $paidThisMonth,
                'collected_this_month' => $collectedThisMonth,
                'collected_this_month_label' => 'Rp '.number_format($collectedThisMonth, 0, ',', '.'),
                'routers_total' => $routersTotal,
                'routers_active' => $routersActive,
                'packages_active' => $packagesActive,
            ],
            'revenue_charts' => $this->revenueCharts(),
            'due_soon' => $dueSoon,
            'attention_invoices' => $attentionInvoices,
            'quick_actions' => collect([
                [
                    'label' => 'Tambah Pelanggan',
                    'description' => 'Daftarkan secret PPPoE baru',
                    'href' => '/admin/customers/pppoe/create',
                    'tone' => 'primary',
                ],
                [
                    'label' => 'Tagihan & Bayar',
                    'description' => 'Lihat invoice dan tandai lunas',
                    'href' => '/admin/billing',
                    'tone' => 'default',
                ],
                [
                    'label' => 'Generate Voucher',
                    'description' => 'Buat voucher hotspot',
                    'href' => '/admin/network/hotspot/generate',
                    'tone' => 'default',
                ],
                [
                    'label' => 'Tambah Router',
                    'description' => 'Hubungkan RouterOS baru',
                    'href' => '/admin/network/routeros/create',
                    'tone' => 'default',
                    'superadmin_only' => true,
                ],
                [
                    'label' => 'Paket Layanan',
                    'description' => 'Kelola harga & profile',
                    'href' => '/admin/customers/pppoe/service-profiles',
                    'tone' => 'default',
                ],
                [
                    'label' => 'Profile PPPoE',
                    'description' => 'Atur PPP profile MikroTik',
                    'href' => '/admin/customers/pppoe/mikrotik-profiles',
                    'tone' => 'default',
                ],
            ])
                ->when(
                    ! request()->user()?->isSuperadmin(),
                    fn ($actions) => $actions->reject(fn (array $action) => ($action['superadmin_only'] ?? false)),
                )
                ->map(fn (array $action) => collect($action)->except('superadmin_only')->all())
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array{
     *     daily: array<string, mixed>,
     *     monthly: array<string, mixed>,
     *     half_year: array<string, mixed>
     * }
     */
    private function revenueCharts(): array
    {
        return [
            'daily' => [
                'key' => 'daily',
                'title' => 'Harian',
                'subtitle' => '14 hari terakhir',
                'x_label' => 'Tanggal',
                'y_label' => 'Pendapatan (Rp)',
                'points' => $this->dailyRevenue(14),
            ],
            'monthly' => [
                'key' => 'monthly',
                'title' => 'Bulanan',
                'subtitle' => '6 bulan terakhir',
                'x_label' => 'Bulan',
                'y_label' => 'Pendapatan (Rp)',
                'points' => $this->monthlyRevenue(6),
            ],
            'half_year' => [
                'key' => 'half_year',
                'title' => 'Per 6 bulan',
                'subtitle' => '4 periode semester',
                'x_label' => 'Periode',
                'y_label' => 'Pendapatan (Rp)',
                'points' => $this->halfYearRevenue(4),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dailyRevenue(int $days): array
    {
        $start = now()->copy()->subDays($days - 1)->startOfDay();
        $end = now()->copy()->endOfDay();

        $driver = DB::connection()->getDriverName();
        $dayExpr = match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d', paid_at)",
            'pgsql' => "to_char(paid_at, 'YYYY-MM-DD')",
            default => 'DATE(paid_at)',
        };

        $rows = Payment::query()
            ->select(DB::raw("{$dayExpr} as day_key"), DB::raw('SUM(amount) as total'))
            ->whereBetween('paid_at', [$start, $end])
            ->groupBy(DB::raw($dayExpr))
            ->pluck('total', 'day_key');

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->format('Y-m-d');
            $total = (int) ($rows[$key] ?? 0);
            $series[] = [
                'key' => $key,
                'label' => $day->copy()->locale('id')->translatedFormat('d M'),
                'total' => $total,
                'total_label' => $this->rupiah($total),
            ];
        }

        return $series;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function monthlyRevenue(int $months): array
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

        $series = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonthsNoOverflow($i);
            $key = $month->format('Y-m');
            $total = (int) ($rows[$key] ?? 0);
            $series[] = [
                'key' => $key,
                'label' => $month->copy()->locale('id')->translatedFormat('M Y'),
                'total' => $total,
                'total_label' => $this->rupiah($total),
            ];
        }

        return $series;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function halfYearRevenue(int $periods): array
    {
        // Periode semester: Jan–Jun / Jul–Des. Ambil $periods semester terakhir termasuk semester berjalan.
        $current = now()->copy()->startOfMonth();
        $semesterStartMonth = $current->month <= 6 ? 1 : 7;
        $latestStart = $current->copy()->month($semesterStartMonth)->startOfMonth();

        $starts = [];
        for ($i = $periods - 1; $i >= 0; $i--) {
            $starts[] = $latestStart->copy()->subMonthsNoOverflow($i * 6);
        }

        $rangeStart = $starts[0]->copy();
        $rangeEnd = $latestStart->copy()->addMonthsNoOverflow(5)->endOfMonth();

        $payments = Payment::query()
            ->whereBetween('paid_at', [$rangeStart, $rangeEnd])
            ->get(['amount', 'paid_at']);

        $series = [];
        foreach ($starts as $start) {
            $end = $start->copy()->addMonthsNoOverflow(5)->endOfMonth();
            $total = (int) $payments
                ->filter(function ($payment) use ($start, $end) {
                    $paidAt = Carbon::parse($payment->paid_at);

                    return $paidAt->betweenIncluded($start, $end);
                })
                ->sum('amount');

            $endMonth = $start->copy()->addMonthsNoOverflow(5);
            $label = $start->copy()->locale('id')->translatedFormat('M')
                .'–'.$endMonth->copy()->locale('id')->translatedFormat('M Y');

            $series[] = [
                'key' => $start->format('Y-m'),
                'label' => $label,
                'total' => $total,
                'total_label' => $this->rupiah($total),
            ];
        }

        return $series;
    }

    private function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
