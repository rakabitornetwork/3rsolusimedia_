<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HotspotVoucher;
use App\Models\MikrotikRouter;
use App\Models\User;
use App\Services\HotspotVoucherService;
use App\Services\MikrotikApiService;
use App\Support\AdminListState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HotspotVoucherController extends Controller
{
    public function __construct(
        private readonly MikrotikApiService $api,
        private readonly HotspotVoucherService $vouchers,
    ) {}

    public function index(Request $request): Response
    {
        AdminListState::apply($request, AdminListState::HOTSPOT, [
            'router_id', 'q', 'profile', 'comment', 'status', 'per_page', 'page',
        ], preferLastRouter: true);

        $routers = $this->activeRouters();
        $routerId = (int) $request->query('router_id', $routers->first()?->id ?? 0);
        $selected = $routers->firstWhere('id', $routerId) ?? $routers->first();

        $users = [];
        $activeCount = 0;
        $error = null;
        $profiles = [];
        $purgedMessage = null;

        if ($selected) {
            $router = MikrotikRouter::query()->find($selected->id);

            // Otomatis bersihkan voucher terpakai dari RouterOS + app.
            $purge = $this->vouchers->purgeUsed($router);
            if (($purge['removed'] ?? 0) > 0) {
                $purgedMessage = $purge['message'];
            }

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
        $commentFilter = trim((string) $request->get('comment', ''));

        $metaByName = $selected
            ? HotspotVoucher::query()
                ->where('mikrotik_router_id', $selected->id)
                ->whereIn('username', collect($users)->pluck('name')->filter()->all())
                ->get()
                ->keyBy('username')
            : collect();

        $enriched = collect($users)
            ->map(function (array $user) use ($metaByName) {
                /** @var HotspotVoucher|null $meta */
                $meta = $metaByName->get($user['name'] ?? '');

                return [
                    ...$user,
                    'voucher_id' => $meta?->id,
                    'batch_id' => $meta?->batch_id,
                    'agent_name' => $meta?->agent_name,
                    'base_price' => $meta?->base_price,
                    'commission' => $meta?->commission,
                    'sell_price' => $meta?->sell_price,
                    'sell_price_label' => $meta
                        ? 'Rp '.number_format((int) $meta->sell_price, 0, ',', '.')
                        : null,
                    'comment' => trim((string) ($user['comment'] ?? $meta?->comment ?? '')),
                ];
            });

        $comments = $enriched
            ->pluck('comment')
            ->map(fn ($comment) => trim((string) $comment))
            ->filter(fn ($comment) => $comment !== '')
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $filtered = $enriched
            ->when($profileFilter !== '', fn ($collection) => $collection->where('profile', $profileFilter))
            ->when($commentFilter !== '', function ($collection) use ($commentFilter) {
                return $collection->filter(
                    fn (array $user) => trim((string) ($user['comment'] ?? '')) === $commentFilter
                );
            })
            ->when($statusFilter === 'online', fn ($collection) => $collection->where('is_online', true))
            ->when($statusFilter === 'disabled', fn ($collection) => $collection->where('disabled', true))
            ->when($statusFilter === 'active', fn ($collection) => $collection->where('disabled', false))
            ->values();

        $perPage = (int) $request->integer('per_page', 25);
        if (! in_array($perPage, [25, 50, 100, 200, 500], true)) {
            $perPage = 25;
        }

        $generated = session('generated_vouchers', []);
        $generatedBatchId = session('generated_batch_id');

        return Inertia::render('Admin/Network/Hotspot/Index', [
            'routers' => $routers->values(),
            'selected_router_id' => $selected?->id,
            'users' => $filtered->all(),
            'profiles' => $profiles,
            'comments' => $comments,
            'error' => $error,
            'purged_message' => $purgedMessage,
            'filters' => [
                'q' => $q,
                'profile' => $profileFilter,
                'comment' => $commentFilter,
                'status' => $statusFilter,
                'router_id' => $selected?->id,
                'per_page' => $perPage,
            ],
            'stats' => [
                'total' => count($users),
                'online' => $activeCount,
                'disabled' => collect($users)->where('disabled', true)->count(),
                'shown' => $filtered->count(),
            ],
            'generated_vouchers' => $generated,
            'generated_batch_id' => $generatedBatchId,
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $routers = $this->activeRouters();

        if ($routers->isEmpty()) {
            return AdminListState::to('admin.network.hotspot', AdminListState::HOTSPOT)
                ->with('error', 'Tambahkan router MikroTik aktif terlebih dahulu.');
        }

        $routerId = (int) $request->query(
            'router_id',
            AdminListState::lastRouterId($request) ?? $routers->first()->id
        );
        $selected = $routers->firstWhere('id', $routerId) ?? $routers->first();
        $router = MikrotikRouter::query()->findOrFail($selected->id);

        $agents = User::query()
            ->where('role', User::ROLE_AGEN)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->values();

        return Inertia::render('Admin/Network/Hotspot/Generate', [
            'routers' => $routers->values(),
            'selected_router_id' => $router->id,
            'profiles' => $this->api->listHotspotUserProfiles($router)['profiles'] ?? [],
            'servers' => $this->api->listHotspotServers($router)['servers'] ?? [],
            'agents' => $agents,
            'code_formats' => HotspotVoucherService::codeFormatOptions(),
        ]);
    }

    public function createUser(Request $request): Response|RedirectResponse
    {
        $routers = $this->activeRouters();

        if ($routers->isEmpty()) {
            return AdminListState::to('admin.network.hotspot', AdminListState::HOTSPOT)
                ->with('error', 'Tambahkan router MikroTik aktif terlebih dahulu.');
        }

        $routerId = (int) $request->query(
            'router_id',
            AdminListState::lastRouterId($request) ?? $routers->first()->id
        );
        $selected = $routers->firstWhere('id', $routerId) ?? $routers->first();
        $router = MikrotikRouter::query()->findOrFail($selected->id);

        return Inertia::render('Admin/Network/Hotspot/AddUser', [
            'routers' => $routers->values(),
            'selected_router_id' => $router->id,
            'profiles' => $this->api->listHotspotUserProfiles($router)['profiles'] ?? [],
            'servers' => $this->api->listHotspotServers($router)['servers'] ?? [],
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'router_id' => ['required', 'exists:mikrotik_routers,id'],
            'name' => ['required', 'string', 'max:80'],
            'password' => ['nullable', 'string', 'max:80'],
            'profile' => ['required', 'string', 'max:120'],
            'server' => ['nullable', 'string', 'max:120'],
            'limit_uptime' => ['nullable', 'string', 'max:40'],
            'limit_bytes_mb' => ['nullable', 'integer', 'min:1', 'max:1048576'],
            'comment' => ['nullable', 'string', 'max:240'],
        ]);

        $router = MikrotikRouter::query()->findOrFail($validated['router_id']);
        $bytes = isset($validated['limit_bytes_mb'])
            ? ((int) $validated['limit_bytes_mb']) * 1024 * 1024
            : null;

        $result = $this->vouchers->addUser($router, [
            'name' => $validated['name'],
            'password' => $validated['password'] ?? '',
            'profile' => $validated['profile'],
            'server' => $validated['server'] ?? null,
            'limit_uptime' => $validated['limit_uptime'] ?? null,
            'limit_bytes_total' => $bytes,
            'comment' => $validated['comment'] ?? '',
            'created_by' => $request->user()?->id,
        ]);

        if (! $result['ok']) {
            return back()->withInput()->with('error', $result['message']);
        }

        return AdminListState::to('admin.network.hotspot', AdminListState::HOTSPOT, [
            'router_id' => $router->id,
        ])
            ->with('success', $result['message'])
            ->with('generated_vouchers', $result['vouchers'] ?? [])
            ->with('generated_batch_id', $result['batch_id'] ?? null);
    }

    public function reset(MikrotikRouter $router, string $user): RedirectResponse
    {
        $result = $this->api->resetHotspotUser($router, $user);

        if (($result['ok'] ?? false) && ! empty($result['username'])) {
            $this->vouchers->markResetLocally($router, (string) $result['username']);
        }

        return AdminListState::to('admin.network.hotspot', AdminListState::HOTSPOT, [
            'router_id' => $router->id,
        ])->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'router_id' => ['required', 'exists:mikrotik_routers,id'],
            'profile' => ['required', 'string', 'max:120'],
            'server' => ['nullable', 'string', 'max:120'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.HotspotVoucherService::MAX_GENERATE_QUANTITY],
            'prefix' => ['nullable', 'string', 'max:20'],
            'code_length' => ['required', 'integer', 'min:4', 'max:12'],
            'code_format' => ['required', Rule::in([
                'numbers',
                'upper',
                'lower',
                'numbers_upper',
                'numbers_lower',
                'alt_numbers_upper',
                'alt_numbers_lower',
            ])],
            'password_mode' => ['required', Rule::in(['same', 'random'])],
            'limit_uptime' => ['nullable', 'string', 'max:40'],
            'limit_bytes_mb' => ['nullable', 'integer', 'min:1', 'max:1048576'],
            'comment' => ['nullable', 'string', 'max:255'],
            'agent_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', User::ROLE_AGEN)),
            ],
            'agent_name' => ['nullable', 'string', 'max:120'],
            'base_price' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'commission' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ]);

        $router = MikrotikRouter::query()->findOrFail($validated['router_id']);
        $bytes = isset($validated['limit_bytes_mb'])
            ? ((int) $validated['limit_bytes_mb']) * 1024 * 1024
            : null;

        $result = $this->vouchers->generate($router, [
            'quantity' => $validated['quantity'],
            'prefix' => $validated['prefix'] ?? '',
            'code_length' => $validated['code_length'],
            'code_format' => $validated['code_format'],
            'password_mode' => $validated['password_mode'],
            'profile' => $validated['profile'],
            'server' => $validated['server'] ?? null,
            'limit_uptime' => $validated['limit_uptime'] ?? null,
            'limit_bytes_total' => $bytes,
            'comment' => $validated['comment'] ?? 'voucher-app',
            'agent_id' => $validated['agent_id'] ?? null,
            'agent_name' => $validated['agent_name'] ?? null,
            'base_price' => $validated['base_price'] ?? 0,
            'commission' => $validated['commission'] ?? 0,
            'created_by' => $request->user()?->id,
        ]);

        if (! $result['ok']) {
            return back()->withInput()->with('error', $result['message']);
        }

        return AdminListState::to('admin.network.hotspot', AdminListState::HOTSPOT, [
            'router_id' => $router->id,
        ])
            ->with('success', $result['message'])
            ->with('generated_vouchers', $result['vouchers'] ?? [])
            ->with('generated_batch_id', $result['batch_id'] ?? null);
    }

    public function destroy(MikrotikRouter $router, string $user): RedirectResponse
    {
        $listed = $this->api->listHotspotUsers($router);
        $username = null;
        if ($listed['ok'] ?? false) {
            $match = collect($listed['users'] ?? [])->first(
                fn (array $row) => ($row['id'] ?? '') === $user || ($row['name'] ?? '') === $user
            );
            $username = $match['name'] ?? null;
        }

        $result = $this->api->removeHotspotUser($router, $user);

        if (($result['ok'] ?? false) && $username) {
            $this->vouchers->markDeletedLocally($router, $username);
        }

        return AdminListState::to('admin.network.hotspot', AdminListState::HOTSPOT, [
            'router_id' => $router->id,
        ])->with($result['ok'] ? 'success' : 'error', $result['message']);
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

        return AdminListState::to('admin.network.hotspot', AdminListState::HOTSPOT, [
            'router_id' => $router->id,
        ])->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function destroyByComment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'router_id' => ['required', 'exists:mikrotik_routers,id'],
            'comment' => ['required', 'string', 'max:255'],
        ]);

        $router = MikrotikRouter::query()->findOrFail($validated['router_id']);
        $result = $this->vouchers->deleteByComment($router, $validated['comment']);

        return AdminListState::to('admin.network.hotspot', AdminListState::HOTSPOT, [
            'router_id' => $router->id,
            'comment' => $validated['comment'],
        ])->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function purge(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'router_id' => ['required', 'exists:mikrotik_routers,id'],
        ]);

        $router = MikrotikRouter::query()->findOrFail($validated['router_id']);
        $result = $this->vouchers->purgeUsed($router);

        return AdminListState::to('admin.network.hotspot', AdminListState::HOTSPOT, [
            'router_id' => $router->id,
        ])->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function printCards(Request $request): Response|RedirectResponse
    {
        $batchId = (string) $request->query('batch_id', '');
        $ids = array_filter(array_map('intval', (array) $request->query('ids', [])));

        $query = HotspotVoucher::query();

        if ($batchId !== '') {
            $vouchers = $query->where('batch_id', $batchId)
                ->latest('id')
                ->limit(500)
                ->get();
        } elseif ($ids !== []) {
            $byId = $query->whereIn('id', $ids)
                ->limit(500)
                ->get()
                ->keyBy('id');

            $vouchers = collect($ids)
                ->map(fn (int $id) => $byId->get($id))
                ->filter()
                ->values();
        } else {
            return AdminListState::to('admin.network.hotspot', AdminListState::HOTSPOT)
                ->with('error', 'Tidak ada voucher untuk dicetak.');
        }

        if ($vouchers->isEmpty()) {
            return AdminListState::to('admin.network.hotspot', AdminListState::HOTSPOT)
                ->with('error', 'Voucher tidak ditemukan.');
        }

        return Inertia::render('Admin/Network/Hotspot/PrintCards', [
            'vouchers' => $this->cardsWithHotspotDns($vouchers),
            'layout' => $this->printLayout($request),
            'show_qr' => $request->query('qr', '1') !== '0',
        ]);
    }

    private function printLayout(Request $request): string
    {
        $layout = (string) $request->query('layout', 'a4');

        return in_array($layout, ['a4', 'small', 'thermal'], true) ? $layout : 'a4';
    }

    /**
     * @param  Collection<int, HotspotVoucher>  $vouchers
     * @return array<int, array<string, mixed>>
     */
    private function cardsWithHotspotDns($vouchers): array
    {
        $dnsByRouter = [];

        foreach ($vouchers->pluck('mikrotik_router_id')->unique()->filter() as $routerId) {
            $router = MikrotikRouter::query()->find($routerId);
            $dnsByRouter[(int) $routerId] = $router
                ? $this->api->mapHotspotDnsByServer($router)
                : ['by_server' => [], 'default' => null];
        }

        return $vouchers
            ->map(function (HotspotVoucher $voucher) use ($dnsByRouter) {
                $map = $dnsByRouter[(int) $voucher->mikrotik_router_id] ?? [
                    'by_server' => [],
                    'default' => null,
                ];

                $server = trim((string) ($voucher->server ?? ''));
                $dns = null;

                if ($server !== '' && strtolower($server) !== 'all') {
                    $dns = $map['by_server'][$server] ?? null;
                }

                $dns = is_string($dns) && $dns !== ''
                    ? $dns
                    : ($map['default'] ?? null);

                $loginHost = is_string($dns) && $dns !== '' ? $dns : null;

                return [
                    ...$voucher->toCardArray(),
                    'dns_name' => $loginHost,
                    'login_url' => HotspotVoucherService::buildHotspotLoginUrl(
                        $loginHost,
                        (string) $voucher->username,
                        (string) $voucher->password,
                    ),
                    'same_code' => (string) $voucher->username === (string) $voucher->password,
                ];
            })
            ->values()
            ->all();
    }

    private function activeRouters()
    {
        return MikrotikRouter::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'host', 'port']);
    }
}
