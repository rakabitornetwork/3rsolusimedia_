<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PppoeCustomer;
use App\Services\BillingService;
use App\Services\PaymentGateway\PaymentGatewayManager;
use App\Support\AppSettings;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class BillingController extends Controller
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly PaymentGatewayManager $gateways,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        // Generate tagihan bulanan dalam jendela hari yang dikonfigurasi.
        // Tidak membuat ulang prorata yang sengaja dihapus.
        $this->billing->generateOpenInvoices();

        $query = Invoice::query()
            ->with(['customer'])
            ->latest('due_date')
            ->latest('id');

        if ($user->isAgen()) {
            $query->whereHas('customer', fn ($c) => $c->where('agent_id', $user->id));
        }

        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('number', 'like', "%{$search}%")
                    ->orWhere('package_name', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customer) use ($search) {
                        $customer->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($request->boolean('overdue')) {
            $query->where('status', 'unpaid')
                ->whereDate('due_date', '<', now()->toDateString());
        }

        $grace = (string) $request->get('grace', '');
        if ($grace === 'active') {
            $query->whereHas('customer', function ($customer) {
                $customer->whereNotNull('grace_until')
                    ->whereDate('grace_until', '>=', now()->toDateString());
            });
        } elseif ($grace === 'none') {
            $query->where(function ($builder) {
                $builder->whereDoesntHave('customer')
                    ->orWhereHas('customer', function ($customer) {
                        $customer->where(function ($inner) {
                            $inner->whereNull('grace_until')
                                ->orWhereDate('grace_until', '<', now()->toDateString());
                        });
                    });
            });
        }

        $invoices = $query->paginate(20)->withQueryString()->through(
            fn (Invoice $invoice) => $invoice->toAdminArray()
        );

        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $unpaidQuery = Invoice::query()->where('status', 'unpaid');
        $overdueQuery = Invoice::query()->where('status', 'unpaid')->whereDate('due_date', '<', $today);
        $paidMonthQuery = Invoice::query()->where('status', 'paid')->whereDate('paid_at', '>=', $monthStart);
        $paymentMonthQuery = Payment::query()->whereDate('paid_at', '>=', $monthStart);
        $isolatedCustomerQuery = PppoeCustomer::query()->where('status', 'isolated');

        if ($user->isAgen()) {
            $unpaidQuery->whereHas('customer', fn ($c) => $c->where('agent_id', $user->id));
            $overdueQuery->whereHas('customer', fn ($c) => $c->where('agent_id', $user->id));
            $paidMonthQuery->whereHas('customer', fn ($c) => $c->where('agent_id', $user->id));
            $paymentMonthQuery->whereHas('invoice.customer', fn ($c) => $c->where('agent_id', $user->id));
            $isolatedCustomerQuery->where('agent_id', $user->id);
        }

        $collectedAmount = (int) $paymentMonthQuery->sum('amount');

        return Inertia::render('Admin/Billing/Index', [
            'invoices' => $invoices,
            'filters' => [
                'q' => $request->get('q', ''),
                'status' => $request->get('status', ''),
                'overdue' => $request->boolean('overdue'),
                'grace' => in_array($grace, ['active', 'none'], true) ? $grace : '',
            ],
            'stats' => [
                'unpaid' => $unpaidQuery->count(),
                'overdue' => $overdueQuery->count(),
                'paid_this_month' => $paidMonthQuery->count(),
                'collected_this_month' => $collectedAmount,
                'collected_this_month_label' => 'Rp '.number_format($collectedAmount, 0, ',', '.'),
                'isolated' => $isolatedCustomerQuery->count(),
            ],
            'payment_methods' => [
                ['value' => 'cash', 'label' => 'Tunai'],
                ['value' => 'transfer', 'label' => 'Transfer'],
                ['value' => 'qris', 'label' => 'QRIS'],
                ['value' => 'other', 'label' => 'Lainnya'],
            ],
        ]);
    }

    public function show(Request $request, Invoice $invoice): Response|RedirectResponse
    {
        $user = $request->user();
        if ($user->isAgen() && $invoice->customer?->agent_id !== $user->id) {
            return redirect()->route('admin.billing')->with('error', 'Anda tidak memiliki akses ke tagihan pelanggan ini.');
        }

        $invoice->load(['customer.package', 'customer.router', 'payments.receiver', 'package', 'paymentTransactions']);

        return Inertia::render('Admin/Billing/Show', [
            'invoice' => $invoice->toAdminArray(),
            'payment_methods' => [
                ['value' => 'cash', 'label' => 'Tunai'],
                ['value' => 'transfer', 'label' => 'Transfer'],
                ['value' => 'qris', 'label' => 'QRIS'],
                ['value' => 'other', 'label' => 'Lainnya'],
            ],
            'online_pay' => [
                'available' => $this->gateways->hasEnabledGateway(),
                'enabled_gateways' => $this->gateways->enabledGateways(),
                'default_gateway' => AppSettings::paymentGatewayConfig()['default'],
                'portal_url' => url('/portal'),
            ],
        ]);
    }

    public function createOnlinePayment(Request $request, Invoice $invoice): RedirectResponse
    {
        $user = $request->user();
        if ($user->isAgen() && $invoice->customer?->agent_id !== $user->id) {
            return back()->with('error', 'Anda tidak memiliki akses untuk memproses pembayaran tagihan pelanggan ini.');
        }

        $validated = $request->validate([
            'gateway' => ['nullable', Rule::in(['xendit', 'midtrans', 'duitku'])],
        ]);

        $successUrl = URL::route('admin.billing.show', $invoice);
        $failureUrl = URL::route('admin.billing.show', $invoice);

        try {
            $result = $this->gateways->createPayment(
                $invoice,
                $successUrl,
                $failureUrl,
                $validated['gateway'] ?? null,
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal membuat link pembayaran: '.$e->getMessage());
        }

        return redirect()
            ->route('admin.billing.show', $invoice)
            ->with('success', 'Link pembayaran online dibuat.')
            ->with('online_checkout_url', $result['checkout_url']);
    }

    public function print(Request $request, Invoice $invoice)
    {
        $user = $request->user();
        if ($user->isAgen() && $invoice->customer?->agent_id !== $user->id) {
            return redirect()->route('admin.billing')->with('error', 'Anda tidak memiliki akses ke tagihan pelanggan ini.');
        }

        $half = (string) $request->get('half', 'top');
        if (! in_array($half, ['top', 'bottom'], true)) {
            $half = 'top';
        }

        $invoice->load(['customer.package', 'package']);

        return response()->view('admin.billing.invoice-print', [
            'invoice' => $invoice,
            'half' => $half,
            'company' => [
                'name' => AppSettings::companyName(),
                'logo' => AppSettings::branding()['logo_mark'] ?? AppSettings::branding()['logo_full'],
                'address' => trim((string) \App\Models\SiteSetting::getValue('address', '')),
                'phone' => trim((string) \App\Models\SiteSetting::getValue('phone', '')),
                'whatsapp' => trim((string) \App\Models\SiteSetting::getValue('whatsapp', '')),
                'tagline' => trim((string) \App\Models\SiteSetting::getValue('tagline', '')),
            ],
        ]);
    }

    public function pay(Request $request, Invoice $invoice): RedirectResponse
    {
        $user = $request->user();
        if ($user->isAgen() && $invoice->customer?->agent_id !== $user->id) {
            return back()->with('error', 'Anda tidak memiliki akses untuk memproses pembayaran tagihan pelanggan ini.');
        }
        $validated = $request->validate([
            'method' => ['required', Rule::in(['cash', 'transfer', 'qris', 'other'])],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $result = $this->billing->markPaid(
                invoice: $invoice,
                method: $validated['method'],
                reference: $validated['reference'] ?? null,
                notes: $validated['notes'] ?? null,
                receivedBy: $request->user()?->id,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $windowDays = AppSettings::billingGenerateDays();
        $message = 'Pembayaran berhasil. Tagihan '.$result['invoice']->number.' lunas.';
        if ($result['next_due_date']) {
            $message .= ' Jatuh tempo berikutnya: '.$result['next_due_date'].
                ". Tagihan baru akan muncul {$windowDays} hari sebelum tanggal tersebut.";
        }

        return redirect()
            ->route('admin.billing.show', $result['invoice'])
            ->with('success', $message);
    }

    public function generate(): RedirectResponse
    {
        $result = $this->billing->generateOpenInvoices();
        $windowDays = AppSettings::billingGenerateDays();

        return back()->with(
            'success',
            "Generate selesai: {$result['created']} tagihan dibuat (hanya yang jatuh tempo ≤ {$windowDays} hari), {$result['skipped']} dilewati."
        );
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        $number = $invoice->number;

        try {
            $this->billing->deleteInvoice($invoice);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.billing.index')
            ->with('success', "Tagihan {$number} berhasil dihapus.");
    }

    public function void(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $voided = $this->billing->voidInvoice($invoice, $validated['notes'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.billing.show', $voided)
            ->with('success', "Tagihan {$voided->number} dibatalkan (void).");
    }

    public function grantGrace(Request $request, PppoeCustomer $pppoe): RedirectResponse
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', Rule::in([3, 7, 14])],
            'grace_until' => ['nullable', 'date', 'after_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if (empty($validated['days']) && empty($validated['grace_until'])) {
            return back()->with('error', 'Pilih durasi toleransi atau tanggal akhir.');
        }

        $until = ! empty($validated['grace_until'])
            ? Carbon::parse($validated['grace_until'])->startOfDay()
            : now()->startOfDay()->addDays((int) $validated['days']);

        try {
            $this->billing->grantGrace(
                $pppoe,
                $until,
                $validated['note'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            'success',
            'Toleransi isolir aktif sampai '.$until->format('d M Y').'. Jatuh tempo tidak digeser.'
        );
    }

    public function clearGrace(PppoeCustomer $pppoe): RedirectResponse
    {
        $this->billing->clearGrace($pppoe);

        return back()->with('success', 'Toleransi isolir dicabut.');
    }

    public function combineBilling(Request $request, PppoeCustomer $pppoe): RedirectResponse
    {
        $validated = $request->validate([
            'months' => ['nullable', 'integer', 'min:2', 'max:6'],
        ]);

        try {
            $invoice = $this->billing->createCombinedMonthlyInvoice(
                $pppoe->load('package'),
                (int) ($validated['months'] ?? 2),
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.billing.show', $invoice)
            ->with(
                'success',
                'Tagihan gabungan '.$invoice->billing_months.' bulan dibuat: '.$invoice->number
            );
    }
}
