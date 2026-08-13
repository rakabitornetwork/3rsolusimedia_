<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ResolvesPortalCustomer;
use App\Models\Invoice;
use App\Models\PppoeCustomer;
use App\Services\PaymentGateway\PaymentGatewayManager;
use App\Support\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PaymentPortalController extends Controller
{
    use ResolvesPortalCustomer;

    public function __construct(private readonly PaymentGatewayManager $gateways)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Portal/Pay/Index', [
            'branding' => AppSettings::branding(),
            'gateway_ready' => $this->gateways->hasEnabledGateway(),
        ]);
    }

    public function lookup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40'],
        ]);

        $username = trim($validated['username']);
        $phoneDigits = $this->normalizePortalPhone($validated['phone']);

        if ($phoneDigits === '' || strlen($phoneDigits) < 8) {
            return back()
                ->withErrors(['phone' => 'Nomor telepon tidak valid.'])
                ->withInput();
        }

        $customer = PppoeCustomer::query()
            ->where('username', $username)
            ->get()
            ->first(function (PppoeCustomer $row) use ($phoneDigits) {
                $stored = $this->normalizePortalPhone((string) $row->phone);

                return $stored === $phoneDigits
                    || ($stored !== '' && str_ends_with($stored, $phoneDigits))
                    || ($stored !== '' && str_ends_with($phoneDigits, $stored));
            });

        if (! $customer) {
            return back()
                ->withErrors(['username' => 'Username atau nomor telepon tidak cocok.'])
                ->withInput();
        }

        $token = $this->makePortalToken($customer->id);
        $request->session()->put('portal_customer_id', $customer->id);

        return redirect()->route('portal.home', ['token' => $token]);
    }

    public function invoices(Request $request, string $token): Response|RedirectResponse
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

        $unpaid = Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('status', 'unpaid')
            ->latest('due_date')
            ->get()
            ->map(fn (Invoice $invoice) => $this->invoicePortalArray($invoice))
            ->values()
            ->all();

        $recentPaid = Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('status', 'paid')
            ->latest('paid_at')
            ->limit(5)
            ->get()
            ->map(fn (Invoice $invoice) => $this->invoicePortalArray($invoice))
            ->values()
            ->all();

        return Inertia::render('Portal/Pay/Show', [
            'branding' => AppSettings::branding(),
            'token' => $token,
            'status' => $request->get('status'),
            'customer' => $this->portalCustomerPayload($customer),
            'unpaid' => $unpaid,
            'recent_paid' => $recentPaid,
            'gateway_ready' => $this->gateways->hasEnabledGateway(),
            'default_gateway' => AppSettings::paymentGatewayConfig()['default'],
        ]);
    }

    public function pay(Request $request, string $token, Invoice $invoice): RedirectResponse
    {
        $customer = $this->customerFromPortalToken($token);
        if (! $customer) {
            return redirect()
                ->route('portal.pay.index')
                ->with('error', 'Sesi portal kedaluwarsa. Silakan masuk lagi.');
        }

        if ((int) $invoice->pppoe_customer_id !== (int) $customer->id) {
            return back()->with('error', 'Tagihan tidak ditemukan untuk akun Anda.');
        }

        if (! $invoice->isUnpaid()) {
            return redirect()
                ->route('portal.pay.invoices', ['token' => $token, 'status' => 'already_paid'])
                ->with('success', 'Tagihan sudah lunas.');
        }

        $successUrl = URL::route('portal.pay.invoices', ['token' => $token, 'status' => 'success']);
        $failureUrl = URL::route('portal.pay.invoices', ['token' => $token, 'status' => 'failed']);

        try {
            $result = $this->gateways->createPayment($invoice, $successUrl, $failureUrl);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal membuat link pembayaran: '.$e->getMessage());
        }

        return redirect()->away($result['checkout_url']);
    }

    protected function invoicePortalArray(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'package_name' => $invoice->package_name,
            'due_date' => $invoice->due_date?->format('Y-m-d'),
            'total' => $invoice->total,
            'total_label' => 'Rp '.number_format($invoice->total, 0, ',', '.'),
            'status' => $invoice->status,
            'status_label' => match ($invoice->status) {
                'unpaid' => 'Belum bayar',
                'paid' => 'Lunas',
                'void' => 'Dibatalkan',
                default => $invoice->status,
            },
            'is_overdue' => $invoice->isOverdue(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
        ];
    }
}
