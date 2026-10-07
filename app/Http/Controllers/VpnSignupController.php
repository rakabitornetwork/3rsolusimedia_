<?php

namespace App\Http\Controllers;

use App\Models\PppoeCustomer;
use App\Models\SubscriptionPackage;
use App\Services\Messaging\WhatsAppIdentityBinder;
use App\Support\AppSettings;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class VpnSignupController extends Controller
{
    public function __construct(
        private readonly WhatsAppIdentityBinder $binder,
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
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:40'],
        ]);

        $package = $this->packages()->first();
        if (! $package) {
            return back()->withErrors(['phone' => 'Pendaftaran VPN Tunnel belum dibuka.'])->withInput();
        }

        $phone = PhoneNumber::toInternational($validated['phone']);
        if ($phone === '' || strlen($phone) < 10) {
            return back()->withErrors(['phone' => 'Nomor WhatsApp tidak valid.'])->withInput();
        }

        if ($this->binder->customersForNumber($phone)->isNotEmpty()) {
            return back()->withErrors([
                'phone' => 'Nomor WhatsApp ini sudah terdaftar. Masuk portal dengan kode OTP.',
            ])->withInput();
        }

        $today = now()->startOfDay();
        $trialEnds = $today->copy()->addDays(3);

        PppoeCustomer::query()->create([
            'mikrotik_router_id' => $package->mikrotik_router_id,
            'subscription_package_id' => $package->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $phone,
            'username' => $this->uniqueUsername($phone),
            'password' => Str::password(16, symbols: false),
            'ppp_service' => PppoeCustomer::SERVICE_L2TP,
            'service_profile' => $package->mikrotik_profile,
            'start_date' => $today->toDateString(),
            'billing_day' => min(28, (int) $trialEnds->day),
            'due_date' => $trialEnds->toDateString(),
            'vpn_trial_ends_at' => $trialEnds->toDateString(),
            'first_bill_amount' => (int) $package->price,
            'first_bill_days' => 3,
            'overdue_action' => 'isolir',
            'status' => 'active',
            'sync_status' => 'pending',
            'is_active' => true,
            'notes' => 'Daftar sendiri dari halaman VPN Tunnel.',
        ]);

        return redirect()
            ->route('portal.pay.index')
            ->with('success', 'Akun VPN Tunnel sudah dibuat. Masuk portal dengan nomor WhatsApp ini. Kode OTP dikirim ke WhatsApp yang didaftarkan. GRATIS coba 3 hari sampai '.$trialEnds->translatedFormat('d M Y').'.');
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

    private function uniqueUsername(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: 'user';
        $base = 'vt'.substr($digits, -8);
        $candidate = $base;

        for ($attempt = 1; PppoeCustomer::query()->where('username', $candidate)->exists(); $attempt++) {
            $candidate = $attempt > 20
                ? 'vt'.Str::lower(Str::random(8))
                : substr($base, 0, 24).$attempt;
        }

        return $candidate;
    }
}
