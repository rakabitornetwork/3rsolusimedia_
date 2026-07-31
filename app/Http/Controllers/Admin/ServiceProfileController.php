<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MikrotikRouter;
use App\Models\SubscriptionPackage;
use App\Services\MikrotikApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ServiceProfileController extends Controller
{
    public function __construct(private readonly MikrotikApiService $api)
    {
    }

    public function index(): Response
    {
        $packages = SubscriptionPackage::query()
            ->withCount('customers')
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get()
            ->map(fn (SubscriptionPackage $package) => [
                ...$package->toOptionArray(),
                'sort_order' => $package->sort_order,
                'customers_count' => $package->customers_count,
            ]);

        return Inertia::render('Admin/Customers/ServiceProfiles/Index', [
            'packages' => $packages,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Customers/ServiceProfiles/Form', [
            'package' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePackage($request);

        SubscriptionPackage::create([
            ...$validated,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()
            ->route('admin.customers.pppoe.service-profiles')
            ->with('success', 'Paket layanan berhasil ditambahkan.');
    }

    public function edit(SubscriptionPackage $service_profile): Response
    {
        return Inertia::render('Admin/Customers/ServiceProfiles/Form', [
            'package' => [
                ...$service_profile->toOptionArray(),
                'sort_order' => $service_profile->sort_order,
            ],
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, SubscriptionPackage $service_profile): RedirectResponse
    {
        $validated = $this->validatePackage($request, $service_profile);

        $service_profile->update([
            ...$validated,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()
            ->route('admin.customers.pppoe.service-profiles')
            ->with('success', 'Paket layanan berhasil diperbarui.');
    }

    public function destroy(SubscriptionPackage $service_profile): RedirectResponse
    {
        if ($service_profile->customers()->exists()) {
            return back()->with(
                'error',
                'Profile masih dipakai pelanggan. Pindahkan pelanggan dulu sebelum menghapus.'
            );
        }

        $service_profile->delete();

        return redirect()
            ->route('admin.customers.pppoe.service-profiles')
            ->with('success', 'Paket layanan berhasil dihapus.');
    }

    private function formOptions(): array
    {
        $routers = MikrotikRouter::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'host']);

        $routerProfiles = [];
        $firstRouter = $routers->first();

        if ($firstRouter) {
            $router = MikrotikRouter::query()->find($firstRouter->id);
            $result = $this->api->listPppProfiles($router);
            $routerProfiles = $result['profiles'] ?? [];
        }

        return [
            'routers' => $routers,
            'router_profiles' => $routerProfiles,
            'default_router_id' => $firstRouter?->id,
        ];
    }

    private function validatePackage(Request $request, ?SubscriptionPackage $package = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('subscription_packages', 'name')->ignore($package?->id),
            ],
            'price' => ['required', 'integer', 'min:0'],
            'mikrotik_profile' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
