<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MikrotikRouter;
use App\Services\MikrotikApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HotspotVoucherController extends Controller
{
    public function __construct(private readonly MikrotikApiService $api)
    {
    }

    public function index(Request $request): Response
    {
        $routers = $this->activeRouters();
        $routerId = (int) $request->query('router_id', $routers->first()?->id ?? 0);
        $selected = $routers->firstWhere('id', $routerId) ?? $routers->first();

        $users = [];
        $activeCount = 0;
        $error = null;
        $profiles = [];

        if ($selected) {
            $router = MikrotikRouter::query()->find($selected->id);
            $result = $this->api->listHotspotUsers($router);

            if ($result['ok']) {
                $users = $result['users'] ?? [];
                $activeCount = (int) ($result['active_count'] ?? 0);
            } else {
                $error = $result['message'] ?? 'Gagal mengambil voucher dari RouterOS.';
            }

            $profiles = $this->api->listHotspotUserProfiles($router)['profiles'] ?? [];
        }

        $q = trim((string) $request->get('q', ''));
        $profileFilter = (string) $request->get('profile', '');
        $statusFilter = (string) $request->get('status', '');

        $filtered = collect($users)
            ->when($q !== '', function ($collection) use ($q) {
                $needle = mb_strtolower($q);

                return $collection->filter(function (array $user) use ($needle) {
                    return str_contains(mb_strtolower($user['name']), $needle)
                        || str_contains(mb_strtolower((string) ($user['comment'] ?? '')), $needle);
                });
            })
            ->when($profileFilter !== '', fn ($collection) => $collection->where('profile', $profileFilter))
            ->when($statusFilter === 'online', fn ($collection) => $collection->where('is_online', true))
            ->when($statusFilter === 'disabled', fn ($collection) => $collection->where('disabled', true))
            ->when($statusFilter === 'active', fn ($collection) => $collection->where('disabled', false))
            ->values();

        return Inertia::render('Admin/Network/Hotspot/Index', [
            'routers' => $routers->values(),
            'selected_router_id' => $selected?->id,
            'users' => $filtered,
            'profiles' => $profiles,
            'error' => $error,
            'filters' => [
                'q' => $q,
                'profile' => $profileFilter,
                'status' => $statusFilter,
                'router_id' => $selected?->id,
            ],
            'stats' => [
                'total' => count($users),
                'online' => $activeCount,
                'disabled' => collect($users)->where('disabled', true)->count(),
                'shown' => $filtered->count(),
            ],
            'generated_vouchers' => session('generated_vouchers', []),
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $routers = $this->activeRouters();

        if ($routers->isEmpty()) {
            return redirect()
                ->route('admin.network.hotspot')
                ->with('error', 'Tambahkan router MikroTik aktif terlebih dahulu.');
        }

        $routerId = (int) $request->query('router_id', $routers->first()->id);
        $selected = $routers->firstWhere('id', $routerId) ?? $routers->first();
        $router = MikrotikRouter::query()->findOrFail($selected->id);

        return Inertia::render('Admin/Network/Hotspot/Generate', [
            'routers' => $routers->values(),
            'selected_router_id' => $router->id,
            'profiles' => $this->api->listHotspotUserProfiles($router)['profiles'] ?? [],
            'servers' => $this->api->listHotspotServers($router)['servers'] ?? [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'router_id' => ['required', 'exists:mikrotik_routers,id'],
            'profile' => ['required', 'string', 'max:120'],
            'server' => ['nullable', 'string', 'max:120'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'prefix' => ['nullable', 'string', 'max:20'],
            'code_length' => ['required', 'integer', 'min:4', 'max:12'],
            'password_mode' => ['required', Rule::in(['same', 'random'])],
            'limit_uptime' => ['nullable', 'string', 'max:40'],
            'limit_bytes_mb' => ['nullable', 'integer', 'min:1', 'max:1048576'],
            'comment' => ['nullable', 'string', 'max:255'],
        ]);

        $router = MikrotikRouter::query()->findOrFail($validated['router_id']);
        $bytes = isset($validated['limit_bytes_mb'])
            ? ((int) $validated['limit_bytes_mb']) * 1024 * 1024
            : null;

        $result = $this->api->createHotspotVoucherBatch($router, [
            'quantity' => $validated['quantity'],
            'prefix' => $validated['prefix'] ?? 'VC',
            'code_length' => $validated['code_length'],
            'password_mode' => $validated['password_mode'],
            'profile' => $validated['profile'],
            'server' => $validated['server'] ?? null,
            'limit_uptime' => $validated['limit_uptime'] ?? null,
            'limit_bytes_total' => $bytes,
            'comment' => $validated['comment'] ?? 'voucher-app',
        ]);

        if (! $result['ok']) {
            return back()->withInput()->with('error', $result['message']);
        }

        return redirect()
            ->route('admin.network.hotspot', ['router_id' => $router->id])
            ->with('success', $result['message'])
            ->with('generated_vouchers', $result['vouchers'] ?? []);
    }

    public function destroy(MikrotikRouter $router, string $user): RedirectResponse
    {
        $result = $this->api->removeHotspotUser($router, $user);

        return redirect()
            ->route('admin.network.hotspot', ['router_id' => $router->id])
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function toggle(Request $request, MikrotikRouter $router, string $user): RedirectResponse
    {
        $validated = $request->validate([
            'disabled' => ['required', 'boolean'],
        ]);

        $result = $this->api->setHotspotUserDisabled(
            $router,
            $user,
            $request->boolean('disabled'),
        );

        return redirect()
            ->route('admin.network.hotspot', ['router_id' => $router->id])
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    private function activeRouters()
    {
        return MikrotikRouter::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'host', 'port']);
    }
}
