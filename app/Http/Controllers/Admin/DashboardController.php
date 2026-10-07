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

        $newCustomers = $this->newCustomers($customerQuery);

        return Inertia::render('Admin/Dashboard', [
            'company' => AppSettings::companyName(),
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
            'new_customers' => $newCustomers,
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
     * @param  \Illuminate\Database\Eloquent\Builder<PppoeCustomer>  $customerQuery
     * @return array<string, mixed>
     */
    private function newCustomers($customerQuery): array
    {
        $statusLabels = [
            'active' => 'Aktif',
            'isolated' => 'Isolir',
            'disabled' => 'Nonaktif',
        ];

        $recent = (clone $customerQuery)
            ->with('package')
            ->where('created_at', '>=', now()->copy()->startOfMonth())
            ->where('created_at', '<=', now()->copy()->endOfMonth())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(function (PppoeCustomer $customer) use ($statusLabels) {
                $registered = $customer->created_at?->copy()->timezone(config('app.timezone'));

                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'username' => $customer->username,
                    'package' => $customer->package?->name,
                    'registered_at' => $registered?->toIso8601String(),
                    'registered_label' => $registered
                        ? $registered->format('j').' '.$this->monthName((int) $registered->format('n'), short: true).' '.$registered->format('Y')
                        : null,
                    'status' => $customer->status,
                    'status_label' => $statusLabels[$customer->status] ?? $customer->status,
                ];
            })
            ->values();

        $totalThisMonth = (clone $customerQuery)
            ->where('created_at', '>=', now()->copy()->startOfMonth())
            ->where('created_at', '<=', now()->copy()->endOfMonth())
            ->count();

        $monthLabel = $this->monthName((int) now()->format('n')).' '.now()->format('Y');

        return [
            'month_label' => $monthLabel,
            'total_this_month' => $totalThisMonth,
            'charts' => [
                'daily' => [
                    'key' => 'daily',
                    'title' => 'Harian',
                    'subtitle' => 'Tanggal data pelanggan disimpan di '.$monthLabel,
                    'x_label' => 'Tanggal',
                    'y_label' => 'Pelanggan',
                    'points' => $this->dailyNewCustomers($customerQuery),
                ],
                'monthly' => [
                    'key' => 'monthly',
                    'title' => 'Bulanan',
                    'subtitle' => '6 bulan terakhir, menurut waktu pendaftaran',
                    'x_label' => 'Bulan',
                    'y_label' => 'Pelanggan',
                    'points' => $this->monthlyNewCustomers($customerQuery),
                ],
            ],
            'recent' => $recent,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<PppoeCustomer>  $customerQuery
     * @return array<int, array<string, mixed>>
     */
    private function dailyNewCustomers($customerQuery): array
    {
        $start = now()->copy()->startOfMonth();
        $end = now()->copy()->endOfMonth();
        $dayExpr = $this->dateKeyExpression('created_at', '%Y-%m-%d', 'YYYY-MM-DD', '%Y-%m-%d');

        $rows = (clone $customerQuery)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end)
            ->select(DB::raw("{$dayExpr} as day_key"), DB::raw('COUNT(*) as total'))
            ->groupBy(DB::raw($dayExpr))
            ->pluck('total', 'day_key');

        $series = [];
        for ($i = 0; $i < $start->daysInMonth; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->format('Y-m-d');
            $total = (int) ($rows[$key] ?? 0);
            $series[] = [
                'key' => $key,
                'label' => $day->format('j'),
                'total' => $total,
                'total_label' => $total.' pelanggan',
            ];
        }

        return $series;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<PppoeCustomer>  $customerQuery
     * @return array<int, array<string, mixed>>
     */
    private function monthlyNewCustomers($customerQuery, int $months = 6): array
    {
        $start = now()->copy()->subMonthsNoOverflow($months - 1)->startOfMonth();
        $end = now()->copy()->endOfMonth();
        $monthExpr = $this->dateKeyExpression('created_at', '%Y-%m', 'YYYY-MM', '%Y-%m');

        $rows = (clone $customerQuery)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end)
            ->select(DB::raw("{$monthExpr} as month_key"), DB::raw('COUNT(*) as total'))
            ->groupBy(DB::raw($monthExpr))
            ->pluck('total', 'month_key');

        $series = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonthsNoOverflow($i);
            $key = $month->format('Y-m');
            $total = (int) ($rows[$key] ?? 0);
            $series[] = [
                'key' => $key,
                'label' => $this->monthName((int) $month->format('n'), short: true).' '.$month->format('Y'),
                'total' => $total,
                'total_label' => $total.' pelanggan',
            ];
        }

        return $series;
    }

    private function dateKeyExpression(string $column, string $sqlite, string $pgsql, string $mysql): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "strftime('{$sqlite}', {$column})",
            'pgsql' => "to_char({$column}, '{$pgsql}')",
            default => "DATE_FORMAT({$column}, '{$mysql}')",
        };
    }

    private function monthName(int $month, bool $short = false): string
    {
        $names = [
            1 => ['Januari', 'Jan'],
            2 => ['Februari', 'Feb'],
            3 => ['Maret', 'Mar'],
            4 => ['April', 'Apr'],
            5 => ['Mei', 'Mei'],
            6 => ['Juni', 'Jun'],
            7 => ['Juli', 'Jul'],
            8 => ['Agustus', 'Agu'],
            9 => ['September', 'Sep'],
            10 => ['Oktober', 'Okt'],
            11 => ['November', 'Nov'],
            12 => ['Desember', 'Des'],
        ];

        return $names[$month][$short ? 1 : 0] ?? '';
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
