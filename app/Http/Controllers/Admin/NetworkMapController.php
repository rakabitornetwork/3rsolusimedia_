<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Services\GenieAcsService;
use App\Services\MikrotikApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NetworkMapController extends Controller
{
    public function __construct(
        private readonly GenieAcsService $genie,
        private readonly MikrotikApiService $api,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $search = trim((string) $request->get('q', ''));
        $status = (string) $request->get('status', '');

        $query = PppoeCustomer::query()
            ->with(['router:id,name,host', 'package:id,name'])
            ->orderBy('name');

        if ($user?->isAgen()) {
            $query->where('agent_id', $user->id);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($status !== '' && $status !== 'all') {
            if ($status === 'grace') {
                $query->whereNotNull('grace_until')
                    ->whereDate('grace_until', '>=', now()->toDateString());
            } elseif ($status === 'overdue') {
                $query->whereDate('due_date', '<', now()->toDateString())
                    ->where('status', '!=', 'isolated');
            } else {
                $query->where('status', $status);
            }
        }

        $customers = $query->get();

        $opticalIndex = [];
        $configured = $this->genie->isConfigured();
        $opticalMeta = [
            'enabled' => $configured,
            'ok' => false,
            'message' => null,
            'matched' => 0,
            'total' => 0,
        ];

        // Samakan perilaku halaman GenieACS: cukup URL NBI terisi (flag enabled sering terlewat).
        if ($configured) {
            $opticalResult = $this->genie->opticalIndexByPppoeUsername();
            $opticalMeta['ok'] = (bool) ($opticalResult['ok'] ?? false);
            $opticalMeta['message'] = $opticalResult['message'] ?? null;
            $opticalMeta['matched'] = (int) ($opticalResult['matched'] ?? 0);
            $opticalMeta['total'] = (int) ($opticalResult['total'] ?? 0);
            $opticalIndex = $opticalResult['index'] ?? [];
        } else {
            $opticalMeta['message'] = 'URL NBI GenieACS belum dikonfigurasi.';
        }

        $onlineByRouter = $this->activeSessionUsernamesByRouter(
            $customers->pluck('mikrotik_router_id')->filter()->unique()->values()->all()
        );

        $items = $customers->map(function (PppoeCustomer $customer) use ($opticalIndex, $onlineByRouter) {
            $lat = $customer->latitude;
            $lng = $customer->longitude;
            $onMap = $lat !== null && $lng !== null
                && is_numeric($lat) && is_numeric($lng);

            $usernameKey = strtolower(trim((string) $customer->username));
            $optical = $opticalIndex[$usernameKey] ?? null;

            $routerId = (int) ($customer->mikrotik_router_id ?? 0);
            $sessionOnline = $routerId > 0
                && isset($onlineByRouter[$routerId][$usernameKey]);

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'username' => $customer->username,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'status' => $customer->status,
                'is_active' => (bool) $customer->is_active,
                'is_overdue' => $customer->isOverdue(),
                'due_date' => $customer->due_date?->format('Y-m-d'),
                'latitude' => $onMap ? (float) $lat : null,
                'longitude' => $onMap ? (float) $lng : null,
                'on_map' => $onMap,
                'package' => $customer->package?->only(['id', 'name']),
                'router' => $customer->router?->only(['id', 'name', 'host']),
                'mikrotik_router_id' => $customer->mikrotik_router_id,
                'session_online' => $sessionOnline,
                'optical' => $optical,
            ];
        })->values()->all();

        $onMapCount = collect($items)->where('on_map', true)->count();
        $opticalMatched = collect($items)->filter(fn (array $item) => ($item['optical']['matched'] ?? false))->count();
        $sessionOnlineCount = collect($items)->where('session_online', true)->count();

        return Inertia::render('Admin/Network/Map', [
            'filters' => [
                'q' => $search,
                'status' => $status !== '' ? $status : 'all',
            ],
            'customers' => $items,
            'optical_meta' => $opticalMeta,
            'stats' => [
                'total' => count($items),
                'on_map' => $onMapCount,
                'without_gps' => count($items) - $onMapCount,
                'optical_matched' => $opticalMatched,
                'session_online' => $sessionOnlineCount,
            ],
        ]);
    }

    public function customerTraffic(Request $request, PppoeCustomer $pppoe): JsonResponse
    {
        $user = $request->user();
        if ($user?->isAgen() && (int) $pppoe->agent_id !== (int) $user->id) {
            abort(403);
        }

        $router = $pppoe->router;
        if (! $router) {
            return response()->json([
                'ok' => false,
                'online' => false,
                'message' => 'Router pelanggan tidak ditemukan.',
            ]);
        }

        $result = $this->api->monitorPppoeUserTraffic($router, (string) $pppoe->username);

        return response()->json($result);
    }

    public function customerReboot(Request $request, PppoeCustomer $pppoe): RedirectResponse
    {
        $user = $request->user();
        if ($user?->isAgen() && (int) $pppoe->agent_id !== (int) $user->id) {
            abort(403);
        }

        if (! $this->genie->isConfigured()) {
            return back()->with('error', 'URL NBI GenieACS belum dikonfigurasi.');
        }

        $validated = $request->validate([
            'device_id' => ['required', 'string', 'max:255'],
        ]);

        $deviceId = trim($validated['device_id']);
        $deviceResult = $this->genie->getDevice($deviceId);

        if (! ($deviceResult['ok'] ?? false)) {
            return back()->with(
                'error',
                $deviceResult['message'] ?? 'Perangkat GenieACS tidak ditemukan.'
            );
        }

        $deviceUsername = strtolower(trim((string) ($deviceResult['device']['pppoe_username'] ?? '')));
        $customerUsername = strtolower(trim((string) $pppoe->username));

        if ($deviceUsername === '' || $deviceUsername !== $customerUsername) {
            return back()->with(
                'error',
                'Perangkat GenieACS tidak cocok dengan username PPPoE pelanggan ini.'
            );
        }

        $result = $this->genie->rebootDevice($deviceId);

        return back()->with(
            $result['ok'] ? 'success' : 'error',
            $result['message']
        );
    }

    /**
     * @param  array<int, int|string>  $routerIds
     * @return array<int, array<string, true>>
     */
    private function activeSessionUsernamesByRouter(array $routerIds): array
    {
        $map = [];

        if ($routerIds === []) {
            return $map;
        }

        $routers = MikrotikRouter::query()
            ->whereIn('id', $routerIds)
            ->get()
            ->keyBy('id');

        foreach ($routerIds as $routerId) {
            $router = $routers->get($routerId);
            if (! $router) {
                continue;
            }

            $result = $this->api->listPppActiveSessions($router);
            if (! ($result['ok'] ?? false)) {
                continue;
            }

            $usernames = [];
            foreach ($result['sessions'] ?? [] as $session) {
                $name = strtolower(trim((string) ($session['name'] ?? '')));
                if ($name !== '') {
                    $usernames[$name] = true;
                }
            }

            $map[(int) $routerId] = $usernames;
        }

        return $map;
    }
}
