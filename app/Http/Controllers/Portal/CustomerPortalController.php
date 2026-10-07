<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ResolvesPortalCustomer;
use App\Models\Invoice;
use App\Models\PppoeCustomer;
use App\Models\VpnRouter;
use App\Services\GenieAcsService;
use App\Services\Messaging\CustomerNotifier;
use App\Services\MikrotikApiService;
use App\Services\PppoeMonthlyUsageService;
use App\Services\PaymentGateway\PaymentGatewayManager;
use App\Services\Vpn\L2tpClientScript;
use App\Services\Vpn\VpnAccessPlan;
use App\Services\Vpn\VpnChrSettings;
use App\Services\Vpn\VpnProvisioner;
use App\Services\Vpn\VpnRouterAccounts;
use App\Support\AppSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class CustomerPortalController extends Controller
{
    use ResolvesPortalCustomer;

    public function __construct(
        private readonly GenieAcsService $genie,
        private readonly PaymentGatewayManager $gateways,
        private readonly MikrotikApiService $mikrotik,
        private readonly PppoeMonthlyUsageService $usage,
        private readonly L2tpClientScript $l2tpScript,
        private readonly VpnAccessPlan $vpnPlan,
        private readonly VpnRouterAccounts $vpnAccounts,
        private readonly VpnProvisioner $vpn,
        private readonly CustomerNotifier $notifier,
    ) {}

    public function home(Request $request, string $token): Response|RedirectResponse
    {
        $customer = $this->requireCustomer($request, $token);
        if ($customer instanceof RedirectResponse) {
            return $customer;
        }

        if ($customer->pppService() === PppoeCustomer::SERVICE_L2TP) {
            return $this->vpnHome($token, $customer);
        }

        $deviceSummary = $this->resolvePortalDevice($customer);
        $customer->load(['usageThisMonth', 'trafficCursor']);

        return Inertia::render('Portal/Home', [
            'branding' => AppSettings::branding(),
            'token' => $token,
            'customer' => $this->portalCustomerPayload($customer),
            'billing' => $this->billingPayload($customer),
            'usage' => $this->usage->present($customer->usageThisMonth, $customer->trafficCursor),
            'device' => $deviceSummary['device'],
            'device_available' => $deviceSummary['available'],
            'device_message' => $deviceSummary['message'],
            'genieacs_configured' => $this->genie->isConfigured(),
            'banners' => AppSettings::portalBanners($token),
        ]);
    }

    public function device(Request $request, string $token): Response|RedirectResponse
    {
        $customer = $this->requireCustomer($request, $token);
        if ($customer instanceof RedirectResponse) {
            return $customer;
        }

        if ($denied = $this->denyVpnDevice($customer, $token)) {
            return $denied;
        }

        $deviceSummary = $this->resolvePortalDevice($customer);

        return Inertia::render('Portal/Device/Show', [
            'branding' => AppSettings::branding(),
            'token' => $token,
            'customer' => $this->portalCustomerPayload($customer),
            'device' => $deviceSummary['device'],
            'device_available' => $deviceSummary['available'],
            'device_message' => $deviceSummary['message'],
            'genieacs_configured' => $this->genie->isConfigured(),
        ]);
    }

    public function updateWifi(Request $request, string $token): RedirectResponse
    {
        $customer = $this->requireCustomer($request, $token);
        if ($customer instanceof RedirectResponse) {
            return $customer;
        }

        if ($denied = $this->denyVpnDevice($customer, $token)) {
            return $denied;
        }

        $validated = $request->validate([
            'ssid' => ['nullable', 'string', 'max:32'],
            'password' => ['nullable', 'string', 'min:8', 'max:63'],
        ]);

        $ssid = trim((string) ($validated['ssid'] ?? ''));
        $password = (string) ($validated['password'] ?? '');

        if ($ssid === '' && $password === '') {
            return back()->with('error', 'Isi SSID dan/atau password baru.');
        }

        $owned = $this->ownedDevice($customer);
        if (! ($owned['ok'] ?? false)) {
            return back()->with('error', $owned['message'] ?? 'Perangkat tidak ditemukan.');
        }

        $result = $this->genie->updateWifi(
            (string) $owned['device']['id'],
            $ssid !== '' ? $ssid : null,
            $password !== '' ? $password : null,
        );

        return back()->with(
            $result['ok'] ? 'success' : 'error',
            $result['message']
        );
    }

    public function reboot(Request $request, string $token): RedirectResponse
    {
        $customer = $this->requireCustomer($request, $token);
        if ($customer instanceof RedirectResponse) {
            return $customer;
        }

        if ($denied = $this->denyVpnDevice($customer, $token)) {
            return $denied;
        }

        $owned = $this->ownedDevice($customer);
        if (! ($owned['ok'] ?? false)) {
            return back()->with('error', $owned['message'] ?? 'Perangkat tidak ditemukan.');
        }

        $result = $this->genie->rebootDevice((string) $owned['device']['id']);

        return back()->with(
            $result['ok'] ? 'success' : 'error',
            $result['message']
        );
    }

    public function refresh(Request $request, string $token): RedirectResponse
    {
        $customer = $this->requireCustomer($request, $token);
        if ($customer instanceof RedirectResponse) {
            return $customer;
        }

        if ($denied = $this->denyVpnDevice($customer, $token)) {
            return $denied;
        }

        $owned = $this->ownedDevice($customer);
        if (! ($owned['ok'] ?? false)) {
            return back()->with('error', $owned['message'] ?? 'Perangkat tidak ditemukan.');
        }

        $result = $this->genie->summonDevice((string) $owned['device']['id']);

        return back()->with(
            $result['ok'] ? 'success' : 'error',
            $result['message']
        );
    }

    public function traffic(Request $request, string $token): JsonResponse
    {
        $customer = $this->customerFromPortalToken($token);
        if (! $customer) {
            return response()->json([
                'ok' => false,
                'online' => false,
                'message' => 'Sesi portal kedaluwarsa.',
            ], 401);
        }

        if ((int) $request->session()->get('portal_customer_id') !== (int) $customer->id) {
            $request->session()->put('portal_customer_id', $customer->id);
        }

        if ($customer->pppService() === PppoeCustomer::SERVICE_L2TP) {
            return response()->json([
                'ok' => false,
                'online' => false,
                'message' => 'Trafik perangkat hanya untuk pelanggan PPPoE.',
            ], 404);
        }

        $router = $customer->router;
        if (! $router) {
            return response()->json([
                'ok' => false,
                'online' => false,
                'message' => 'Router pelanggan tidak ditemukan.',
            ]);
        }

        $result = $this->mikrotik->monitorPppoeUserTraffic($router, (string) $customer->username);

        return response()->json($result);
    }

    protected function requireCustomer(Request $request, string $token): PppoeCustomer|RedirectResponse
    {
        $customer = $this->customerFromPortalToken($token);
        if (! $customer) {
            return redirect()
                ->route('portal.pay.index')
                ->with('error', 'Sesi portal kedaluwarsa. Silakan masuk lagi.');
        }

        if ((int) $request->session()->get('portal_customer_id') !== (int) $customer->id) {
            $request->session()->put('portal_customer_id', $customer->id);
        }

        return $customer;
    }

    private function vpnHome(string $token, PppoeCustomer $customer): Response
    {
        $this->vpnPlan->ensure($customer);
        $customer->refresh()->load(['router', 'vpnPortForwards', 'vpnRouters.portForwards']);
        $script = $this->l2tpScript->build($customer);
        $server = VpnChrSettings::host() !== ''
            ? VpnChrSettings::host()
            : trim((string) ($customer->router?->host ?? ''));

        return Inertia::render('Portal/Vpn/Home', [
            'branding' => AppSettings::branding(),
            'token' => $token,
            'customer' => $this->portalCustomerPayload($customer),
            'billing' => $this->billingPayload($customer),
            'banners' => AppSettings::portalBanners($token),
            'script' => $script,
            'script_message' => $script === null ? $this->l2tpScript->unavailableReason($customer) : null,
            'vpn' => [
                'server' => $server,
                'username' => (string) $customer->username,
                'interface' => L2tpClientScript::INTERFACE_NAME,
                'address' => $customer->vpn_remote_address,
                'ports' => $customer->vpnPortForwards->map(fn ($forward) => [
                    'public_port' => $forward->public_port,
                    'dst_port' => $forward->dst_port,
                    'label' => $forward->label,
                    'note' => (int) $forward->dst_port === 22
                        ? 'Port ini boleh Anda teruskan ke perangkat mana pun.'
                        : null,
                ])->values()->all(),
                'router_limit' => 3,
                'router_count' => 1 + $customer->vpnRouters->count(),
                'spare_routers' => $this->vpnAccounts->spareCount($customer),
                'extra_routers' => $customer->vpnRouters->map(fn ($router) => [
                    'id' => $router->id,
                    'name' => $router->name,
                    'usable' => $router->isUsable(),
                    'service_until' => $router->service_until?->toDateString(),
                    'script' => $router->isUsable() ? $this->l2tpScript->buildForRouter($router) : null,
                    'ports' => $router->isUsable()
                        ? $router->portForwards->map(fn ($forward) => [
                            'public_port' => $forward->public_port,
                            'dst_port' => $forward->dst_port,
                            'label' => $forward->label,
                        ])->values()->all()
                        : [],
                ])->values()->all(),
            ],
        ]);
    }

    public function storeVpnRouter(Request $request, string $token): RedirectResponse
    {
        $customer = $this->requireVpnCustomer($request, $token);
        if ($customer instanceof RedirectResponse) {
            return $customer;
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:32'],
        ]);

        try {
            $enrolled = $this->vpnAccounts->enroll($customer, $validated['name']);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $router = $enrolled['router'];
        $push = $this->vpn->pushRouter($router);
        $name = $validated['name'];

        if ($enrolled['reused']) {
            $message = 'Router '.$name.' dibuat tanpa tagihan baru. Tagihan sebelumnya tetap berlaku.';
        } else {
            $number = $enrolled['invoice']?->number;
            $message = 'Router '.$name.' dibuat. Tagihan '.($number ?: 'baru').' muncul hari ini. Secret baru aktif setelah lunas.';
            if ($enrolled['invoice']) {
                try {
                    $this->notifier->notifyInvoice($enrolled['invoice']->loadMissing('customer'));
                } catch (\Throwable) {
                    // Router tetap tersimpan meski WhatsApp gagal.
                }
            }
        }

        if (! $push['ok']) {
            $message .= ' '.$push['message'];
        } else {
            $message .= ' Aturan CHR sudah diisi.';
        }

        return back()->with('success', $message);
    }

    public function destroyVpnRouter(Request $request, string $token, VpnRouter $vpnRouter): RedirectResponse
    {
        $customer = $this->requireVpnCustomer($request, $token);
        if ($customer instanceof RedirectResponse) {
            return $customer;
        }

        if ((int) $vpnRouter->pppoe_customer_id !== (int) $customer->id) {
            abort(404);
        }

        $result = $this->vpnAccounts->release($vpnRouter);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    private function requireVpnCustomer(Request $request, string $token): PppoeCustomer|RedirectResponse
    {
        $customer = $this->requireCustomer($request, $token);
        if ($customer instanceof RedirectResponse) {
            return $customer;
        }

        if ($customer->pppService() !== PppoeCustomer::SERVICE_L2TP) {
            return back()->with('error', 'Router tambahan hanya untuk pelanggan VPN.');
        }

        return $customer;
    }

    /**
     * @return array{unpaid_count: int, unpaid_total: int, unpaid_total_label: string, oldest_unpaid_id: ?int, gateway_ready: bool}
     */
    private function billingPayload(PppoeCustomer $customer): array
    {
        $unpaidQuery = Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('status', 'unpaid');

        $unpaidCount = (clone $unpaidQuery)->count();
        $unpaidTotal = (int) (clone $unpaidQuery)->sum('total');
        $oldestUnpaidId = (clone $unpaidQuery)->orderBy('due_date')->orderBy('id')->value('id');

        return [
            'unpaid_count' => $unpaidCount,
            'unpaid_total' => $unpaidTotal,
            'unpaid_total_label' => 'Rp '.number_format($unpaidTotal, 0, ',', '.'),
            'oldest_unpaid_id' => $oldestUnpaidId ? (int) $oldestUnpaidId : null,
            'gateway_ready' => $this->gateways->hasEnabledGateway(),
        ];
    }

    private function denyVpnDevice(PppoeCustomer $customer, string $token): ?RedirectResponse
    {
        if ($customer->pppService() !== PppoeCustomer::SERVICE_L2TP) {
            return null;
        }

        return redirect()
            ->route('portal.home', ['token' => $token])
            ->with('error', 'Halaman perangkat hanya untuk pelanggan PPPoE. Skrip VPN ada di beranda.');
    }

    /**
     * @return array{available: bool, message: ?string, device: ?array}
     */
    protected function resolvePortalDevice(PppoeCustomer $customer): array
    {
        if (! $this->genie->isConfigured()) {
            return [
                'available' => false,
                'message' => 'Monitoring perangkat belum diaktifkan. Hubungi admin.',
                'device' => null,
            ];
        }

        $result = $this->genie->findDeviceByPppoeUsername((string) $customer->username);
        if (! ($result['ok'] ?? false) || empty($result['device'])) {
            return [
                'available' => false,
                'message' => $result['message'] ?? 'Perangkat belum terhubung ke sistem monitoring.',
                'device' => null,
            ];
        }

        $device = $result['device'];
        $deviceUsername = strtolower(trim((string) ($device['pppoe_username'] ?? '')));
        $customerUsername = strtolower(trim((string) $customer->username));

        if ($deviceUsername === '' || $deviceUsername !== $customerUsername) {
            return [
                'available' => false,
                'message' => 'Perangkat GenieACS tidak cocok dengan akun Anda.',
                'device' => null,
            ];
        }

        return [
            'available' => true,
            'message' => null,
            'device' => $this->genie->toPortalSafeDevice($device),
        ];
    }

    /**
     * @return array{ok: bool, message?: string, device?: array}
     */
    protected function ownedDevice(PppoeCustomer $customer): array
    {
        if (! $this->genie->isConfigured()) {
            return [
                'ok' => false,
                'message' => 'Monitoring perangkat belum diaktifkan.',
            ];
        }

        $result = $this->genie->findDeviceByPppoeUsername((string) $customer->username);
        if (! ($result['ok'] ?? false) || empty($result['device']['id'])) {
            return [
                'ok' => false,
                'message' => $result['message'] ?? 'Perangkat tidak ditemukan.',
            ];
        }

        $deviceUsername = strtolower(trim((string) ($result['device']['pppoe_username'] ?? '')));
        $customerUsername = strtolower(trim((string) $customer->username));

        if ($deviceUsername === '' || $deviceUsername !== $customerUsername) {
            return [
                'ok' => false,
                'message' => 'Perangkat GenieACS tidak cocok dengan akun Anda.',
            ];
        }

        return [
            'ok' => true,
            'device' => $result['device'],
        ];
    }
}
