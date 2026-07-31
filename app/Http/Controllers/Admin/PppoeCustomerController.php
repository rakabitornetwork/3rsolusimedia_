<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SubscriptionPackage;
use App\Services\BillingCycleService;
use App\Services\BillingService;
use App\Services\MikrotikApiService;
use App\Services\PppoeSyncService;
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
    ) {
    }

    public function index(Request $request): Response
    {
        $query = PppoeCustomer::query()
            ->with(['router', 'package'])
            ->latest();

        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($routerId = $request->get('router_id')) {
            $query->where('mikrotik_router_id', $routerId);
        }

        $customers = $query->paginate(15)->withQueryString()->through(
            fn (PppoeCustomer $customer) => $customer->toSafeArray()
        );

        return Inertia::render('Admin/Customers/Pppoe/Index', [
            'customers' => $customers,
            'filters' => [
                'q' => $request->get('q', ''),
                'status' => $request->get('status', ''),
                'router_id' => $request->get('router_id', ''),
            ],
            'routers' => MikrotikRouter::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'host']),
            'stats' => [
                'total' => PppoeCustomer::query()->count(),
                'active' => PppoeCustomer::query()->where('status', 'active')->count(),
                'isolated' => PppoeCustomer::query()->where('status', 'isolated')->count(),
                'overdue' => PppoeCustomer::query()->whereDate('due_date', '<', now()->toDateString())->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Customers/Pppoe/Form', [
            'customer' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
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
        $this->sync->sync($customer->fresh(['router', 'package']));

        $message = 'Pelanggan PPPoE berhasil ditambahkan. Tagihan pertama (prorata): Rp '.
            number_format((int) $customer->first_bill_amount, 0, ',', '.').'.';

        if ($invoice) {
            $message .= ' Invoice: '.$invoice->number.'.';
        }

        return redirect()
            ->route('admin.customers.pppoe')
            ->with('success', $message);
    }

    public function edit(PppoeCustomer $pppoe): Response
    {
        $pppoe->load(['router', 'package']);

        return Inertia::render('Admin/Customers/Pppoe/Form', [
            'customer' => $pppoe->toSafeArray(),
            ...$this->formOptions($pppoe->mikrotik_router_id),
        ]);
    }

    public function update(Request $request, PppoeCustomer $pppoe): RedirectResponse
    {
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

        if (empty($validated['password'])) {
            unset($payload['password']);
        }

        $pppoe->update($payload);
        $customer = $pppoe->fresh(['router', 'package']);
        $this->billingService->syncUnpaidProrataInvoice($customer);
        $this->sync->sync($customer);

        return redirect()
            ->route('admin.customers.pppoe')
            ->with(
                'success',
                'Pelanggan PPPoE berhasil diperbarui. Tagihan pertama (prorata): Rp '.
                number_format((int) $customer->first_bill_amount, 0, ',', '.').'.'
            );
    }

    public function destroy(PppoeCustomer $pppoe): RedirectResponse
    {
        if ($pppoe->router) {
            $this->api->removePppSecret($pppoe->router, $pppoe->username);
        }

        $pppoe->delete();

        return redirect()
            ->route('admin.customers.pppoe')
            ->with('success', 'Pelanggan PPPoE berhasil dihapus.');
    }

    public function sync(PppoeCustomer $pppoe): RedirectResponse
    {
        $this->sync->sync($pppoe->load(['router', 'package']));

        return back()->with(
            $pppoe->fresh()->sync_status === 'synced' ? 'success' : 'error',
            $pppoe->fresh()->sync_message ?: 'Sinkronisasi selesai.'
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

    private function formOptions(?int $routerId = null): array
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
            'packages' => SubscriptionPackage::query()
                ->where('is_active', true)
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
        return $request->validate([
            'mikrotik_router_id' => ['required', 'exists:mikrotik_routers,id'],
            'subscription_package_id' => ['required', 'exists:subscription_packages,id'],
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
        ]);
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

        // Jangan menimpa due_date berjalan kecuali siklus diubah.
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
