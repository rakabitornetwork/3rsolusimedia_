<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ResolvesPortalCustomer;
use App\Services\Portal\PortalWhatsappLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WhatsappLoginController extends Controller
{
    use ResolvesPortalCustomer;

    public function __construct(private readonly PortalWhatsappLogin $login) {}

    public function requestCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
        ]);

        $result = $this->login->requestCode($validated['phone']);

        if ($result['pending']) {
            $request->session()->put(PortalWhatsappLogin::SESSION_PHONE, $result['phone']);

            return back()->with('success', $result['message']);
        }

        if ($result['status'] !== 'limited') {
            $request->session()->forget(PortalWhatsappLogin::SESSION_PHONE);
        }

        return back()
            ->withErrors(['whatsapp' => $result['message']])
            ->withInput();
    }

    public function verify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
            'code' => ['required', 'string', 'max:12'],
        ]);

        $result = $this->login->verifyCode($validated['phone'], $validated['code']);
        if (! $result['ok'] || $result['customer'] === null) {
            return back()
                ->withErrors(['code' => $result['message']])
                ->withInput();
        }

        $customer = $result['customer'];
        $token = $this->makePortalToken($customer->id);
        $request->session()->put('portal_customer_id', $customer->id);
        $request->session()->forget(PortalWhatsappLogin::SESSION_PHONE);

        return redirect()->route('portal.home', ['token' => $token]);
    }

    public function cancel(Request $request): RedirectResponse
    {
        $phone = (string) $request->session()->get(PortalWhatsappLogin::SESSION_PHONE, '');
        if ($phone !== '') {
            $this->login->forget($phone);
        }

        $request->session()->forget(PortalWhatsappLogin::SESSION_PHONE);

        return back();
    }
}
