<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ResolvesPortalCustomer;
use App\Models\Invoice;
use App\Models\PppoeCustomer;
use App\Services\GenieAcsService;
use App\Services\PaymentGateway\PaymentGatewayManager;
use App\Support\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerPortalController extends Controller
{
    use ResolvesPortalCustomer;

    public function __construct(
        private readonly GenieAcsService $genie,
        private readonly PaymentGatewayManager $gateways,
    ) {
    }

    public function home(Request $request, string $token): Response|RedirectResponse
    {
        $customer = $this->requireCustomer($request, $token);
        if ($customer instanceof RedirectResponse) {
            return $customer;
        }

        $unpaidCount = Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('status', 'unpaid')
            ->count();

        $unpaidTotal = (int) Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('status', 'unpaid')
            ->sum('total');

        $deviceSummary = $this->resolvePortalDevice($customer);

        return Inertia::render('Portal/Home', [
            'branding' => AppSettings::branding(),
            'token' => $token,
            'customer' => $this->portalCustomerPayload($customer),
            'billing' => [
                'unpaid_count' => $unpaidCount,
                'unpaid_total' => $unpaidTotal,
                'unpaid_total_label' => 'Rp '.number_format($unpaidTotal, 0, ',', '.'),
                'gateway_ready' => $this->gateways->hasEnabledGateway(),
            ],
            'device' => $deviceSummary['device'],
            'device_available' => $deviceSummary['available'],
            'device_message' => $deviceSummary['message'],
            'genieacs_configured' => $this->genie->isConfigured(),
        ]);
    }

    public function device(Request $request, string $token): Response|RedirectResponse
    {
        $customer = $this->requireCustomer($request, $token);
        if ($customer instanceof RedirectResponse) {
            return $customer;
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

    /**
     * @return PppoeCustomer|RedirectResponse
     */
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
