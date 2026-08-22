<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Services\BillingCycleService;
use App\Services\BillingService;
use App\Services\Messaging\CustomerNotifier;
use App\Services\MikrotikApiService;
use App\Services\PppoeSyncService;
use App\Support\AdminListState;
use App\Support\AppSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PppoeCustomerController extends Controller
{
    public function __construct(
        private readonly MikrotikApiService $api,
        private readonly BillingCycleService $billing,
        private readonly BillingService $billingService,
        private readonly PppoeSyncService $sync,
        private readonly CustomerNotifier $notifier,
    ) {
    }

    public function index(Request $request): Response
    {
        AdminListState::apply($request, AdminListState::PPPOE, [
            'q', 'status', 'router_id', 'sort', 'direction', 'page',
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
        ];

        if (! array_key_exists($sort, $allowedSorts)) {
            $sort = 'name';
        }

        $query = PppoeCustomer::query()
            ->with(['router', 'package', 'agent'])
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

        $query->orderBy($allowedSorts[$sort], $direction)
            ->orderBy('pppoe_customers.id', $direction);

        $customers = $query->get()->map(
            fn (PppoeCustomer $customer) => $customer->toSafeArray()
        )->values();

        return Inertia::render('Admin/Customers/Pppoe/Index', [
            'customers' => $customers,
            'filters' => [
                'q' => $request->get('q', ''),
                'status' => $request->get('status', ''),
                'router_id' => $request->get('router_id', ''),
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
        ];
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

        $prefill = $this->buildSessionPrefill($routerId, $username);

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

        $invoice = $this->billingService->createProrataInvoice($customer->fresh('package'));
        $this->sync->sync($customer->fresh(['router', 'package']), pushPassword: true);
        $this->notifier->notifyWelcome($customer->fresh('package'), $invoice);

        $message = 'Pelanggan PPPoE berhasil ditambahkan. Tagihan pertama (prorata): Rp '.
            number_format((int) $customer->first_bill_amount, 0, ',', '.').'.';

        if ($invoice) {
            $message .= ' Invoice: '.$invoice->number.'.';
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

        $pppoe->load(['router', 'package']);

        return Inertia::render('Admin/Customers/Pppoe/Form', [
            'customer' => $pppoe->toSafeArray(),
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
        $package = isset($validated['subscription_package_id'])
            ? SubscriptionPackage::query()->find($validated['subscription_package_id'])
            : null;

        if ($package && empty($validated['service_profile'])) {
            $validated['service_profile'] = $package->mikrotik_profile;
        }

        $validated = $this->applyBillingCycle($validated, $package, $pppoe);

        $isActive = $request->boolean('is_active');

        $payload = [
            ...$validated,
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

        $pppoe->update($payload);
        $this->sync->sync($pppoe->fresh(['router', 'package']), pushPassword: $passwordChanged);

        return AdminListState::to('admin.customers.pppoe', AdminListState::PPPOE)
            ->with('success', 'Pelanggan PPPoE berhasil diperbarui.');
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

            $payload = [
                'mikrotik_router_id' => $router->id,
                'subscription_package_id' => $package->id,
                'name' => $name,
                'username' => $username,
                'password' => $validated['password'],
                'service_profile' => $serviceProfile,
                'start_date' => $validated['start_date'],
                'billing_day' => (int) $validated['billing_day'],
                'overdue_action' => $validated['overdue_action'],
                'isolir_profile' => $validated['overdue_action'] === 'isolir'
                    ? ($validated['isolir_profile'] ?? null)
                    : null,
                'notes' => 'Diimpor dari sesi aktif PPPoE',
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
        return $request->validate(
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
                'password' => [$customer ? 'nullable' : 'required', 'string', 'max:255'],
                'service_profile' => ['nullable', 'string', 'max:120'],
                'start_date' => ['required', 'date'],
                'billing_day' => ['required', 'integer', 'min:1', 'max:28'],
                'overdue_action' => ['required', Rule::in(['bypass', 'isolir'])],
                'isolir_profile' => [
                    Rule::requiredIf(fn () => $request->input('overdue_action') === 'isolir'),
                    'nullable',
                    'string',
                    'max:120',
                ],
                'notes' => ['nullable', 'string', 'max:1000'],
                'is_active' => ['nullable', 'boolean'],
            ],
            [
                'subscription_package_id.exists' => 'Paket langganan tidak tersedia untuk router yang dipilih.',
            ]
        );
    }

    /**
     * Prefill form dari sesi aktif + PPP secret MikroTik.
     *
     * @return array<string, mixed>|null
     */
    private function buildSessionPrefill(?int $routerId, string $username): ?array
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
            'service_profile' => $profile !== '' ? $profile : null,
            'start_date' => now()->toDateString(),
            'billing_day' => AppSettings::int('app_default_billing_day', 1),
            'overdue_action' => 'isolir',
            'isolir_profile' => $isolirProfile,
            'secret_found' => (bool) ($secretResult['ok'] ?? false),
            'secret_message' => $secretResult['message'] ?? null,
        ];
    }

    private function applyBillingCycle(
        array $validated,
        ?SubscriptionPackage $package,
        ?PppoeCustomer $existing = null,
    ): array {
        $packagePrice = (int) ($package?->price ?? 0);

        if (! $existing) {
            $prorata = $this->billing->calculateProrata(
                $validated['start_date'],
                (int) $validated['billing_day'],
                $packagePrice,
            );

            $validated['billing_day'] = $prorata['billing_day'];
            $validated['due_date'] = $prorata['due_date'];
            $validated['first_bill_amount'] = $prorata['amount'];
            $validated['first_bill_days'] = $prorata['days'];

            return $validated;
        }

        $startChanged = $existing->start_date?->toDateString() !== $validated['start_date'];
        $dayChanged = (int) $existing->billing_day !== (int) $validated['billing_day'];
        $firstCycleDone = $this->billingService->hasCompletedFirstBillingCycle($existing);

        // Setelah siklus pertama selesai (sudah bayar / prorata diganti),
        // jangan hitung ulang first_bill dari start → due berjalan
        // (itu yang membuat "62 hari" / nominal dobel).
        if ($firstCycleDone && ! $startChanged && ! $dayChanged) {
            $validated['billing_day'] = $this->billing->normalizeBillingDay(
                (int) $validated['billing_day']
            );
            $validated['due_date'] = $existing->due_date?->toDateString();
            $validated['first_bill_amount'] = $existing->first_bill_amount;
            $validated['first_bill_days'] = $existing->first_bill_days;

            return $validated;
        }

        if ($firstCycleDone && ($startChanged || $dayChanged)) {
            // Ubah pola billing setelah bayar: pertahankan first_bill historis
            // dan due_date berjalan (koreksi due tetap manual bila perlu).
            $validated['billing_day'] = $this->billing->normalizeBillingDay(
                (int) $validated['billing_day']
            );
            $validated['due_date'] = $existing->due_date?->toDateString();
            $validated['first_bill_amount'] = $existing->first_bill_amount;
            $validated['first_bill_days'] = $existing->first_bill_days;

            return $validated;
        }

        // Jangan menimpa due_date berjalan kecuali siklus diubah (sebelum bayar pertama).
        $dueDate = ($startChanged || $dayChanged)
            ? null
            : $existing->due_date?->toDateString();

        $prorata = $this->billing->calculateProrata(
            $validated['start_date'],
            (int) $validated['billing_day'],
            $packagePrice,
            $dueDate,
        );

        $validated['billing_day'] = $prorata['billing_day'];
        $validated['due_date'] = $prorata['due_date'];
        $validated['first_bill_amount'] = $prorata['amount'];
        $validated['first_bill_days'] = $prorata['days'];

        return $validated;
    }
}
