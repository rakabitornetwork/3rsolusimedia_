<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MikrotikRouter;
use App\Services\MikrotikApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MikrotikPppProfileController extends Controller
{
    public function __construct(private readonly MikrotikApiService $api)
    {
    }

    public function index(Request $request): Response
    {
        $routers = $this->activeRouters();
        $routerId = (int) $request->query('router_id', $routers->first()?->id ?? 0);
        $selected = $routers->firstWhere('id', $routerId) ?? $routers->first();

        $profiles = [];
        $error = null;

        if ($selected) {
            // Reload full model so encrypted credentials are available for API.
            $router = MikrotikRouter::query()->find($selected->id);
            $result = $this->api->listPppProfiles($router);
            if ($result['ok']) {
                $profiles = $result['profiles'] ?? [];
            } else {
                $error = $result['message'] ?? 'Gagal mengambil profile dari RouterOS.';
            }
        }

        return Inertia::render('Admin/Customers/MikrotikProfiles/Index', [
            'routers' => $routers->values(),
            'selected_router_id' => $selected?->id,
            'profiles' => $profiles,
            'error' => $error,
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $routers = $this->activeRouters();

        if ($routers->isEmpty()) {
            return redirect()
                ->route('admin.customers.pppoe.mikrotik-profiles')
                ->with('error', 'Tambahkan router MikroTik aktif terlebih dahulu.');
        }

        $routerId = (int) $request->query('router_id', $routers->first()->id);
        $selected = $routers->firstWhere('id', $routerId) ?? $routers->first();
        $router = MikrotikRouter::query()->findOrFail($selected->id);

        return Inertia::render('Admin/Customers/MikrotikProfiles/Form', [
            'profile' => null,
            'routers' => $routers->values(),
            'selected_router_id' => $router->id,
            ...$this->formSelectOptions($router),
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'router_id' => ['required', 'exists:mikrotik_routers,id'],
        ]);

        $router = MikrotikRouter::query()->findOrFail($validated['router_id']);
        $pools = $this->api->listIpPools($router);
        $queueTypes = $this->api->listQueueTypes($router);
        $ok = ($pools['ok'] ?? false) || ($queueTypes['ok'] ?? false);

        return response()->json([
            'ok' => $ok,
            'pools' => $pools['pools'] ?? [],
            'queue_types' => $queueTypes['queue_types'] ?? [],
            'message' => $ok ? null : ($pools['message'] ?? $queueTypes['message'] ?? 'Gagal mengambil data dari RouterOS'),
        ], $ok ? 200 : 422);
    }

    public function pools(Request $request): JsonResponse
    {
        return $this->options($request);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProfile($request);
        $router = MikrotikRouter::query()->findOrFail($validated['router_id']);

        $result = $this->api->createPppProfile($router, $validated);

        if (! $result['ok']) {
            return back()->withInput()->with('error', $result['message']);
        }

        return redirect()
            ->route('admin.customers.pppoe.mikrotik-profiles', ['router_id' => $router->id])
            ->with('success', $result['message']);
    }

    public function edit(Request $request, MikrotikRouter $router, string $profile): Response|RedirectResponse
    {
        $result = $this->api->getPppProfile($router, $profile);

        if (! $result['ok']) {
            return redirect()
                ->route('admin.customers.pppoe.mikrotik-profiles', ['router_id' => $router->id])
                ->with('error', $result['message'] ?? 'Profile tidak ditemukan.');
        }

        return Inertia::render('Admin/Customers/MikrotikProfiles/Form', [
            'profile' => $result['profile'],
            'routers' => $this->activeRouters()->values(),
            'selected_router_id' => $router->id,
            ...$this->formSelectOptions($router),
        ]);
    }

    public function update(Request $request, MikrotikRouter $router, string $profile): RedirectResponse
    {
        $validated = $this->validateProfile($request, requireRouter: false);
        $result = $this->api->updatePppProfile($router, $profile, $validated);

        if (! $result['ok']) {
            return back()->withInput()->with('error', $result['message']);
        }

        return redirect()
            ->route('admin.customers.pppoe.mikrotik-profiles', ['router_id' => $router->id])
            ->with('success', $result['message']);
    }

    public function destroy(MikrotikRouter $router, string $profile): RedirectResponse
    {
        $existing = $this->api->getPppProfile($router, $profile);
        $name = strtolower($existing['profile']['name'] ?? '');

        if (in_array($name, ['default', 'default-encryption'], true)) {
            return back()->with('error', 'Profile bawaan RouterOS tidak boleh dihapus.');
        }

        $result = $this->api->removePppProfile($router, $profile);

        return redirect()
            ->route('admin.customers.pppoe.mikrotik-profiles', ['router_id' => $router->id])
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    private function activeRouters()
    {
        return MikrotikRouter::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'host', 'port']);
    }

    /**
     * @return array{ip_pools: array<int, array{name: string, ranges: ?string}>, queue_types: array<int, array{name: string, kind: ?string}>}
     */
    private function formSelectOptions(MikrotikRouter $router): array
    {
        return [
            'ip_pools' => $this->api->listIpPools($router)['pools'] ?? [],
            'queue_types' => $this->api->listQueueTypes($router)['queue_types'] ?? [],
        ];
    }

    private function validateProfile(Request $request, bool $requireRouter = true): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'rate_limit' => ['nullable', 'string', 'max:120'],
            'local_address' => ['nullable', 'string', 'max:120'],
            'remote_address' => ['nullable', 'string', 'max:120'],
            'queue_type_rx' => ['nullable', 'string', 'max:120'],
            'queue_type_tx' => ['nullable', 'string', 'max:120'],
            'only_one' => ['nullable', 'in:default,yes,no'],
            'dns_server' => ['nullable', 'string', 'max:120'],
            'comment' => ['nullable', 'string', 'max:255'],
        ];

        if ($requireRouter) {
            $rules['router_id'] = ['required', 'exists:mikrotik_routers,id'];
        }

        $validated = $request->validate($rules);

        if (($validated['only_one'] ?? null) === 'default') {
            $validated['only_one'] = null;
        }

        return $validated;
    }
}
