<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HotspotVoucher;
use App\Models\MikrotikRouter;
use App\Models\User;
use App\Services\HotspotVoucherService;
use App\Services\MikrotikApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HotspotVoucherController extends Controller
{
    public function __construct(
        private readonly MikrotikApiService $api,
        private readonly HotspotVoucherService $vouchers,
    ) {
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

        $metaByName = $selected
            ? HotspotVoucher::query()
                ->where('mikrotik_router_id', $selected->id)
                ->whereIn('username', collect($users)->pluck('name')->filter()->all())
                ->get()
                ->keyBy('username')
            : collect();

        $filtered = collect($users)
            ->map(function (array $user) use ($metaByName) {
                /** @var HotspotVoucher|null $meta */
                $meta = $metaByName->get($user['name'] ?? '');

                return [
                    ...$user,
                    'agent_name' => $meta?->agent_name,
                    'base_price' => $meta?->base_price,
                    'commission' => $meta?->commission,
                    'sell_price' => $meta?->sell_price,
                    'sell_price_label' => $meta
                        ? 'Rp '.number_format((int) $meta->sell_price, 0, ',', '.')
                        : null,
                ];
            })
            ->when($q !== '', function ($collection) use ($q) {
                $needle = mb_strtolower($q);

                return $collection->filter(function (array $user) use ($needle) {
                    return str_contains(mb_strtolower($user['name']), $needle)
                        || str_contains(mb_strtolower((string) ($user['comment'] ?? '')), $needle)
                        || str_contains(mb_strtolower((string) ($user['agent_name'] ?? '')), $needle);
                });
            })
            ->when($profileFilter !== '', fn ($collection) => $collection->where('profile', $profileFilter))
            ->when($statusFilter === 'online', fn ($collection) => $collection->where('is_online', true))
            ->when($statusFilter === 'disabled', fn ($collection) => $collection->where('disabled', true))
            ->when($statusFilter === 'active', fn ($collection) => $collection->where('disabled', false))
            ->values();

        $generated = session('generated_vouchers', []);
        $generatedBatchId = session('generated_batch_id');

        return Inertia::render('Admin/Network/Hotspot/Index', [
            'routers' => $routers->values(),
            'selected_router_id' => $selected?->id,
            'users' => $filtered,
            'profiles' => $profiles,
            'error' => $error,
            'purged_message' => $purgedMessage,
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
            'generated_vouchers' => $generated,
            'generated_batch_id' => $generatedBatchId,
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

        $agents = User::query()
            ->where('role', User::ROLE_AGEN)
            ->orderBy('name')
            ->get(['id', 'name', 'voucher_commission'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'voucher_commission' => (int) ($user->voucher_commission ?? 0),
            ])
            ->values();

        return Inertia::render('Admin/Network/Hotspot/Generate', [
            'routers' => $routers->values(),
            'selected_router_id' => $router->id,
            'profiles' => $this->api->listHotspotUserProfiles($router)['profiles'] ?? [],
            'servers' => $this->api->listHotspotServers($router)['servers'] ?? [],
            'agents' => $agents,
            'code_formats' => HotspotVoucherService::codeFormatOptions(),
            'scenarios' => HotspotVoucherService::scenarioPresets(),
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
            'code_format' => ['required', Rule::in(['alphanumeric', 'numeric', 'letters', 'hex'])],
            'password_mode' => ['required', Rule::in(['same', 'random'])],
            'limit_uptime' => ['nullable', 'string', 'max:40'],
            'limit_bytes_mb' => ['nullable', 'integer', 'min:1', 'max:1048576'],
            'comment' => ['nullable', 'string', 'max:255'],
            'agent_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', User::ROLE_AGEN)),
            ],
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
            'base_price' => $validated['base_price'] ?? 0,
            'commission' => $validated['commission'] ?? 0,
            'created_by' => $request->user()?->id,
        ]);

        if (! $result['ok']) {
            return back()->withInput()->with('error', $result['message']);
        }

        return redirect()
            ->route('admin.network.hotspot', ['router_id' => $router->id])
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

    public function purge(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'router_id' => ['required', 'exists:mikrotik_routers,id'],
        ]);

        $router = MikrotikRouter::query()->findOrFail($validated['router_id']);
        $result = $this->vouchers->purgeUsed($router);

        return redirect()
            ->route('admin.network.hotspot', ['router_id' => $router->id])
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function printCards(Request $request): Response|RedirectResponse
    {
        $batchId = (string) $request->query('batch_id', '');
        $ids = array_filter(array_map('intval', (array) $request->query('ids', [])));

        $query = HotspotVoucher::query()->latest('id');

        if ($batchId !== '') {
            $query->where('batch_id', $batchId);
        } elseif ($ids !== []) {
            $query->whereIn('id', $ids);
        } else {
            return redirect()
                ->route('admin.network.hotspot')
                ->with('error', 'Tidak ada voucher untuk dicetak.');
        }

        $cards = $query->limit(200)->get()->map(fn (HotspotVoucher $v) => $v->toCardArray())->all();

        if ($cards === []) {
            return redirect()
                ->route('admin.network.hotspot')
                ->with('error', 'Voucher tidak ditemukan.');
        }

        return Inertia::render('Admin/Network/Hotspot/PrintCards', [
            'vouchers' => $cards,
        ]);
    }

    private function activeRouters()
    {
        return MikrotikRouter::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'host', 'port']);
    }
}
