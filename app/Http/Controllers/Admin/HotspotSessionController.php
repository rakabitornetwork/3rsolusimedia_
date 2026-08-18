<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MikrotikRouter;
use App\Services\MikrotikApiService;
use App\Support\AdminListState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HotspotSessionController extends Controller
{
    public function __construct(private readonly MikrotikApiService $api)
    {
    }

    public function index(Request $request): Response
    {
        AdminListState::apply($request, AdminListState::HOTSPOT_SESSIONS, [
            'router_id', 'q',
        ], preferLastRouter: true);

        $routers = $this->activeRouters();
        $selectedRouterId = $request->integer('router_id') ?: $routers->first()?->id;
        $search = trim((string) $request->get('q', ''));

        $sessions = collect();
        $error = null;

        if ($selectedRouterId) {
            $router = MikrotikRouter::query()->find($selectedRouterId);

            if (! $router) {
                $error = 'Router tidak ditemukan.';
            } else {
                $result = $this->api->listHotspotActiveSessions($router);

                if (! $result['ok']) {
                    $error = $result['message'] ?? 'Gagal mengambil sesi hotspot aktif.';
                } else {
                    $sessions = collect($result['sessions'] ?? []);
                }
            }
        } else {
            $error = 'Belum ada router aktif. Tambahkan router terlebih dahulu.';
        }

        $stats = [
            'online' => $sessions->count(),
            'matched' => $sessions->where('user_registered', true)->count(),
            'unknown' => $sessions->where('user_registered', false)->count(),
        ];

        return Inertia::render('Admin/Network/Hotspot/Sessions', [
            'routers' => $routers->values(),
            'selected_router_id' => $selectedRouterId,
            'sessions' => $sessions->values()->all(),
            'filters' => [
                'q' => $search,
            ],
            'stats' => $stats,
            'error' => $error,
        ]);
    }

    public function disconnect(MikrotikRouter $router, string $session): RedirectResponse
    {
        $result = $this->api->disconnectHotspotActiveSession($router, $session);

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
