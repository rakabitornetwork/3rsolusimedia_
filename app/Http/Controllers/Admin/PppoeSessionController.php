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

        if ($search !== '') {
            $needle = strtolower($search);
            $sessions = $sessions->filter(function (array $session) use ($needle) {
                return str_contains(strtolower((string) ($session['name'] ?? '')), $needle)
                    || str_contains(strtolower((string) ($session['customer_name'] ?? '')), $needle)
                    || str_contains(strtolower((string) ($session['address'] ?? '')), $needle)
                    || str_contains(strtolower((string) ($session['caller_id'] ?? '')), $needle);
            });
        }

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
            'sessions' => $sessions->values()->all(),
            'filters' => [
                'q' => $search,
            ],
            'stats' => $stats,
            'error' => $error,
            'packages' => SubscriptionPackage::query()
                ->where('is_active', true)
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
