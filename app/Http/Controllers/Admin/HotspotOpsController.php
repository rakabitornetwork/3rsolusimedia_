<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MikrotikRouter;
use App\Services\MikrotikApiService;
use App\Support\AdminListState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HotspotOpsController extends Controller
{
    public const TABS = ['hosts', 'cookies', 'bindings', 'log'];

    public function __construct(private readonly MikrotikApiService $api) {}

    public function index(Request $request): Response
    {
        AdminListState::apply($request, AdminListState::HOTSPOT_TOOLS, [
            'router_id', 'tab', 'host_filter',
        ], preferLastRouter: true);

        $tab = (string) $request->query('tab', 'hosts');
        if (! in_array($tab, self::TABS, true)) {
            $tab = 'hosts';
        }

        $hostFilter = (string) $request->query('host_filter', 'all');
        if (! in_array($hostFilter, ['all', 'authorized', 'bypassed'], true)) {
            $hostFilter = 'all';
        }

        $routers = $this->activeRouters();
        $selectedRouterId = $request->integer('router_id') ?: $routers->first()?->id;
        $error = null;
        $hosts = [];
        $cookies = [];
        $bindings = [];
        $logs = [];
        $servers = [];

        if (! $selectedRouterId) {
            $error = 'Belum ada router aktif. Tambahkan router terlebih dahulu.';
        } else {
            $router = MikrotikRouter::query()->find($selectedRouterId);
            if (! $router) {
                $error = 'Router tidak ditemukan.';
            } else {
                $result = match ($tab) {
                    'cookies' => $this->api->listHotspotCookies($router),
                    'bindings' => $this->api->listHotspotIpBindings($router),
                    'log' => $this->api->listHotspotLogs($router),
                    default => $this->api->listHotspotHosts($router),
                };

                if (! ($result['ok'] ?? false)) {
                    $error = $result['message'] ?? 'Gagal mengambil data hotspot dari RouterOS.';
                }

                $hosts = $result['hosts'] ?? [];
                $cookies = $result['cookies'] ?? [];
                $bindings = $result['bindings'] ?? [];
                $logs = $result['logs'] ?? [];

                if ($tab === 'bindings' || $tab === 'hosts') {
                    $servers = $this->api->listHotspotServers($router)['servers'] ?? [];
                }
            }
        }

        if ($tab === 'hosts' && $hostFilter !== 'all') {
            $hosts = collect($hosts)
                ->filter(function (array $host) use ($hostFilter) {
                    return $hostFilter === 'authorized'
                        ? ($host['authorized'] ?? false)
                        : ($host['bypassed'] ?? false);
                })
                ->values()
                ->all();
        }

        return Inertia::render('Admin/Network/Hotspot/Tools', [
            'routers' => $routers->values(),
            'selected_router_id' => $selectedRouterId,
            'tab' => $tab,
            'host_filter' => $hostFilter,
            'hosts' => $hosts,
            'cookies' => $cookies,
            'bindings' => $bindings,
            'logs' => $logs,
            'servers' => $servers,
            'error' => $error,
        ]);
    }

    public function destroyHost(MikrotikRouter $router, string $id): RedirectResponse
    {
        $result = $this->api->removeHotspotHost($router, $id);

        return $this->backToTools($router->id, 'hosts', $result);
    }

    public function bindHost(Request $request, MikrotikRouter $router, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::in(['regular', 'bypassed', 'blocked'])],
        ]);

        $listed = $this->api->listHotspotHosts($router);
        $host = collect($listed['hosts'] ?? [])->first(
            fn (array $row) => (string) ($row['id'] ?? '') === $id
        );

        if (! $host) {
            return $this->backToTools($router->id, 'hosts', [
                'ok' => false,
                'message' => 'Host hotspot tidak ditemukan di RouterOS.',
            ]);
        }

        $result = $this->api->addHotspotIpBinding($router, [
            'mac_address' => $host['mac_address'] ?? null,
            'address' => $host['address'] ?? null,
            'to_address' => $host['to_address'] ?? null,
            'server' => $host['server'] ?? null,
            'type' => $validated['type'] ?? 'bypassed',
            'comment' => $host['comment'] ?? null,
        ]);

        return $this->backToTools($router->id, $result['ok'] ? 'bindings' : 'hosts', $result);
    }

    public function destroyCookie(MikrotikRouter $router, string $id): RedirectResponse
    {
        $result = $this->api->removeHotspotCookie($router, $id);

        return $this->backToTools($router->id, 'cookies', $result);
    }

    public function storeBinding(Request $request, MikrotikRouter $router): RedirectResponse
    {
        $validated = $request->validate([
            'mac_address' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:64'],
            'to_address' => ['nullable', 'string', 'max:64'],
            'server' => ['nullable', 'string', 'max:120'],
            'type' => ['required', Rule::in(['regular', 'bypassed', 'blocked'])],
            'comment' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->api->addHotspotIpBinding($router, $validated);

        return $this->backToTools($router->id, 'bindings', $result);
    }

    public function toggleBinding(Request $request, MikrotikRouter $router, string $id): RedirectResponse
    {
        $request->validate([
            'disabled' => ['required', 'boolean'],
        ]);

        $result = $this->api->setHotspotIpBindingDisabled(
            $router,
            $id,
            $request->boolean('disabled'),
        );

        return $this->backToTools($router->id, 'bindings', $result);
    }

    public function destroyBinding(MikrotikRouter $router, string $id): RedirectResponse
    {
        $result = $this->api->removeHotspotIpBinding($router, $id);

        return $this->backToTools($router->id, 'bindings', $result);
    }

    /**
     * @param  array{ok: bool, message?: string}  $result
     */
    private function backToTools(int $routerId, string $tab, array $result): RedirectResponse
    {
        return AdminListState::to('admin.network.hotspot.tools', AdminListState::HOTSPOT_TOOLS, [
            'router_id' => $routerId,
            'tab' => $tab,
        ])->with($result['ok'] ? 'success' : 'error', $result['message'] ?? ($result['ok'] ? 'Berhasil.' : 'Gagal.'));
    }

    private function activeRouters()
    {
        return MikrotikRouter::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'host']);
    }
}
