<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Portal\Concerns\ResolvesPortalCustomer;
use App\Models\PppoeCustomer;
use App\Support\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VpnPortalLoginController extends Controller
{
    use ResolvesPortalCustomer;

    public function create(): Response
    {
        return Inertia::render('Vpn/Login', [
            'settings' => [
                'company_name' => AppSettings::get('company_name', 'Tesla Tech'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string', 'max:64'],
        ], [
            'email.required' => 'E-mail wajib diisi.',
            'email.email' => 'E-mail tidak valid.',
            'password.required' => 'Password wajib diisi.',
        ]);

        $customer = PppoeCustomer::query()
            ->where('ppp_service', PppoeCustomer::SERVICE_L2TP)
            ->whereRaw('LOWER(email) = ?', [strtolower($validated['email'])])
            ->first();

        $password = (string) ($customer?->password ?? '');
        if (! $customer || $password === '' || ! hash_equals($password, $validated['password'])) {
            return back()
                ->withErrors(['email' => 'E-mail atau password tidak sesuai.'])
                ->withInput($request->only('email'));
        }

        $token = $this->makePortalToken($customer->id);
        $request->session()->put('portal_customer_id', $customer->id);

        return redirect()->route('portal.home', ['token' => $token]);
    }
}
