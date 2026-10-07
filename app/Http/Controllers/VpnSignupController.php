<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Portal\Concerns\ResolvesPortalCustomer;
use App\Models\SubscriptionPackage;
use App\Services\BillingCycleService;
use App\Services\BillingService;
use App\Services\Messaging\CustomerNotifier;
use App\Services\PaymentGateway\PaymentGatewayManager;
use App\Services\Vpn\VpnRouterAccounts;
use App\Support\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class VpnSignupController extends Controller
{
    use ResolvesPortalCustomer;

    public function __construct(
        private readonly BillingCycleService $cycle,
        private readonly BillingService $billing,
        private readonly VpnRouterAccounts $accounts,
        private readonly PaymentGatewayManager $gateways,
        private readonly CustomerNotifier $notifier,
    ) {}

    public function create(): Response
    {
        $packages = $this->packages();

        return Inertia::render('Vpn/Signup', [
            'settings' => [
                'company_name' => AppSettings::get('company_name', 'Tesla Tech'),
            ],
            'packages' => $packages->map(fn (SubscriptionPackage $package) => [
                'id' => $package->id,
                'name' => $package->name,
                'price' => (int) $package->price,
                'price_label' => 'Rp '.number_format((int) $package->price, 0, ',', '.'),
                'description' => $package->description,
            ])->values()->all(),
            'open' => $packages->isNotEmpty(),
        ]);
    }

    public function store(Request $request): RedirectResponse|HttpResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:40'],
            'username' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]{1,31}$/', 'unique:pppoe_customers,username'],
            'password' => ['required', 'string', 'min:6', 'max:64'],
            'router_name' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]{1,31}$/'],
            'subscription_package_id' => ['required', 'integer'],
        ], [
            'username.regex' => 'Username akun hanya huruf, angka, titik, garis bawah, atau strip, 2–32 karakter.',
            'username.unique' => 'Username akun sudah dipakai.',
            'router_name.regex' => 'Nama router hanya huruf, angka, titik, garis bawah, atau strip, 2–32 karakter.',
        ]);

        $package = $this->packages()->firstWhere('id', (int) $validated['subscription_package_id']);
        if (! $package) {
            return back()->withErrors(['subscription_package_id' => 'Paket VPN tidak tersedia.'])->withInput();
        }

        try {
            [$customer, $invoice] = DB::transaction(function () use ($validated, $package) {
                $quote = $this->cycle->calculateProrata(now(), (int) now()->day, (int) $package->price);
                if ($quote['amount'] <= 0) {
                    throw new InvalidArgumentException('Tagihan pertama tidak bisa dihitung untuk paket ini.');
                }

                $customer = \App\Models\PppoeCustomer::query()->create([
                    'mikrotik_router_id' => $package->mikrotik_router_id,
                    'subscription_package_id' => $package->id,
                    'name' => $validated['name'],
                    'phone' => $validated['phone'],
                    'username' => $validated['username'],
                    'password' => $validated['password'],
                    'ppp_service' => \App\Models\PppoeCustomer::SERVICE_L2TP,
                    'service_profile' => $package->mikrotik_profile,
                    'start_date' => $quote['start_date'],
                    'billing_day' => $quote['billing_day'],
                    'due_date' => $quote['due_date'],
                    'first_bill_amount' => $quote['amount'],
                    'first_bill_days' => $quote['days'],
                    'overdue_action' => 'isolir',
                    'status' => 'isolated',
                    'sync_status' => 'pending',
                    'is_active' => true,
                    'notes' => 'Daftar sendiri dari halaman VPN.',
                ]);

                $invoice = $this->billing->createProrataInvoice($customer->fresh('package'));
                if (! $invoice) {
                    throw new InvalidArgumentException('Tagihan pertama tidak terbentuk.');
                }

                $this->accounts->enroll($customer, $validated['router_name']);

                return [$customer, $invoice];
            });
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['router_name' => $exception->getMessage()])->withInput();
        }

        try {
            $this->notifier->notifyInvoice($invoice->loadMissing('customer'));
        } catch (Throwable) {
            // Akun tetap jadi meski pesan tagihan gagal.
        }

        $token = $this->makePortalToken($customer->id);
        $request->session()->put('portal_customer_id', $customer->id);

        if ($this->gateways->hasEnabledGateway()) {
            try {
                $result = $this->gateways->createPayment(
                    $invoice,
                    URL::route('portal.pay.invoices', ['token' => $token, 'status' => 'success']),
                    URL::route('portal.pay.invoices', ['token' => $token, 'status' => 'failed']),
                );

                return Inertia::location($result['checkout_url']);
            } catch (InvalidArgumentException|RuntimeException|Throwable $exception) {
                return redirect()
                    ->route('portal.pay.invoices', ['token' => $token])
                    ->with('error', 'Router dibuat, tetapi link pembayaran gagal: '.$exception->getMessage());
            }
        }

        return redirect()
            ->route('portal.pay.invoices', ['token' => $token])
            ->with('success', 'Router '.$validated['router_name'].' sudah dibuat. Bayar tagihan '.$invoice->number.' supaya layanan aktif.');
    }

    /**
     * @return \Illuminate\Support\Collection<int, SubscriptionPackage>
     */
    private function packages()
    {
        return SubscriptionPackage::query()
            ->where('is_active', true)
            ->where('price', '>', 0)
            ->whereHas('router', fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
