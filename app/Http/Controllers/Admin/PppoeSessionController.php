<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SubscriptionPackage;
use App\Services\MikrotikApiService;
use App\Support\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class PppoeSessionController extends Controller
{
    public function __construct(private readonly MikrotikApiService $api)
    {
    }

    public function index(Request $request): Response
    {
        $routers = $this->activeRouters();
        $selectedRouterId = $request->integer('router_id') ?: $routers->first()?->id;
        $search = trim((string) $request->get('q', ''));
        $onlyUnknown = $request->boolean('only_unknown');
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = (int) $request->integer('per_page', 25);
        if (! in_array($perPage, [25, 50, 100, 200, 500], true)) {
            $perPage = 25;
        }

        $sessions = [];
        $error = null;

        if ($selectedRouterId) {
            $router = MikrotikRouter::query()->find($selectedRouterId);

            if (! $router) {
                $error = 'Router tidak ditemukan.';
            } else {
                $result = $this->api->listPppActiveSessions($router);

                if (! $result['ok']) {
                    $error = $result['message'] ?? 'Gagal mengambil sesi aktif.';
                } else {
                    $sessions = $result['sessions'] ?? [];
                }
            }
        } else {
            $error = 'Belum ada router aktif. Tambahkan router terlebih dahulu.';
        }

        $user = $request->user();
        $customerQuery = PppoeCustomer::query()
            ->when($selectedRouterId, fn ($q) => $q->where('mikrotik_router_id', $selectedRouterId));

        if ($user->isAgen()) {
            $customerQuery->where('agent_id', $user->id);
        }

        $customerMap = $customerQuery
            ->get(['id', 'name', 'username', 'status', 'service_profile'])
            ->keyBy(fn (PppoeCustomer $customer) => strtolower($customer->username));

        $sessions = collect($sessions)
            ->map(function (array $session) use ($customerMap) {
                $username = strtolower((string) ($session['name'] ?? ''));
                $customer = $customerMap->get($username);

                return [
                    ...$session,
                    'customer_id' => $customer?->id,
                    'customer_name' => $customer?->name,
                    'customer_status' => $customer?->status,
                    'service_profile' => $customer?->service_profile,
                ];
            });

        if ($user->isAgen()) {
            $sessions = $sessions->filter(fn (array $session) => ! empty($session['customer_id']));
        }

        $stats = [
            'online' => $sessions->count(),
            'matched' => $sessions->whereNotNull('customer_id')->count(),
            'unknown' => $sessions->whereNull('customer_id')->count(),
        ];

        $unknownUsernames = $sessions
            ->filter(fn (array $session) => empty($session['customer_id']))
            ->pluck('name')
            ->filter()
            ->values()
            ->all();

        if ($search !== '') {
            $needle = strtolower($search);
            $sessions = $sessions->filter(function (array $session) use ($needle) {
                return str_contains(strtolower((string) ($session['name'] ?? '')), $needle)
                    || str_contains(strtolower((string) ($session['customer_name'] ?? '')), $needle)
                    || str_contains(strtolower((string) ($session['address'] ?? '')), $needle)
                    || str_contains(strtolower((string) ($session['caller_id'] ?? '')), $needle);
            });
        }

        if ($onlyUnknown) {
            $sessions = $sessions->filter(fn (array $session) => empty($session['customer_id']));
        }

        $sessions = $sessions->values();
        $totalFiltered = $sessions->count();
        $pageItems = $sessions->forPage($page, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $pageItems,
            $totalFiltered,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $isolirProfiles = [];
        if ($selectedRouterId) {
            $router = MikrotikRouter::query()->find($selectedRouterId);
            if ($router) {
                $profilesResult = $this->api->listPppProfiles($router);
                $isolirProfiles = $profilesResult['isolir_profiles'] ?? [];
            }
        }

        return Inertia::render('Admin/Customers/Pppoe/Sessions', [
            'routers' => $routers->values(),
            'selected_router_id' => $selectedRouterId,
            'sessions' => $paginator,
            'unknown_usernames' => $unknownUsernames,
            'filters' => [
                'q' => $search,
                'only_unknown' => $onlyUnknown,
                'per_page' => $perPage,
                'router_id' => $selectedRouterId,
            ],
            'stats' => $stats,
            'error' => $error,
            'packages' => SubscriptionPackage::query()
                ->where('is_active', true)
                ->when(
                    $selectedRouterId,
                    fn ($query) => $query->where('mikrotik_router_id', $selectedRouterId)
                )
                ->orderBy('sort_order')
                ->orderBy('price')
                ->get()
                ->map(fn (SubscriptionPackage $package) => $package->toOptionArray())
                ->values(),
            'isolir_profiles' => $isolirProfiles,
            'defaults' => [
                'billing_day' => AppSettings::int('app_default_billing_day', 1),
                'start_date' => now()->toDateString(),
                'overdue_action' => 'isolir',
            ],
        ]);
    }

    public function disconnect(MikrotikRouter $router, string $session): RedirectResponse
    {
        $result = $this->api->disconnectPppActiveSession($router, $session);

        return back()->with(
            $result['ok'] ? 'success' : 'error',
            $result['message'] ?? ($result['ok'] ? 'Sesi diputus.' : 'Gagal memutus sesi.')
        );
    }

    private function activeRouters()
    {
        return MikrotikRouter::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'host']);
    }
}
