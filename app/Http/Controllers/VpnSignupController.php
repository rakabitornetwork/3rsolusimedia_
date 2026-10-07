<?php

namespace App\Http\Controllers;

use App\Models\PppoeCustomer;
use App\Models\SubscriptionPackage;
use App\Services\Messaging\CustomerNotifier;
use App\Services\Messaging\WhatsAppIdentityBinder;
use App\Services\Vpn\VpnChrSettings;
use App\Support\AppSettings;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VpnSignupController extends Controller
{
    public function __construct(
        private readonly WhatsAppIdentityBinder $binder,
        private readonly CustomerNotifier $notifier,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Vpn/Signup', [
            'settings' => [
                'company_name' => AppSettings::get('company_name', 'Tesla Tech'),
            ],
            'open' => $this->packages()->isNotEmpty(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('pppoe_customers', 'email')->where(
                    fn ($query) => $query->where('ppp_service', PppoeCustomer::SERVICE_L2TP)
                ),
            ],
            'password' => ['required', 'string', 'min:8', 'max:64', 'confirmed', 'regex:/^[A-Za-z0-9._@!-]+$/'],
            'phone' => ['required', 'string', 'max:40'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'E-mail wajib diisi.',
            'email.email' => 'E-mail tidak valid.',
            'email.unique' => 'E-mail ini sudah terdaftar. Masuk dengan email dan password tersebut.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
            'password.regex' => 'Password hanya boleh huruf, angka, dan karakter . _ @ ! -',
            'phone.required' => 'Nomor WhatsApp wajib diisi.',
        ]);

        $package = $this->packages()->first();
        if (! $package) {
            return back()->withErrors(['phone' => 'Pendaftaran VPN Tunnel belum dibuka.'])->withInput();
        }

        $phone = PhoneNumber::toInternational($validated['phone']);
        if ($phone === '' || strlen($phone) < 10) {
            return back()->withErrors(['phone' => 'Nomor WhatsApp tidak valid.'])->withInput();
        }

        $usernameError = $this->usernameFromNameError($validated['name'], (int) $package->mikrotik_router_id);
        if ($usernameError !== null) {
            return back()->withErrors(['name' => $usernameError])->withInput();
        }

        if ($this->binder->customersForNumber($phone)->isNotEmpty()) {
            return back()->withErrors([
                'phone' => 'Nomor WhatsApp ini sudah terdaftar.',
            ])->withInput();
        }

        $today = now()->startOfDay();

        $customer = PppoeCustomer::query()->create([
            'mikrotik_router_id' => $package->mikrotik_router_id,
            'subscription_package_id' => $package->id,
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'phone' => $phone,
            'username' => trim($validated['name']),
            'password' => $validated['password'],
            'ppp_service' => PppoeCustomer::SERVICE_L2TP,
            'service_profile' => $package->mikrotik_profile,
            'start_date' => $today->toDateString(),
            'billing_day' => min(28, (int) $today->day),
            'due_date' => $today->toDateString(),
            'first_bill_amount' => (int) $package->price,
            'first_bill_days' => 3,
            'overdue_action' => 'isolir',
            'status' => 'disabled',
            'sync_status' => 'pending',
            'is_active' => false,
            'vpn_self_signup' => true,
            'notes' => 'Daftar sendiri dari halaman VPN Tunnel. Akun CHR dibuat dari portal.',
        ]);

        try {
            $this->notifier->notifyVpnSignup($customer);
        } catch (\Throwable) {
            // Pendaftaran tetap tersimpan meski WhatsApp gagal.
        }

        return redirect()
            ->route('vpn.login')
            ->with('success', 'Pendaftaran berhasil. Masuk dengan email dan password yang baru dibuat. Informasi akun juga dikirim ke WhatsApp.');
    }

    /**
     * @return \Illuminate\Support\Collection<int, SubscriptionPackage>
     */
    private function packages()
    {
        $routerId = VpnChrSettings::mikrotikRouterId();

        $query = SubscriptionPackage::query()
            ->where('is_active', true)
            ->where('price', '>', 0)
            ->whereHas('router', fn ($query) => $query->where('is_active', true));

        if ($routerId) {
            $query->where('mikrotik_router_id', $routerId);
        } else {
            return collect();
        }

        $packages = $query->orderBy('sort_order')->orderBy('name')->get();
        $tunnel = $packages->filter(
            fn (SubscriptionPackage $package) => str_contains(strtolower($package->name), 'vpn tunnel')
        );

        return $tunnel->isNotEmpty() ? $tunnel->values() : $packages;
    }

    private function usernameFromNameError(string $name, int $routerId): ?string
    {
        $username = trim($name);
        if ($username === '') {
            return 'Nama wajib diisi.';
        }

        if (mb_strlen($username) > 100) {
            return 'Nama pelanggan maksimal 100 karakter karena dipakai sebagai username secret.';
        }

        $taken = PppoeCustomer::query()
            ->where('mikrotik_router_id', $routerId)
            ->whereRaw('LOWER(username) = ?', [mb_strtolower($username)])
            ->exists();

        if ($taken) {
            return 'Nama ini sudah dipakai pelanggan lain di router yang sama.';
        }

        return null;
    }
}
