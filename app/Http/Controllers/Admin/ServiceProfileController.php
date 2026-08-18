<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MikrotikRouter;
use App\Models\SubscriptionPackage;
use App\Support\AdminListState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ServiceProfileController extends Controller
{
    public function index(Request $request): Response
    {
        AdminListState::apply($request, AdminListState::SERVICE_PROFILES, ['router_id']);

        $this->debugLog('index', $request);
        $routers = MikrotikRouter::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'host']);

        $routerId = $request->get('router_id');

        $packagesQuery = SubscriptionPackage::query()
            ->with('router')
            ->withCount('customers')
            ->orderBy('sort_order')
            ->orderBy('price');

        if ($routerId) {
            $packagesQuery->where('mikrotik_router_id', $routerId);
        }

        $packages = $packagesQuery
            ->get()
            ->map(fn (SubscriptionPackage $package) => [
                ...$package->toOptionArray(),
                'sort_order' => $package->sort_order,
                'customers_count' => $package->customers_count,
            ]);

        return Inertia::render('Admin/Customers/ServiceProfiles/Index', [
            'packages' => $packages,
            'routers' => $routers,
            'filters' => [
                'router_id' => $routerId ?: '',
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->debugLog('create', $request);
        $routerId = $request->integer('router_id')
            ?: AdminListState::lastRouterId($request);

        return Inertia::render('Admin/Customers/ServiceProfiles/Form', [
            'package' => null,
            ...$this->formOptions($routerId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->debugLog('store', $request);
        $validated = $this->validatePackage($request);

        SubscriptionPackage::create([
            ...$validated,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return AdminListState::to('admin.customers.pppoe.service-profiles', AdminListState::SERVICE_PROFILES, array_filter([
            'router_id' => $validated['mikrotik_router_id'] ?? null,
        ]))->with('success', 'Paket layanan berhasil ditambahkan.');
    }

    public function edit(Request $request, SubscriptionPackage $service_profile): Response
    {
        $this->debugLog('edit', $request, $service_profile);
        $routerId = $request->filled('router_id')
            ? $request->integer('router_id')
            : ($service_profile->mikrotik_router_id ? (int) $service_profile->mikrotik_router_id : null);

        return Inertia::render('Admin/Customers/ServiceProfiles/Form', [
            'package' => [
                ...$service_profile->toOptionArray(),
                'sort_order' => $service_profile->sort_order,
            ],
            ...$this->formOptions($routerId),
        ]);
    }

    public function update(Request $request, SubscriptionPackage $service_profile): RedirectResponse
    {
        $this->debugLog('update', $request, $service_profile);
        $validated = $this->validatePackage($request, $service_profile);

        $service_profile->update([
            ...$validated,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return AdminListState::to('admin.customers.pppoe.service-profiles', AdminListState::SERVICE_PROFILES, array_filter([
            'router_id' => $validated['mikrotik_router_id'] ?? null,
        ]))->with('success', 'Paket layanan berhasil diperbarui.');
    }

    public function destroy(Request $request, SubscriptionPackage $service_profile): RedirectResponse
    {
        $this->debugLog('destroy', $request, $service_profile);
        if ($service_profile->customers()->exists()) {
            return back()->with(
                'error',
                'Profile masih dipakai pelanggan. Pindahkan pelanggan dulu sebelum menghapus.'
            );
        }

        $routerId = $service_profile->mikrotik_router_id;
        $service_profile->delete();

        return AdminListState::to('admin.customers.pppoe.service-profiles', AdminListState::SERVICE_PROFILES, array_filter([
            'router_id' => $request->get('router_id', $routerId),
        ]))->with('success', 'Paket layanan berhasil dihapus.');
    }

    private function formOptions(?int $routerId = null): array
    {
        $routers = MikrotikRouter::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'host']);

        $selectedRouterId = $routerId ?: $routers->first()?->id;

        // Profile di-load di browser via /profiles agar halaman edit
        // tidak gagal jika koneksi RouterOS bermasalah.
        return [
            'routers' => $routers,
            'router_profiles' => [],
            'default_router_id' => $selectedRouterId,
        ];
    }


    private function debugLog(string $event, Request $request, ?SubscriptionPackage $package = null): void
    {
        @file_put_contents(
            storage_path('logs/service-profiles-debug.log'),
            date('c')." {$event} ".$request->method().' '.$request->fullUrl()
            .' user='.($request->user()?->id ?? 'guest')
            .' package='.($package?->id ?? '-')
            .' inertia='.($request->header('X-Inertia') ? '1' : '0')
            .' input='.json_encode($request->except(['password', '_token']))
            ."\n",
            FILE_APPEND
        );
    }

    private function validatePackage(Request $request, ?SubscriptionPackage $package = null): array
    {
        return $request->validate([
            'mikrotik_router_id' => ['required', 'exists:mikrotik_routers,id'],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('subscription_packages', 'name')
                    ->where(fn ($query) => $query->where(
                        'mikrotik_router_id',
                        $request->integer('mikrotik_router_id')
                    ))
                    ->ignore($package?->id),
            ],
            'price' => ['required', 'integer', 'min:0'],
            'mikrotik_profile' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
