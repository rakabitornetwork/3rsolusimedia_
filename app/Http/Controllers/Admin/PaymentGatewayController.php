<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\PaymentGateway\PaymentGatewayManager;
use App\Support\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PaymentGatewayController extends Controller
{
    public function __construct(private readonly PaymentGatewayManager $gateways)
    {
    }

    public function index(): Response
    {
        $config = AppSettings::paymentGatewayConfig();

        return Inertia::render('Admin/Billing/PaymentGateway/Index', [
            'config' => $config,
            'webhook_urls' => [
                'xendit' => url('/webhooks/xendit'),
                'midtrans' => url('/webhooks/midtrans'),
                'duitku' => url('/webhooks/duitku'),
            ],
            'portal_url' => url('/portal'),
            'enabled_gateways' => $this->gateways->enabledGateways(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pg_default' => ['required', Rule::in(['xendit', 'midtrans', 'duitku'])],
            'xendit_enabled' => ['sometimes', 'boolean'],
            'xendit_mode' => ['required', Rule::in(['sandbox', 'live'])],
            'xendit_secret_key' => ['nullable', 'string', 'max:255'],
            'xendit_callback_token' => ['nullable', 'string', 'max:255'],
            'midtrans_enabled' => ['sometimes', 'boolean'],
            'midtrans_mode' => ['required', Rule::in(['sandbox', 'live'])],
            'midtrans_server_key' => ['nullable', 'string', 'max:255'],
            'midtrans_client_key' => ['nullable', 'string', 'max:255'],
            'duitku_enabled' => ['sometimes', 'boolean'],
            'duitku_mode' => ['required', Rule::in(['sandbox', 'live'])],
            'duitku_merchant_code' => ['nullable', 'string', 'max:120'],
            'duitku_api_key' => ['nullable', 'string', 'max:255'],
        ]);

        $values = [
            'pg_default' => $validated['pg_default'],
            'xendit_enabled' => $request->boolean('xendit_enabled') ? '1' : '0',
            'xendit_mode' => $validated['xendit_mode'],
            'midtrans_enabled' => $request->boolean('midtrans_enabled') ? '1' : '0',
            'midtrans_mode' => $validated['midtrans_mode'],
            'midtrans_client_key' => $validated['midtrans_client_key'] ?? '',
            'duitku_enabled' => $request->boolean('duitku_enabled') ? '1' : '0',
            'duitku_mode' => $validated['duitku_mode'],
            'duitku_merchant_code' => $validated['duitku_merchant_code'] ?? '',
        ];

        foreach ([
            'xendit_secret_key',
            'xendit_callback_token',
            'midtrans_server_key',
            'duitku_api_key',
        ] as $secretKey) {
            if (array_key_exists($secretKey, $validated) && filled($validated[$secretKey])) {
                $values[$secretKey] = $validated[$secretKey];
            }
        }

        SiteSetting::setMany($values);

        return redirect()
            ->route('admin.billing.payment-gateway')
            ->with('success', 'Pengaturan Payment Gateway berhasil disimpan.');
    }

    public function test(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'gateway' => ['required', Rule::in(['xendit', 'midtrans', 'duitku'])],
        ]);

        $result = $this->gateways->driver($validated['gateway'])->testConnection();

        return back()->with(
            $result['ok'] ? 'success' : 'error',
            $result['message'].(isset($result['latency_ms']) ? " ({$result['latency_ms']} ms)" : '')
        );
    }
}
