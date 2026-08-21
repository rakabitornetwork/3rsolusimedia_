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

class HotspotProfileController extends Controller
{
    public function __construct(private readonly MikrotikApiService $api)
    {
    }

    public function index(Request $request): Response
    {
        AdminListState::apply($request, AdminListState::HOTSPOT_PROFILES, [
            'router_id',
        ], preferLastRouter: true);

        $routers = $this->activeRouters();
        $routerId = (int) $request->query('router_id', $routers->first()?->id ?? 0);
        $selected = $routers->firstWhere('id', $routerId) ?? $routers->first();

        $profiles = [];
        $error = null;

        if ($selected) {
            $router = MikrotikRouter::query()->find($selected->id);
            $result = $this->api->listHotspotUserProfiles($router);

            if ($result['ok']) {
                $profiles = $result['profiles'] ?? [];
            } else {
                $error = $result['message'] ?? 'Gagal mengambil profile hotspot dari RouterOS.';
            }
        }

        return Inertia::render('Admin/Network/Hotspot/Profiles/Index', [
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
            return AdminListState::to('admin.network.hotspot.profiles', AdminListState::HOTSPOT_PROFILES)
                ->with('error', 'Tambahkan router MikroTik aktif terlebih dahulu.');
        }

        $routerId = (int) $request->query(
            'router_id',
            AdminListState::lastRouterId($request) ?? $routers->first()->id
        );
        $selected = $routers->firstWhere('id', $routerId) ?? $routers->first();
        $router = MikrotikRouter::query()->findOrFail($selected->id);

        return Inertia::render('Admin/Network/Hotspot/Profiles/Form', [
            'profile' => null,
            'routers' => $routers->values(),
            'selected_router_id' => $selected->id,
            'parent_queues' => $this->api->listSimpleQueues($router)['queues'] ?? [],
            'address_pools' => $this->api->listIpPools($router)['pools'] ?? [],
            'expired_modes' => $this->expiredModes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProfile($request);
        $router = MikrotikRouter::query()->findOrFail($validated['router_id']);
        $result = $this->api->createHotspotUserProfile($router, $validated);

        if (! $result['ok']) {
            return back()->withInput()->with('error', $result['message']);
        }

        return AdminListState::to('admin.network.hotspot.profiles', AdminListState::HOTSPOT_PROFILES, [
            'router_id' => $router->id,
        ])->with('success', $result['message']);
    }

    public function edit(MikrotikRouter $router, string $profile): Response|RedirectResponse
    {
        $result = $this->api->getHotspotUserProfile($router, $profile);

        if (! $result['ok']) {
            return AdminListState::to('admin.network.hotspot.profiles', AdminListState::HOTSPOT_PROFILES, [
                'router_id' => $router->id,
            ])->with('error', $result['message'] ?? 'Profile tidak ditemukan.');
        }

        return Inertia::render('Admin/Network/Hotspot/Profiles/Form', [
            'profile' => $result['profile'],
            'routers' => $this->activeRouters()->values(),
            'selected_router_id' => $router->id,
            'parent_queues' => $this->api->listSimpleQueues($router)['queues'] ?? [],
            'address_pools' => $this->api->listIpPools($router)['pools'] ?? [],
            'expired_modes' => $this->expiredModes(),
        ]);
    }

    public function update(Request $request, MikrotikRouter $router, string $profile): RedirectResponse
    {
        $validated = $this->validateProfile($request, requireRouter: false);
        $result = $this->api->updateHotspotUserProfile($router, $profile, $validated);

        if (! $result['ok']) {
            return back()->withInput()->with('error', $result['message']);
        }

        return AdminListState::to('admin.network.hotspot.profiles', AdminListState::HOTSPOT_PROFILES, [
            'router_id' => $router->id,
        ])->with('success', $result['message']);
    }

    public function destroy(MikrotikRouter $router, string $profile): RedirectResponse
    {
        $existing = $this->api->getHotspotUserProfile($router, $profile);
        $name = strtolower($existing['profile']['name'] ?? '');

        if ($name === 'default') {
            return back()->with('error', 'Profile bawaan "default" tidak boleh dihapus.');
        }

        $result = $this->api->removeHotspotUserProfile($router, $profile);

        return AdminListState::to('admin.network.hotspot.profiles', AdminListState::HOTSPOT_PROFILES, [
            'router_id' => $router->id,
        ])->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    private function activeRouters()
    {
        return MikrotikRouter::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'host', 'port']);
    }

    private function validateProfile(Request $request, bool $requireRouter = true): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'rate_limit' => ['nullable', 'string', 'max:120'],
            'session_timeout' => ['nullable', 'string', 'max:40'],
            'idle_timeout' => ['nullable', 'string', 'max:40'],
            'shared_users' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'address_list' => ['nullable', 'string', 'max:120'],
            'address_pool' => ['nullable', 'string', 'max:120'],
            'expired_mode' => ['nullable', 'string', Rule::in([
                'rem', 'ntf', 'remc', 'ntfc',
                'remove', 'notice', 'remove,notice',
            ])],
            'validity' => [
                Rule::requiredIf(fn () => filled($request->input('expired_mode'))),
                'nullable',
                'string',
                'max:40',
                'regex:/^(?:[1-9]\d*[wdhms])+$/i',
            ],
            'price' => ['nullable', 'integer', 'min:0'],
            'selling_price' => ['nullable', 'integer', 'min:0'],
            'lock_user' => ['nullable', 'boolean'],
            'parent_queue' => ['nullable', 'string', 'max:120'],
        ];

        if ($requireRouter) {
            $rules['router_id'] = ['required', 'exists:mikrotik_routers,id'];
        }

        $validated = $request->validate($rules, [
            'validity.required' => 'Validity wajib diisi jika expired mode aktif. Contoh: 1d, 7d, 12h, 5h30m.',
            'validity.regex' => 'Gunakan format RouterOS: 30d, 12h, 5h30m, 4w3d.',
        ]);
        $validated['lock_user'] = $request->boolean('lock_user');
        $validated['parent_queue'] = trim((string) ($validated['parent_queue'] ?? '')) ?: null;
        $mode = $this->api->normalizeHotspotExpiredMode($validated['expired_mode'] ?? null);
        $validated['expired_mode'] = $mode !== '' ? $mode : null;
        $validated['rate_limit'] = trim((string) ($validated['rate_limit'] ?? '')) ?: null;
        $validated['session_timeout'] = trim((string) ($validated['session_timeout'] ?? '')) ?: null;
        $validated['idle_timeout'] = trim((string) ($validated['idle_timeout'] ?? '')) ?: null;
        $validated['address_list'] = trim((string) ($validated['address_list'] ?? '')) ?: null;
        $validated['address_pool'] = trim((string) ($validated['address_pool'] ?? '')) ?: null;
        $validity = strtolower(trim((string) ($validated['validity'] ?? '')));
        $validated['validity'] = in_array($validity, ['', '0', '0s', 'none'], true) ? null : $validity;
        $validated['price'] = max(0, (int) ($validated['price'] ?? 0));
        $validated['selling_price'] = max(0, (int) ($validated['selling_price'] ?? 0));

        return $validated;
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    private function expiredModes(): array
    {
        return [
            [
                'value' => '',
                'label' => 'Tidak ada',
                'description' => 'Tidak memasang skrip expire. Session timeout RouterOS tetap berlaku terpisah',
            ],
            [
                'value' => 'rem',
                'label' => 'Remove',
                'description' => 'Hapus user di RouterOS setelah validity habis',
            ],
            [
                'value' => 'ntf',
                'label' => 'Notice',
                'description' => 'User tetap ada, limit-uptime diset 1s setelah expired',
            ],
            [
                'value' => 'remc',
                'label' => 'Remove & Record',
                'description' => 'Hapus user setelah expired dan catat penjualan di /system/script (Mikhmon)',
            ],
            [
                'value' => 'ntfc',
                'label' => 'Notice & Record',
                'description' => 'Notice (1s) setelah expired dan catat penjualan di /system/script (Mikhmon)',
            ],
        ];
    }
}
