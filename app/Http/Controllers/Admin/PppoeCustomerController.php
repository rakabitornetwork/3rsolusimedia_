<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SiteSetting;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Models\VpnPortForward;
use App\Models\VpnRouter;
use App\Models\VpnRouterCredit;
use App\Services\BillingCycleService;
use App\Services\BillingService;
use App\Services\Messaging\CustomerNotifier;
use App\Services\Messaging\WhatsAppIdentityBinder;
use App\Services\MikrotikApiService;
use App\Services\PppoeMonthlyUsageService;
use App\Services\PppoeSyncService;
use App\Services\Vpn\L2tpClientScript;
use App\Services\Vpn\VpnAccessPlan;
use App\Services\Vpn\VpnChrSettings;
use App\Services\Vpn\VpnProvisioner;
use App\Services\Vpn\VpnRouterAccounts;
use App\Services\Vpn\VpnServerScript;
use App\Support\AdminListState;
use App\Support\AppSettings;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class PppoeCustomerController extends Controller
{
    public function __construct(
        private readonly MikrotikApiService $api,
        private readonly BillingCycleService $billing,
        private readonly BillingService $billingService,
        private readonly PppoeSyncService $sync,
        private readonly PppoeMonthlyUsageService $usage,
        private readonly CustomerNotifier $notifier,
        private readonly WhatsAppIdentityBinder $whatsappBinder,
        private readonly VpnAccessPlan $vpnPlan,
        private readonly VpnProvisioner $vpn,
        private readonly VpnRouterAccounts $vpnAccounts,
        private readonly VpnServerScript $vpnServerScript,
        private readonly L2tpClientScript $vpnClientScript,
    ) {
    }

    public function index(Request $request): Response
    {
        AdminListState::apply($request, AdminListState::PPPOE, [
            'q', 'status', 'router_id', 'service', 'sort', 'direction', 'page',
        ]);

        $user = $request->user();
        $sort = (string) $request->get('sort', 'name');
        $direction = strtolower((string) $request->get('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $allowedSorts = [
            'name' => 'pppoe_customers.name',
            'username' => 'pppoe_customers.username',
            'package' => 'subscription_packages.name',
            'due_date' => 'pppoe_customers.due_date',
            'overdue_action' => 'pppoe_customers.overdue_action',
            'status' => 'pppoe_customers.status',
            'usage' => 'usage',
        ];

        if (! array_key_exists($sort, $allowedSorts)) {
            $sort = 'name';
        }

        $query = PppoeCustomer::query()
            ->with(['router', 'package', 'agent', 'usageThisMonth', 'trafficCursor'])
            ->select('pppoe_customers.*');

        if ($user->isAgen()) {
            $query->where('pppoe_customers.agent_id', $user->id);
        }

        if ($sort === 'package') {
            $query->leftJoin(
                'subscription_packages',
                'subscription_packages.id',
                '=',
                'pppoe_customers.subscription_package_id'
            );
        }

        if ($sort === 'usage') {
            $query->leftJoin('pppoe_monthly_usages as monthly_usage_sort', function ($join) {
                $join->on('monthly_usage_sort.pppoe_customer_id', '=', 'pppoe_customers.id')
                    ->where('monthly_usage_sort.period', '=', now()->format('Y-m'));
            });
        }

        if ($status = $request->get('status')) {
            if ($status === 'grace') {
                $query->whereNotNull('pppoe_customers.grace_until')
                    ->whereDate('pppoe_customers.grace_until', '>=', now()->toDateString());
            } elseif ($status === 'active') {
                $query->where('pppoe_customers.status', 'active')
                    ->where(function ($builder) {
                        $builder->whereNull('pppoe_customers.grace_until')
                            ->orWhereDate('pppoe_customers.grace_until', '<', now()->toDateString());
                    });
            } else {
                $query->where('pppoe_customers.status', $status);
            }
        }

        if ($routerId = $request->get('router_id')) {
            $query->where('pppoe_customers.mikrotik_router_id', $routerId);
        }

        $service = PppoeCustomer::normalizePppService((string) $request->get('service', ''));
        if ($request->filled('service')) {
            $query->where('pppoe_customers.ppp_service', $service);
        }

        if ($sort === 'usage') {
            $query->orderByRaw(
                '(COALESCE(monthly_usage_sort.rx_bytes, 0) + COALESCE(monthly_usage_sort.tx_bytes, 0)) '.$direction
            );
        } else {
            $query->orderBy($allowedSorts[$sort], $direction);
        }

        $query->orderBy('pppoe_customers.id', $direction);

        $customers = $query->get()->map(function (PppoeCustomer $customer) {
            $payload = $customer->toSafeArray();
            $payload['monthly_usage'] = $this->usage->present(
                $customer->usageThisMonth,
                $customer->trafficCursor,
            );

            return $payload;
        })->values();

        return Inertia::render('Admin/Customers/Pppoe/Index', [
            'customers' => $customers,
            'filters' => [
                'q' => $request->get('q', ''),
                'status' => $request->get('status', ''),
                'router_id' => $request->get('router_id', ''),
                'service' => $request->filled('service') ? $service : '',
                'sort' => $sort,
                'direction' => $direction,
            ],
            'routers' => MikrotikRouter::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'host']),
            'stats' => $this->customerStats($request->get('router_id'), $user),
            'is_agen' => $user->isAgen(),
        ]);
    }

    public function print(Request $request): HttpResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'date_field' => ['nullable', Rule::in(['due_date', 'start_date', 'billing_day'])],
            'router_id' => ['nullable', 'integer', 'exists:mikrotik_routers,id'],
            'status' => ['nullable', Rule::in(['active', 'isolated', 'grace', 'disabled'])],
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();
        $dateField = $validated['date_field'] ?? 'due_date';
        $user = $request->user();

        $query = PppoeCustomer::query()
            ->with([
                'router',
                'package',
                'invoices' => function ($builder) use ($date, $dateField) {
                    $builder->whereIn('status', ['unpaid', 'paid'])
                        ->orderByRaw("CASE WHEN status = 'unpaid' THEN 0 ELSE 1 END")
                        ->orderByDesc('due_date')
                        ->orderByDesc('id');

                    if ($dateField === 'due_date') {
                        $builder->whereDate('due_date', $date->toDateString());
                    }
                },
            ])
            ->select('pppoe_customers.*');

        if ($user->isAgen()) {
            $query->where('pppoe_customers.agent_id', $user->id);
        }

        if ($dateField === 'billing_day') {
            $query->where('pppoe_customers.billing_day', $date->day);
        } elseif ($dateField === 'start_date') {
            $query->whereDate('pppoe_customers.start_date', $date->toDateString());
        } else {
            $query->whereDate('pppoe_customers.due_date', $date->toDateString());
        }

        if (! empty($validated['router_id'])) {
            $query->where('pppoe_customers.mikrotik_router_id', $validated['router_id']);
        }

        if (! empty($validated['status'])) {
            $status = $validated['status'];
            if ($status === 'grace') {
                $query->whereNotNull('pppoe_customers.grace_until')
                    ->whereDate('pppoe_customers.grace_until', '>=', now()->toDateString());
            } elseif ($status === 'active') {
                $query->where('pppoe_customers.status', 'active')
                    ->where(function ($builder) {
                        $builder->whereNull('pppoe_customers.grace_until')
                            ->orWhereDate('pppoe_customers.grace_until', '<', now()->toDateString());
                    });
            } else {
                $query->where('pppoe_customers.status', $status);
            }
        }

        $customers = $query
            ->orderBy('pppoe_customers.name', 'asc')
            ->orderBy('pppoe_customers.id', 'asc')
            ->get()
            ->map(function (PppoeCustomer $customer) {
                $invoice = $customer->invoices->first();
                $amount = $invoice?->total
                    ?? $customer->first_bill_amount
                    ?? $customer->package?->price;

                return [
                    'customer' => $customer,
                    'amount' => $amount,
                    'due_date' => $invoice?->due_date ?? $customer->due_date,
                ];
            });

        $router = ! empty($validated['router_id'])
            ? MikrotikRouter::query()->find($validated['router_id'])
            : null;

        $dateFieldLabels = [
            'due_date' => 'Jatuh tempo',
            'start_date' => 'Tanggal mulai',
            'billing_day' => 'Hari tagihan (tiap tanggal)',
        ];

        $totalAmount = $customers->sum(fn (array $row) => (int) ($row['amount'] ?? 0));

        return response()->view('admin.customers.pppoe-print', [
            'rows' => $customers,
            'total_amount' => $totalAmount,
            'date' => $date,
            'date_field' => $dateField,
            'date_field_label' => $dateFieldLabels[$dateField] ?? $dateField,
            'router' => $router,
            'status' => $validated['status'] ?? '',
            'company' => [
                'name' => AppSettings::companyName(),
                'logo' => AppSettings::branding()['logo_mark'] ?? AppSettings::branding()['logo_full'],
                'address' => trim((string) SiteSetting::getValue('address', '')),
                'phone' => trim((string) SiteSetting::getValue('phone', '')),
                'whatsapp' => trim((string) SiteSetting::getValue('whatsapp', '')),
                'tagline' => trim((string) SiteSetting::getValue('tagline', '')),
            ],
        ]);
    }

    private function customerStats(mixed $routerId, ?\App\Models\User $user = null): array
    {
        $statsQuery = PppoeCustomer::query();

        if ($user?->isAgen()) {
            $statsQuery->where('agent_id', $user->id);
        }

        if ($routerId) {
            $statsQuery->where('mikrotik_router_id', $routerId);
        }

        return [
            'total' => (clone $statsQuery)->count(),
            'active' => (clone $statsQuery)
                ->where('status', 'active')
                ->where(function ($builder) {
                    $builder->whereNull('grace_until')
                        ->orWhereDate('grace_until', '<', now()->toDateString());
                })
                ->count(),
            'isolated' => (clone $statsQuery)->where('status', 'isolated')->count(),
            'grace' => (clone $statsQuery)
                ->whereNotNull('grace_until')
                ->whereDate('grace_until', '>=', now()->toDateString())
                ->count(),
            'overdue' => (clone $statsQuery)->whereDate('due_date', '<', now()->toDateString())->count(),
            'new_this_month' => (clone $statsQuery)
                ->where('created_at', '>=', now()->copy()->startOfMonth())
                ->where('created_at', '<=', now()->copy()->endOfMonth())
                ->count(),
            'new_month_label' => $this->currentMonthLabel(),
        ];
    }

    private function currentMonthLabel(): string
    {
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        return $months[(int) now()->format('n')].' '.now()->format('Y');
    }

    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->isAgen()) {
            return AdminListState::to('admin.customers.pppoe', AdminListState::PPPOE)
                ->with('error', 'Akun Agen tidak memiliki akses untuk membuat pelanggan baru.');
        }

        $routerId = $request->filled('router_id')
            ? $request->integer('router_id')
            : AdminListState::lastRouterId($request);
        $username = trim((string) $request->get('username', ''));
        $requestedService = $request->filled('service')
            ? PppoeCustomer::normalizePppService((string) $request->get('service'))
            : null;

        if ($routerId && $username !== '') {
            $existing = PppoeCustomer::query()
                ->where('mikrotik_router_id', $routerId)
                ->whereRaw('LOWER(username) = ?', [strtolower($username)])
                ->first();

            if ($existing) {
                return redirect()
                    ->route('admin.customers.pppoe.edit', $existing)
                    ->with('success', 'Username sudah terdaftar. Membuka form edit.');
            }
        }

        $prefill = $this->buildSessionPrefill($routerId, $username, $requestedService);

        return Inertia::render('Admin/Customers/Pppoe/Form', [
            'customer' => null,
            'prefill' => $prefill,
            ...$this->formOptions($routerId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->user()?->isAgen()) {
            return AdminListState::to('admin.customers.pppoe', AdminListState::PPPOE)
                ->with('error', 'Akun Agen tidak memiliki akses untuk membuat pelanggan baru.');
        }

        $validated = $this->validateCustomer($request);
        $package = isset($validated['subscription_package_id'])
            ? SubscriptionPackage::query()->find($validated['subscription_package_id'])
            : null;

        if ($package && empty($validated['service_profile'])) {
            $validated['service_profile'] = $package->mikrotik_profile;
        }

        $validated = $this->applyBillingCycle($validated, $package);

        $isActive = $request->boolean('is_active');

        $customer = PppoeCustomer::query()->create([
            ...$validated,
            'status' => $isActive ? 'active' : 'disabled',
            'sync_status' => 'pending',
            'is_active' => $isActive,
        ]);

        $this->whatsappBinder->bindCustomer($customer);

        $invoice = $this->billingService->createProrataInvoice($customer->fresh('package'));
        $this->sync->sync($customer->fresh(['router', 'package']), pushPassword: true);
        $this->notifier->notifyWelcome($customer->fresh('package'), $invoice);

        $message = 'Pelanggan '.$customer->pppServiceLabel().' berhasil ditambahkan. Tagihan pertama (prorata): Rp '.
            number_format((int) $customer->first_bill_amount, 0, ',', '.').'.';

        if ($invoice) {
            $message .= ' Invoice: '.$invoice->number.'.';
        }

        if ($customer->pppService() === PppoeCustomer::SERVICE_L2TP) {
            $this->vpnPlan->ensure($customer);

            return redirect()
                ->route('admin.customers.pppoe.edit', $customer)
                ->with('success', $message.' Isi nama router di bawah. Ketiga router memakai nama sendiri, dan router pertama mengikuti tagihan akun.');
        }

        return AdminListState::to('admin.customers.pppoe', AdminListState::PPPOE)
            ->with('success', $message);
    }

    public function edit(Request $request, PppoeCustomer $pppoe): Response|RedirectResponse
    {
        if ($request->user()?->isAgen()) {
            return AdminListState::to('admin.customers.pppoe', AdminListState::PPPOE)
                ->with('error', 'Akun Agen tidak memiliki akses untuk mengedit pelanggan.');
        }

        $pppoe->load(['router', 'package', 'usageThisMonth', 'trafficCursor']);
        $customer = $pppoe->toSafeArray();
        $customer['monthly_usage'] = $this->usage->present(
            $pppoe->usageThisMonth,
            $pppoe->trafficCursor,
        );
        $customer['package_change_defers_to_next_month'] = $this->packageChangeDefersToNextMonth($pppoe);
        $lastPaidDue = Invoice::query()
            ->where('pppoe_customer_id', $pppoe->id)
            ->where('status', 'paid')
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->value('due_date');
        $customer['has_paid_invoice'] = $lastPaidDue !== null;
        $customer['last_paid_due_date'] = $lastPaidDue
            ? Carbon::parse($lastPaidDue)->toDateString()
            : null;

        return Inertia::render('Admin/Customers/Pppoe/Form', [
            'customer' => $customer,
            'vpn_access' => $this->vpnAccessPayload($pppoe),
            ...$this->formOptions($pppoe->mikrotik_router_id, $pppoe->subscription_package_id),
        ]);
    }

    public function update(Request $request, PppoeCustomer $pppoe): RedirectResponse
    {
        if ($request->user()?->isAgen()) {
            return AdminListState::to('admin.customers.pppoe', AdminListState::PPPOE)
                ->with('error', 'Akun Agen tidak memiliki akses untuk mengedit pelanggan.');
        }

        $validated = $this->validateCustomer($request, $pppoe);
        $pppoe->loadMissing('package');
        $package = isset($validated['subscription_package_id'])
            ? SubscriptionPackage::query()->find($validated['subscription_package_id'])
            : null;

        $packageChanged = (int) ($validated['subscription_package_id'] ?? 0) !== (int) $pppoe->subscription_package_id;
        if ($package && ($packageChanged || empty($validated['service_profile']))) {
            $validated['service_profile'] = $package->mikrotik_profile;
        }

        $isActive = $request->boolean('is_active');
        $wasActive = (bool) $pppoe->is_active;
        $stopping = $wasActive && ! $isActive;
        $reactivating = ! $wasActive && $isActive;
        $oldPrice = (int) ($pppoe->package?->price ?? 0);
        $oldProfile = (string) ($pppoe->service_profile ?? '');
        $oldService = $pppoe->pppService();
        $requestedDue = $validated['due_date'] ?? null;
        $dueChanged = Carbon::parse($validated['due_date'])->toDateString() !== $pppoe->due_date?->toDateString();
        $hasPaid = $this->billingService->hasPaidInvoice($pppoe);
        $billingUnchanged = $this->billingInputsUnchanged($validated, $pppoe);
        $deferPackageToNextMonth = $this->shouldDeferPackageChangeToNextMonth($validated, $pppoe);
        $midCyclePackageChange = $packageChanged
            && ! $deferPackageToNextMonth
            && $hasPaid
            && $pppoe->due_date
            && $pppoe->due_date->copy()->startOfDay()->greaterThan(now()->startOfDay());

        $stopDate = Carbon::parse($validated['stop_date'] ?? now()->toDateString())->startOfDay();
        $reactivateDate = Carbon::parse($validated['reactivate_date'] ?? now()->toDateString())->startOfDay();
        $changeDate = Carbon::parse($validated['service_change_date'] ?? now()->toDateString())->startOfDay();

        if (
            $stopping
            && $pppoe->due_date
            && $stopDate->greaterThan($pppoe->due_date->copy()->startOfDay())
            && $pppoe->due_date->copy()->startOfDay()->greaterThanOrEqualTo(now()->startOfDay())
        ) {
            return back()->withInput()->with('error', 'Tanggal berhenti harus pada atau sebelum jatuh tempo.');
        }

        if ($reactivating && Carbon::parse($validated['due_date'])->startOfDay()->lessThanOrEqualTo($reactivateDate)) {
            return back()->withInput()->with('error', 'Tanggal jatuh tempo harus setelah tanggal aktif kembali.');
        }

        if ($stopping) {
            $validated = $this->preserveHistoricalBilling($validated, $pppoe);
            $validated['stopped_at'] = $stopDate->toDateString();
        } elseif ($reactivating) {
            $validated = $this->preserveHistoricalBilling($validated, $pppoe, keepDue: false);
            $validated['stopped_at'] = null;
            $validated['reactivated_at'] = $reactivateDate->toDateString();
        } elseif ($billingUnchanged || $deferPackageToNextMonth) {
            $validated = $this->preserveHistoricalBilling($validated, $pppoe);
            if ($deferPackageToNextMonth) {
                $validated['due_date'] = $this->nextMonthDueAfterPackageChange(
                    $pppoe,
                    is_string($requestedDue) ? $requestedDue : null,
                );
                $validated['billing_day'] = $this->billing->normalizeBillingDay(
                    (int) Carbon::parse($validated['due_date'])->day
                );
            }
        } elseif ($hasPaid && ($dueChanged || $midCyclePackageChange)) {
            $validated = $this->preserveHistoricalBilling($validated, $pppoe, keepDue: $dueChanged === false);
        } else {
            $validated = $this->applyBillingCycle($validated, $package, $pppoe);
        }

        $payload = [
            ...$this->customerColumns($validated),
            'is_active' => $isActive,
            'status' => $isActive ? $pppoe->status : 'disabled',
        ];

        if ($isActive && $pppoe->status === 'disabled') {
            $payload['status'] = 'active';
        }

        $passwordChanged = ! empty($validated['password']);

        if (! $passwordChanged) {
            unset($payload['password']);
        }

        try {
            $result = DB::transaction(function () use (
                $pppoe,
                $payload,
                $billingUnchanged,
                $deferPackageToNextMonth,
                $stopping,
                $reactivating,
                $stopDate,
                $reactivateDate,
                $midCyclePackageChange,
                $dueChanged,
                $hasPaid,
                $packageChanged,
                $oldPrice,
                $changeDate,
            ) {
                $pppoe->update($payload);
                $fresh = $pppoe->fresh(['router', 'package']);
                $this->whatsappBinder->bindCustomer($fresh);

                if ($stopping) {
                    return ['kind' => 'stop', ...$this->billingService->settleStoppedService($fresh, $stopDate)];
                }

                if ($reactivating) {
                    return [
                        'kind' => 'reactivate',
                        'invoice' => $this->billingService->createReactivationInvoice($fresh, $reactivateDate),
                    ];
                }

                if ($deferPackageToNextMonth) {
                    return [
                        'kind' => 'defer',
                        'invoice' => $this->billingService->reissueNextMonthInvoiceForPackageChange($fresh),
                    ];
                }

                if ($midCyclePackageChange && ! $dueChanged) {
                    return [
                        'kind' => 'mid',
                        ...$this->billingService->applyMidCyclePackageChange($fresh, $oldPrice, $changeDate),
                    ];
                }

                if ($hasPaid && $dueChanged) {
                    return [
                        'kind' => 'due',
                        ...$this->billingService->reissueInvoiceForBillingDateChange(
                            $fresh,
                            $packageChanged ? $oldPrice : null,
                            $packageChanged ? $changeDate : null,
                        ),
                    ];
                }

                if (! $billingUnchanged) {
                    $this->billingService->ensureOpenInvoice($fresh);
                }

                return ['kind' => 'none'];
            });
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        $fresh = $pppoe->fresh(['router', 'package']);
        $profileChanged = (string) ($fresh->service_profile ?? '') !== $oldProfile
            && (string) ($fresh->service_profile ?? '') !== '';
        $serviceChanged = $fresh->pppService() !== $oldService;
        $this->sync->sync(
            $fresh,
            pushPassword: $passwordChanged,
            forceDisconnect: $profileChanged || $packageChanged || $reactivating || $serviceChanged,
        );

        $vpnNote = '';
        if ($oldService === PppoeCustomer::SERVICE_L2TP && $fresh->pppService() !== PppoeCustomer::SERVICE_L2TP) {
            $removed = $this->vpn->removeCustomer($fresh);
            if ($removed['ok']) {
                $fresh->vpnRouters()->delete();
                $fresh->vpnPortForwards()->delete();
                VpnRouterCredit::query()->where('pppoe_customer_id', $fresh->id)->delete();
            } else {
                $vpnNote = ' Aturan CHR belum terhapus: '.$removed['message'];
            }
        } elseif ($fresh->pppService() === PppoeCustomer::SERVICE_L2TP) {
            $this->vpnPlan->ensure($fresh);
        }

        return AdminListState::to('admin.customers.pppoe', AdminListState::PPPOE)
            ->with('success', $this->customerUpdateMessage($result, $profileChanged || $packageChanged).$vpnNote);
    }

    public function destroy(Request $request, PppoeCustomer $pppoe): RedirectResponse
    {
        if ($request->user()?->isAgen()) {
            return AdminListState::to('admin.customers.pppoe', AdminListState::PPPOE)
                ->with('error', 'Akun Agen tidak memiliki akses untuk menghapus pelanggan.');
        }

        $removeSecret = $request->boolean('remove_secret');
        $secretNote = '';

        if ($removeSecret && $pppoe->router) {
            $result = $this->api->removePppSecret($pppoe->router, $pppoe->username);
            $secretNote = ($result['ok'] ?? false)
                ? ' Secret RouterOS juga dihapus.'
                : ' Data app terhapus, tetapi secret RouterOS gagal dihapus: '.($result['message'] ?? 'unknown');
        }

        if ($removeSecret && $pppoe->pppService() === PppoeCustomer::SERVICE_L2TP) {
            $removed = $this->vpn->removeCustomer($pppoe);
            $secretNote .= ($removed['ok'] ?? false)
                ? ' Aturan CHR ikut dihapus.'
                : ' Aturan CHR gagal dihapus: '.($removed['message'] ?? 'unknown');
        }

        $pppoe->delete();

        return AdminListState::to('admin.customers.pppoe', AdminListState::PPPOE)
            ->with(
                'success',
                'Pelanggan PPPoE berhasil dihapus.'.($removeSecret
                    ? $secretNote
                    : ' Secret di RouterOS dibiarkan.')
            );
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        if ($request->user()?->isAgen()) {
            return AdminListState::to('admin.customers.pppoe', AdminListState::PPPOE)
                ->with('error', 'Akun Agen tidak memiliki akses untuk menghapus pelanggan.');
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:pppoe_customers,id'],
            'remove_secret' => ['sometimes', 'boolean'],
        ]);

        $removeSecret = $request->boolean('remove_secret');
        $customers = PppoeCustomer::query()
            ->with('router')
            ->whereIn('id', $validated['ids'])
            ->get();

        $deleted = 0;
        $secretRemoved = 0;
        $secretFailed = 0;

        foreach ($customers as $customer) {
            if ($removeSecret && $customer->router) {
                $result = $this->api->removePppSecret($customer->router, $customer->username);
                if ($result['ok'] ?? false) {
                    $secretRemoved++;
                } else {
                    $secretFailed++;
                }
            }

            $customer->delete();
            $deleted++;
        }

        $message = "{$deleted} pelanggan PPPoE dihapus dari aplikasi.";
        if ($removeSecret) {
            $message .= " Secret RouterOS: {$secretRemoved} berhasil dihapus";
            if ($secretFailed > 0) {
                $message .= ", {$secretFailed} gagal";
            }
            $message .= '.';
        } else {
            $message .= ' Secret di RouterOS dibiarkan.';
        }

        return AdminListState::to('admin.customers.pppoe', AdminListState::PPPOE)
            ->with($secretFailed > 0 ? 'error' : 'success', $message);
    }

    public function sync(PppoeCustomer $pppoe): RedirectResponse
    {
        $this->sync->sync($pppoe->load(['router', 'package']));

        return back()->with(
            $pppoe->fresh()->sync_status === 'synced' ? 'success' : 'error',
            $pppoe->fresh()->sync_message ?: 'Sinkronisasi selesai.'
        );
    }

    public function pushVpn(Request $request, PppoeCustomer $pppoe): RedirectResponse
    {
        if ($request->user()?->isAgen()) {
            return back()->with('error', 'Akun Agen tidak memiliki akses untuk mengisi CHR.');
        }

        $result = $this->vpn->push($pppoe);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function storeVpnRouter(Request $request, PppoeCustomer $pppoe): RedirectResponse
    {
        if ($request->user()?->isAgen()) {
            return back()->with('error', 'Akun Agen tidak memiliki akses untuk menambah router.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:32'],
        ]);

        try {
            $enrolled = $this->vpnAccounts->enroll($pppoe, $validated['name']);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $invoice = $enrolled['invoice'];
        if ($invoice) {
            try {
                $this->notifier->notifyInvoice($invoice->loadMissing('customer'));
            } catch (\Throwable) {
                // Router dan tagihan tetap tersimpan meski WhatsApp gagal.
            }

            return back()->with(
                'success',
                'Router '.$validated['name'].' dibuat. Tagihan '.$invoice->number.' muncul hari ini. Secret baru aktif setelah lunas.',
            );
        }

        return back()->with(
            'success',
            'Router '.$validated['name'].' dibuat tanpa tagihan baru. Tagihan sebelumnya tetap berlaku.',
        );
    }

    public function destroyVpnRouter(Request $request, PppoeCustomer $pppoe, VpnRouter $vpnRouter): RedirectResponse
    {
        if ($request->user()?->isAgen()) {
            return back()->with('error', 'Akun Agen tidak memiliki akses untuk menghapus router.');
        }

        if ((int) $vpnRouter->pppoe_customer_id !== (int) $pppoe->id) {
            return back()->with('error', 'Router ini bukan milik pelanggan tersebut.');
        }

        $result = $this->vpnAccounts->release($vpnRouter);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function pushVpnRouter(Request $request, PppoeCustomer $pppoe, VpnRouter $vpnRouter): RedirectResponse
    {
        if ($request->user()?->isAgen()) {
            return back()->with('error', 'Akun Agen tidak memiliki akses untuk mengisi CHR.');
        }

        if ((int) $vpnRouter->pppoe_customer_id !== (int) $pppoe->id) {
            return back()->with('error', 'Router ini bukan milik pelanggan tersebut.');
        }

        $result = $this->vpn->pushRouter($vpnRouter);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function storeVpnPort(Request $request, PppoeCustomer $pppoe): RedirectResponse
    {
        if ($request->user()?->isAgen()) {
            return back()->with('error', 'Akun Agen tidak memiliki akses untuk menambah port.');
        }

        $validated = $request->validate([
            'vpn_router_id' => ['required', 'integer'],
            'dst_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'note' => ['nullable', 'string', 'max:80'],
        ]);

        $vpnRouter = VpnRouter::query()
            ->where('pppoe_customer_id', $pppoe->id)
            ->whereKey($validated['vpn_router_id'])
            ->first();
        if (! $vpnRouter) {
            return back()->with('error', 'Pilih router yang akan menerima port ini.');
        }

        try {
            $forward = $this->vpnPlan->addCustom(
                $pppoe,
                (int) $validated['dst_port'],
                $validated['note'] ?? null,
                $vpnRouter,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $pushed = $this->vpn->pushForward($pppoe->fresh(), $forward);
        if (! $pushed['ok']) {
            return back()->with('error', 'Port khusus tersimpan, tetapi belum masuk ke CHR: '.$pushed['message']);
        }

        return back()->with('success', $pushed['message']);
    }

    public function destroyVpnPort(Request $request, PppoeCustomer $pppoe, int $port): RedirectResponse
    {
        if ($request->user()?->isAgen()) {
            return back()->with('error', 'Akun Agen tidak memiliki akses untuk menghapus port.');
        }

        $forward = VpnPortForward::query()
            ->where('pppoe_customer_id', $pppoe->id)
            ->whereKey($port)
            ->first();
        if (! $forward || $forward->kind !== VpnPortForward::KIND_CUSTOM) {
            return back()->with('error', 'Hanya port khusus yang bisa dihapus.');
        }

        $result = $this->vpn->removeForward($pppoe, $forward);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function vpnAccessPayload(PppoeCustomer $customer): ?array
    {
        if ($customer->pppService() !== PppoeCustomer::SERVICE_L2TP) {
            return null;
        }

        $this->vpnPlan->ensure($customer);
        $customer->refresh()->load(['vpnPortForwards', 'vpnRouters.portForwards', 'vpnRouters.invoices']);
        $server = VpnChrSettings::host() !== ''
            ? VpnChrSettings::host()
            : (string) ($customer->router?->host ?? '');

        return [
            'address' => $customer->vpn_remote_address,
            'series' => $customer->vpn_port_series,
            'server' => $server,
            'chr_ready' => VpnChrSettings::configured(),
            'router_limit' => 3,
            'router_count' => $customer->vpnRouters->count(),
            'spare_routers' => $this->vpnAccounts->spareCount($customer),
            'next_without_invoice' => $this->vpnAccounts->nextWithoutInvoice($customer),
            'extra_routers' => $customer->vpnRouters->map(fn (VpnRouter $router) => [
                'id' => $router->id,
                'name' => $router->name,
                'address' => $router->vpn_remote_address,
                'series' => $router->vpn_port_series,
                'included' => (bool) $router->included,
                'usable' => $router->isUsable(),
                'service_until' => $router->included
                    ? $customer->due_date?->toDateString()
                    : $router->service_until?->toDateString(),
                'billing_day' => $router->billing_day,
                'invoice_number' => $router->invoices->sortByDesc('id')->first()?->number,
                'invoice_status' => $router->invoices->sortByDesc('id')->first()?->status,
                'server_script' => $this->vpnServerScript->textForRouter($router),
                'client_script' => $this->vpnClientScript->buildForRouter($router),
                'ports' => $router->portForwards->map(fn ($forward) => [
                    'id' => $forward->id,
                    'public_port' => $forward->public_port,
                    'dst_port' => $forward->dst_port,
                    'label' => $forward->label,
                ])->values()->all(),
            ])->values()->all(),
            'server_script' => $this->vpnServerScript->text($customer),
            'client_script' => $this->vpnClientScript->build($customer),
            'ports' => $customer->vpnPortForwards->map(fn ($forward) => [
                'id' => $forward->id,
                'public_port' => $forward->public_port,
                'dst_port' => $forward->dst_port,
                'label' => $forward->label,
                'kind' => $forward->kind,
                'pushed_at' => $forward->pushed_at?->toIso8601String(),
                'note' => (int) $forward->dst_port === 22
                    ? 'Di router pelanggan, port 22 boleh diteruskan ke perangkat mana pun.'
                    : null,
            ])->values()->all(),
        ];
    }

    public function syncOverdue(): RedirectResponse
    {
        $today = now()->toDateString();

        $customers = PppoeCustomer::query()
            ->with(['router', 'package'])
            ->where('is_active', true)
            ->where(function ($q) use ($today) {
                $q->where(function ($q2) use ($today) {
                    $q2->where('status', 'active')
                        ->whereDate('due_date', '<', $today)
                        ->where('overdue_action', 'isolir')
                        ->where(function ($g) use ($today) {
                            $g->whereNull('grace_until')
                                ->orWhereDate('grace_until', '<', $today);
                        });
                })->orWhere(function ($q2) use ($today) {
                    $q2->where('status', 'isolated')
                        ->where(function ($g) use ($today) {
                            $g->whereDate('due_date', '>=', $today)
                                ->orWhereDate('grace_until', '>=', $today)
                                ->orWhere('overdue_action', '!=', 'isolir');
                        });
                });
            })
            ->get();

        $isolatedCount = 0;
        $restoredCount = 0;
        $errorCount = 0;

        foreach ($customers as $customer) {
            $this->sync->sync($customer);
            $fresh = $customer->fresh();

            if ($fresh->sync_status === 'error') {
                $errorCount++;
            } elseif ($fresh->status === 'isolated') {
                $isolatedCount++;
            } else {
                $restoredCount++;
            }
        }

        $message = "Proses auto isolir selesai. {$isolatedCount} diisolir, {$restoredCount} dikembalikan aktif";
        if ($errorCount > 0) {
            $message .= ", {$errorCount} gagal";
        }
        $message .= '.';

        return back()->with(
            $errorCount > 0 && $isolatedCount === 0 && $restoredCount === 0 ? 'error' : 'success',
            $message
        );
    }


    public function profiles(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'router_id' => ['required', 'exists:mikrotik_routers,id'],
        ]);

        $router = MikrotikRouter::query()->findOrFail($validated['router_id']);
        $result = $this->api->listPppProfiles($router);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function secret(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'router_id' => ['required', 'exists:mikrotik_routers,id'],
            'username' => ['required', 'string', 'max:100'],
        ]);

        $router = MikrotikRouter::query()->findOrFail($validated['router_id']);
        $result = $this->api->getPppSecret($router, $validated['username']);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    /**
     * Impor massal pelanggan dari sesi aktif yang belum terdaftar.
     * Password dipakai bersama (semua secret sama).
     */
    public function importFromSessions(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mikrotik_router_id' => ['required', 'exists:mikrotik_routers,id'],
            'subscription_package_id' => [
                'required',
                Rule::exists('subscription_packages', 'id')->where(
                    fn ($query) => $query->where(
                        'mikrotik_router_id',
                        $request->input('mikrotik_router_id')
                    )
                ),
            ],
            'usernames' => ['required', 'array', 'min:1'],
            'usernames.*' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'billing_day' => ['required', 'integer', 'min:1', 'max:28'],
            'overdue_action' => ['required', Rule::in(['bypass', 'isolir'])],
            'isolir_profile' => [
                Rule::requiredIf(fn () => $request->input('overdue_action') === 'isolir'),
                'nullable',
                'string',
                'max:120',
            ],
        ]);

        $router = MikrotikRouter::query()->findOrFail($validated['mikrotik_router_id']);
        $package = SubscriptionPackage::query()->findOrFail($validated['subscription_package_id']);
        $usernames = collect($validated['usernames'])
            ->map(fn ($u) => trim((string) $u))
            ->filter()
            ->unique(fn ($u) => strtolower($u))
            ->values();

        $created = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($usernames as $username) {
            $exists = PppoeCustomer::query()
                ->where('mikrotik_router_id', $router->id)
                ->whereRaw('LOWER(username) = ?', [strtolower($username)])
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $secretResult = $this->api->getPppSecret($router, $username);
            $secret = ($secretResult['ok'] ?? false) ? ($secretResult['secret'] ?? []) : [];

            $name = trim((string) ($secret['comment'] ?? ''));
            if ($name === '') {
                $name = $username;
            }

            $serviceProfile = trim((string) ($secret['profile'] ?? ''));
            if ($serviceProfile === '') {
                $serviceProfile = (string) $package->mikrotik_profile;
            }

            $pppService = PppoeCustomer::normalizePppService($secret['service'] ?? null);

            $payload = [
                'mikrotik_router_id' => $router->id,
                'subscription_package_id' => $package->id,
                'name' => $name,
                'username' => $username,
                'password' => $validated['password'],
                'ppp_service' => $pppService,
                'service_profile' => $serviceProfile,
                'start_date' => $validated['start_date'],
                'billing_day' => (int) $validated['billing_day'],
                'overdue_action' => $validated['overdue_action'],
                'isolir_profile' => $validated['overdue_action'] === 'isolir'
                    ? ($validated['isolir_profile'] ?? null)
                    : null,
                'notes' => $pppService === PppoeCustomer::SERVICE_L2TP
                    ? 'Diimpor dari sesi aktif L2TP'
                    : 'Diimpor dari sesi aktif PPPoE',
                'is_active' => true,
            ];

            try {
                $payload = $this->applyBillingCycle($payload, $package);

                $customer = PppoeCustomer::query()->create([
                    ...$payload,
                    'status' => 'active',
                    'sync_status' => 'pending',
                    'is_active' => true,
                ]);

                $this->billingService->createProrataInvoice($customer->fresh('package'));
                // Secret sudah ada di RouterOS (impor dari sesi) — jangan timpa password.
                $this->sync->sync($customer->fresh(['router', 'package']), pushPassword: false);
                $created++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        $message = "Impor selesai: {$created} dibuat";
        if ($skipped > 0) {
            $message .= ", {$skipped} dilewati (sudah ada)";
        }
        if ($failed > 0) {
            $message .= ", {$failed} gagal";
        }
        $message .= '.';

        return AdminListState::to('admin.customers.pppoe.sessions', AdminListState::PPPOE_SESSIONS, [
            'router_id' => $router->id,
        ])->with($failed > 0 && $created === 0 ? 'error' : 'success', $message);
    }

    private function formOptions(?int $routerId = null, mixed $currentPackageId = null): array
    {
        $profiles = [];
        $isolirProfiles = [];

        if ($routerId) {
            $router = MikrotikRouter::query()->find($routerId);
            if ($router) {
                $result = $this->api->listPppProfiles($router);
                $profiles = $result['profiles'] ?? [];
                $isolirProfiles = $result['isolir_profiles'] ?? [];
            }
        }

        return [
            'routers' => MikrotikRouter::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (MikrotikRouter $router) => $router->only(['id', 'name', 'host', 'port']))
                ->values(),
            'agents' => \App\Models\User::query()
                ->where('role', \App\Models\User::ROLE_AGEN)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->values(),
            'packages' => SubscriptionPackage::query()
                ->with('router')
                ->where(function ($query) use ($currentPackageId) {
                    $query->where('is_active', true);
                    if ($currentPackageId) {
                        $query->orWhere('id', $currentPackageId);
                    }
                })
                ->orderBy('sort_order')
                ->orderBy('price')
                ->get()
                ->map(fn (SubscriptionPackage $package) => $package->toOptionArray())
                ->values(),
            'profiles' => $profiles,
            'isolir_profiles' => $isolirProfiles,
            'billing_days' => range(1, 28),
            'overdue_actions' => [
                ['value' => 'bypass', 'label' => 'Bypass (tetap aktif)'],
                ['value' => 'isolir', 'label' => 'Isolir'],
            ],
        ];
    }

    private function validateCustomer(Request $request, ?PppoeCustomer $customer = null): array
    {
        $validated = $request->validate(
            [
                'mikrotik_router_id' => ['required', 'exists:mikrotik_routers,id'],
                'agent_id' => [
                    'nullable',
                    Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', User::ROLE_AGEN)),
                ],
                'subscription_package_id' => [
                    'required',
                    Rule::exists('subscription_packages', 'id')->where(
                        fn ($query) => $query->where(
                            'mikrotik_router_id',
                            $request->input('mikrotik_router_id')
                        )
                    ),
                ],
                'name' => ['required', 'string', 'max:150'],
                'phone' => ['nullable', 'string', 'max:50'],
                'address' => ['nullable', 'string', 'max:500'],
                'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
                'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
                'username' => [
                    'required',
                    'string',
                    'max:100',
                    Rule::unique('pppoe_customers', 'username')
                        ->where(fn ($q) => $q->where('mikrotik_router_id', $request->input('mikrotik_router_id')))
                        ->ignore($customer?->id),
                ],
                'ppp_service' => ['nullable', Rule::in([PppoeCustomer::SERVICE_PPPOE, PppoeCustomer::SERVICE_L2TP])],
                'password' => [$customer ? 'nullable' : 'required', 'string', 'max:255'],
                'service_profile' => ['nullable', 'string', 'max:120'],
                'start_date' => ['required', 'date'],
                'due_date' => ['required', 'date', 'after:start_date'],
                'billing_day' => ['nullable', 'integer', 'min:1', 'max:28'],
                'overdue_action' => ['required', Rule::in(['bypass', 'isolir'])],
                'isolir_profile' => [
                    Rule::requiredIf(fn () => $request->input('overdue_action') === 'isolir'),
                    'nullable',
                    'string',
                    'max:120',
                ],
                'notes' => ['nullable', 'string', 'max:1000'],
                'is_active' => ['nullable', 'boolean'],
                'agent_pays_commission' => ['sometimes', 'boolean'],
                'stop_date' => ['nullable', 'date'],
                'service_change_date' => ['nullable', 'date'],
                'reactivate_date' => ['nullable', 'date'],
            ],
            [
                'subscription_package_id.exists' => 'Paket langganan tidak tersedia untuk router yang dipilih.',
                'due_date.after' => 'Tanggal jatuh tempo harus setelah tanggal mulai layanan.',
            ]
        );

        $validated['ppp_service'] = PppoeCustomer::normalizePppService($validated['ppp_service'] ?? null);

        if (empty($validated['agent_id'])) {
            $validated['agent_pays_commission'] = false;
        } else {
            $validated['agent_pays_commission'] = $request->boolean('agent_pays_commission');
        }

        return $validated;
    }

    /**
     * Prefill form dari sesi aktif + PPP secret MikroTik.
     *
     * @return array<string, mixed>|null
     */
    private function buildSessionPrefill(?int $routerId, string $username, ?string $requestedService = null): ?array
    {
        if (! $routerId || $username === '') {
            return null;
        }

        $router = MikrotikRouter::query()->find($routerId);
        if (! $router) {
            return null;
        }

        $secretResult = $this->api->getPppSecret($router, $username);
        $secret = ($secretResult['ok'] ?? false) ? ($secretResult['secret'] ?? []) : [];

        $profile = trim((string) ($secret['profile'] ?? ''));
        $comment = trim((string) ($secret['comment'] ?? ''));
        $password = (string) ($secret['password'] ?? '');
        $pppService = $requestedService ?? PppoeCustomer::normalizePppService($secret['service'] ?? null);

        $packageId = null;
        if ($profile !== '') {
            $packageId = SubscriptionPackage::query()
                ->where('is_active', true)
                ->where('mikrotik_router_id', $router->id)
                ->where('mikrotik_profile', $profile)
                ->orderBy('sort_order')
                ->value('id');
        }

        $isolirProfile = null;
        $profilesResult = $this->api->listPppProfiles($router);
        $isolirProfiles = $profilesResult['isolir_profiles'] ?? [];
        if (count($isolirProfiles) === 1) {
            $isolirProfile = $isolirProfiles[0]['name'] ?? null;
        }

        return [
            'from_session' => true,
            'mikrotik_router_id' => $router->id,
            'subscription_package_id' => $packageId,
            'name' => $comment !== '' ? $comment : $username,
            'username' => $username,
            'password' => $password,
            'ppp_service' => $pppService,
            'service_profile' => $profile !== '' ? $profile : null,
            'start_date' => now()->toDateString(),
            'billing_day' => AppSettings::int('app_default_billing_day', 1),
            'overdue_action' => 'isolir',
            'isolir_profile' => $isolirProfile,
            'secret_found' => (bool) ($secretResult['ok'] ?? false),
            'secret_message' => $secretResult['message'] ?? null,
        ];
    }

    /**
     * Pelanggan yang sudah jatuh tempo dan tagihannya sudah ditentukan
     * boleh ganti paket tanpa menghitung ulang dari tanggal mulai lama.
     */
    private function packageChangeDefersToNextMonth(PppoeCustomer $existing): bool
    {
        $due = $existing->due_date?->copy()->startOfDay();
        if (! $due || $due->greaterThan(now()->startOfDay())) {
            return false;
        }

        return $this->billingService->hasDeterminedInvoice($existing)
            || (int) ($existing->first_bill_amount ?? 0) > 0;
    }

    private function shouldDeferPackageChangeToNextMonth(array $validated, PppoeCustomer $existing): bool
    {
        if (! $this->packageChangeDefersToNextMonth($existing)) {
            return false;
        }

        if ((int) $validated['subscription_package_id'] === (int) $existing->subscription_package_id) {
            return false;
        }

        $incomingStart = Carbon::parse($validated['start_date'])->toDateString();

        return $incomingStart === $existing->start_date?->toDateString();
    }

    private function nextMonthDueAfterPackageChange(PppoeCustomer $existing, ?string $requestedDue = null): string
    {
        $billingDay = $this->billing->normalizeBillingDay(
            (int) ($existing->billing_day ?: $existing->due_date?->day ?: 1)
        );
        $today = now()->startOfDay();

        if ($requestedDue) {
            $requestedDay = $this->billing->normalizeBillingDay((int) Carbon::parse($requestedDue)->day);
            $aligned = $this->billing->alignToBillingDay($requestedDue, $requestedDay);
            if ($aligned->greaterThan($today)) {
                return $aligned->toDateString();
            }

            $billingDay = $requestedDay;
        }

        $next = $this->billing->advanceDueDate($existing->due_date, $billingDay);
        if ($next->lessThanOrEqualTo($today)) {
            $next = $this->billing->advanceDueDate($today, $billingDay);
        }

        return $next->toDateString();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function preserveHistoricalBilling(array $validated, PppoeCustomer $existing, bool $keepDue = true): array
    {
        $validated['start_date'] = $existing->start_date?->toDateString();
        $validated['first_bill_amount'] = $existing->first_bill_amount;
        $validated['first_bill_days'] = $existing->first_bill_days;

        if ($keepDue) {
            $validated['billing_day'] = $existing->billing_day;
            $validated['due_date'] = $existing->due_date?->toDateString();
        } else {
            $validated['billing_day'] = $this->billingDayFromInput($validated);
            $validated['due_date'] = $this->explicitDueFromInput($validated, (int) $validated['billing_day'])
                ?? $existing->due_date?->toDateString();
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function customerColumns(array $validated): array
    {
        unset($validated['stop_date'], $validated['service_change_date'], $validated['reactivate_date']);

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function customerUpdateMessage(array $result, bool $disconnectForNewProfile): string
    {
        $kind = $result['kind'] ?? 'none';
        $invoice = $result['invoice'] ?? null;
        $format = static fn (int $amount): string => 'Rp '.number_format($amount, 0, ',', '.');
        $disconnectNote = $disconnectForNewProfile
            ? ' Sesi PPPoE diputus agar profile baru langsung dipakai.'
            : '';

        return match ($kind) {
            'stop' => $this->stopUpdateMessage($result, $format),
            'reactivate' => $invoice instanceof Invoice
                ? 'Pelanggan diaktifkan kembali. Tagihan prorata '.$invoice->number
                    .' sebesar '.$format((int) $invoice->total)
                    .' jatuh tempo '.($invoice->due_date?->format('d/m/Y') ?? '—')
                    .'. Dihitung dari tanggal aktif kembali sampai jatuh tempo.'
                    .' Sesi PPPoE diputus agar profile paket langsung dipakai.'
                : 'Pelanggan diaktifkan kembali.',
            'defer' => ($invoice instanceof Invoice
                ? 'Paket layanan diubah. Tagihan '.$invoice->number
                    .' sebesar '.$format((int) $invoice->total)
                    .' jatuh tempo '.($invoice->due_date?->format('d/m/Y') ?? '—')
                    .'. Tanggal mulai layanan pada bulan sebelumnya tidak dihitung.'
                : 'Paket layanan diubah.').$disconnectNote,
            'mid' => $this->midCycleUpdateMessage($result, $format).$disconnectNote,
            'due' => ($invoice instanceof Invoice
                ? 'Tanggal tagihan diubah. Tagihan '.$invoice->number
                    .' sebesar '.$format((int) $invoice->total)
                    .' dihitung dari jatuh tempo terakhir yang sudah lunas ('
                    .Carbon::parse($result['anchor'] ?? $invoice->period_start)->format('d/m/Y')
                    .'), tanpa tagihan awal pendaftaran.'
                : 'Tanggal tagihan diubah. Tagihan awal pendaftaran tidak dihitung ulang.')
                .$disconnectNote,
            default => 'Pelanggan PPPoE berhasil diperbarui.',
        };
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  callable(int): string  $format
     */
    private function stopUpdateMessage(array $result, callable $format): string
    {
        $invoice = $result['invoice'] ?? null;
        $credit = (int) ($result['credit'] ?? 0);

        if ($invoice instanceof Invoice) {
            return 'Layanan dihentikan. Tagihan pemakaian '.$invoice->number
                .' sebesar '.$format((int) $invoice->total)
                .' sampai '.($invoice->period_end?->format('d/m/Y') ?? 'tanggal berhenti').'.';
        }

        if ($credit > 0) {
            return 'Layanan dihentikan. Pemakaian sampai tanggal berhenti lebih kecil dari yang sudah dibayar. Kredit '
                .$format($credit).' dipakai pada tagihan berikutnya.';
        }

        return 'Layanan dihentikan. Tidak ada tagihan tambahan untuk tanggal berhenti ini.';
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  callable(int): string  $format
     */
    private function midCycleUpdateMessage(array $result, callable $format): string
    {
        $invoice = $result['invoice'] ?? null;
        $credit = (int) ($result['credit'] ?? 0);

        if ($invoice instanceof Invoice) {
            return 'Paket layanan diubah di tengah siklus. Tagihan selisih '.$invoice->number
                .' sebesar '.$format((int) $invoice->total)
                .' jatuh tempo '.($invoice->due_date?->format('d/m/Y') ?? '—').'.';
        }

        if ($credit > 0) {
            return 'Paket layanan diturunkan. Kredit '.$format($credit)
                .' mengurangi tagihan yang masih terbuka atau tagihan berikutnya.';
        }

        return 'Paket layanan diubah.';
    }

    /**
     * Catatan, nama, dan field non-tagihan tidak boleh menghitung ulang prorata.
     */
    private function billingInputsUnchanged(array $validated, PppoeCustomer $existing): bool
    {
        $incomingStart = Carbon::parse($validated['start_date'])->toDateString();
        $incomingDue = Carbon::parse($validated['due_date'])->toDateString();

        return $incomingStart === $existing->start_date?->toDateString()
            && $incomingDue === $existing->due_date?->toDateString()
            && (int) $validated['subscription_package_id'] === (int) $existing->subscription_package_id;
    }

    private function applyBillingCycle(
        array $validated,
        ?SubscriptionPackage $package,
        ?PppoeCustomer $existing = null,
    ): array {
        $packagePrice = (int) ($package?->price ?? 0);
        $billingDay = $this->billingDayFromInput($validated);
        $explicitDue = $this->explicitDueFromInput($validated, $billingDay);

        if ($existing && $this->billingService->hasCompletedFirstBillingCycle($existing)) {
            // Koreksi due tanggal lengkap diizinkan; first_bill historis tidak dihitung ulang.
            $validated['billing_day'] = $billingDay;
            $validated['due_date'] = $explicitDue ?? $existing->due_date?->toDateString();
            $validated['first_bill_amount'] = $existing->first_bill_amount;
            $validated['first_bill_days'] = $existing->first_bill_days;

            return $validated;
        }

        $prorata = $this->billing->calculateProrata(
            $validated['start_date'],
            $billingDay,
            $packagePrice,
            $explicitDue,
        );

        $validated['billing_day'] = $prorata['billing_day'];
        $validated['due_date'] = $prorata['due_date'];
        $validated['first_bill_amount'] = $prorata['amount'];
        $validated['first_bill_days'] = $prorata['days'];

        return $validated;
    }

    private function billingDayFromInput(array $validated): int
    {
        if (! empty($validated['due_date'])) {
            return $this->billing->normalizeBillingDay(
                (int) Carbon::parse($validated['due_date'])->day
            );
        }

        return $this->billing->normalizeBillingDay((int) ($validated['billing_day'] ?? 1));
    }

    private function explicitDueFromInput(array $validated, int $billingDay): ?string
    {
        if (empty($validated['due_date'])) {
            return null;
        }

        return $this->billing->alignToBillingDay($validated['due_date'], $billingDay)->toDateString();
    }
}
