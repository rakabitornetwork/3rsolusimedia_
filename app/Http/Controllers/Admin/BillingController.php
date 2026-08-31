<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\Payment;
use App\Models\PppoeCustomer;
use App\Models\SiteSetting;
use App\Services\BillingService;
use App\Services\Messaging\CustomerNotifier;
use App\Services\Messaging\MessageTemplate;
use App\Services\PaymentGateway\PaymentGatewayManager;
use App\Support\AdminListState;
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
        private readonly CustomerNotifier $notifier,
    ) {}

    public function index(Request $request): Response
    {
        AdminListState::apply($request, AdminListState::BILLING, [
            'q', 'status', 'overdue', 'grace', 'customer_status', 'router_id', 'sort', 'direction', 'page',
        ]);

        $user = $request->user();
        $routerId = $request->get('router_id', '');
        $sort = (string) $request->get('sort', 'due_date');
        $direction = strtolower((string) $request->get('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = [
            'number' => 'invoices.number',
            'customer' => 'pppoe_customers.name',
            'type' => 'invoices.type',
            'due_date' => 'invoices.due_date',
            'total' => 'invoices.total',
            'status' => 'invoices.status',
        ];

        if (! array_key_exists($sort, $allowedSorts)) {
            $sort = 'due_date';
        }

        // Generate tagihan bulanan dalam jendela hari yang dikonfigurasi.
        // Tidak membuat ulang prorata yang sengaja dihapus.
        $this->billing->generateOpenInvoices();

        $query = Invoice::query()
            ->with(['customer.router'])
            ->select('invoices.*');

        if ($sort === 'customer') {
            $query->leftJoin(
                'pppoe_customers',
                'pppoe_customers.id',
                '=',
                'invoices.pppoe_customer_id'
            );
        }

        if ($user->isAgen()) {
            $query->whereHas('customer', fn ($c) => $c->where('agent_id', $user->id));
        }

        if ($routerId) {
            $query->whereHas('customer', fn ($c) => $c->where('mikrotik_router_id', $routerId));
        }

        if ($status = $request->get('status')) {
            $query->where('invoices.status', $status);
        }

        if ($request->boolean('overdue')) {
            $query->where('invoices.status', 'unpaid')
                ->whereDate('invoices.due_date', '<', now()->toDateString());
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

        $customerStatus = (string) $request->get('customer_status', '');
        if ($customerStatus === 'isolated') {
            $query->whereHas('customer', fn ($customer) => $customer->where('status', 'isolated'));
        }

        $query->orderBy($allowedSorts[$sort], $direction);

        if ($sort === 'type') {
            $query->orderBy('invoices.billing_months', $direction);
        }

        $query->orderBy('invoices.id', $direction);

        $invoices = $query->get()->map(
            fn (Invoice $invoice) => $invoice->toAdminArray()
        )->values();

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

        if ($routerId) {
            $unpaidQuery->whereHas('customer', fn ($c) => $c->where('mikrotik_router_id', $routerId));
            $overdueQuery->whereHas('customer', fn ($c) => $c->where('mikrotik_router_id', $routerId));
            $paidMonthQuery->whereHas('customer', fn ($c) => $c->where('mikrotik_router_id', $routerId));
            $paymentMonthQuery->whereHas('invoice.customer', fn ($c) => $c->where('mikrotik_router_id', $routerId));
            $isolatedCustomerQuery->where('mikrotik_router_id', $routerId);
        }

        $collectedAmount = (int) $paymentMonthQuery->sum('amount');

        return Inertia::render('Admin/Billing/Index', [
            'invoices' => $invoices,
            'filters' => [
                'q' => $request->get('q', ''),
                'status' => $request->get('status', ''),
                'overdue' => $request->boolean('overdue'),
                'grace' => in_array($grace, ['active', 'none'], true) ? $grace : '',
                'customer_status' => $customerStatus === 'isolated' ? 'isolated' : '',
                'router_id' => $routerId ?: '',
                'sort' => $sort,
                'direction' => $direction,
            ],
            'routers' => MikrotikRouter::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'host']),
            'stats' => [
                'unpaid' => $unpaidQuery->count(),
                'overdue' => $overdueQuery->count(),
                'paid_this_month' => $paidMonthQuery->count(),
                'collected_this_month' => $collectedAmount,
                'collected_this_month_label' => 'Rp '.number_format($collectedAmount, 0, ',', '.'),
                'isolated' => $isolatedCustomerQuery->count(),
            ],
            'whatsapp' => $this->notifier->whatsappManualUi(),
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
            return AdminListState::to('admin.billing.index', AdminListState::BILLING)
                ->with('error', 'Anda tidak memiliki akses ke tagihan pelanggan ini.');
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
            'whatsapp' => $this->notifier->whatsappManualUi(),
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
            return AdminListState::to('admin.billing.index', AdminListState::BILLING)
                ->with('error', 'Anda tidak memiliki akses ke tagihan pelanggan ini.');
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
                'address' => trim((string) SiteSetting::getValue('address', '')),
                'phone' => trim((string) SiteSetting::getValue('phone', '')),
                'whatsapp' => trim((string) SiteSetting::getValue('whatsapp', '')),
                'tagline' => trim((string) SiteSetting::getValue('tagline', '')),
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

        return back()->with('success', $message);
    }

    public function bulkPay(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:invoices,id'],
            'method' => ['required', Rule::in(['cash', 'transfer', 'qris', 'other'])],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $this->billing->markPaidMany(
            invoiceIds: $validated['ids'],
            method: $validated['method'],
            reference: $validated['reference'] ?? null,
            notes: $validated['notes'] ?? null,
            receivedBy: $user?->id,
            agentId: $user?->isAgen() ? $user->id : null,
        );

        if ($result['paid'] === 0) {
            return back()->with(
                'error',
                'Tidak ada tagihan yang dilunasi. Pilih tagihan berstatus belum bayar.'
            );
        }

        $message = $result['paid'].' tagihan ditandai lunas.';
        if ($result['skipped'] > 0) {
            $message .= ' '.$result['skipped'].' dilewati (sudah lunas, dibatalkan, atau tidak dapat diakses).';
        }

        return back()->with('success', $message);
    }

    public function sendWhatsapp(Request $request, Invoice $invoice): RedirectResponse
    {
        $user = $request->user();
        if ($user->isAgen() && $invoice->customer?->agent_id !== $user->id) {
            return back()->with('error', 'Anda tidak memiliki akses untuk mengirim WhatsApp tagihan ini.');
        }

        $validated = $request->validate([
            'template' => ['required', 'string', Rule::in(array_keys(MessageTemplate::manualChoices()))],
        ]);

        $result = $this->notifier->notifyManualWhatsapp($invoice, $validated['template']);
        $label = MessageTemplate::manualChoices()[$validated['template']];

        return back()->with(
            ($result['ok'] ?? false) ? 'success' : 'error',
            ($result['ok'] ?? false)
                ? 'WhatsApp "'.$label.'" terkirim ke '.$invoice->customer?->name.'.'
                : ($result['message'] ?? 'Gagal mengirim WhatsApp.')
        );
    }

    public function bulkSendWhatsapp(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:invoices,id'],
            'template' => ['required', 'string', Rule::in(array_keys(MessageTemplate::manualChoices()))],
        ]);

        $query = Invoice::query()
            ->with(['customer'])
            ->whereIn('id', $validated['ids']);

        if ($user->isAgen()) {
            $query->whereHas('customer', fn ($customer) => $customer->where('agent_id', $user->id));
        }

        $invoices = $query->get();
        $queued = 0;
        $failed = 0;
        $lastError = null;
        $defer = $invoices->count() > 1;

        foreach ($invoices as $invoice) {
            $result = $this->notifier->notifyManualWhatsapp($invoice, $validated['template'], $defer);
            if ($result['ok'] ?? false) {
                $queued++;
            } else {
                $failed++;
                $lastError = $result['message'] ?? 'Gagal mengirim';
            }
        }

        if ($queued > 0) {
            $this->notifier->dispatchWhatsappOutbox();
        }

        $skipped = count($validated['ids']) - $invoices->count();
        $label = MessageTemplate::manualChoices()[$validated['template']];

        if ($queued === 0) {
            $message = $lastError ?: 'Tidak ada WhatsApp yang terkirim.';
            if ($skipped > 0) {
                $message .= ' '.$skipped.' tagihan dilewati.';
            }

            return back()->with('error', $message);
        }

        $message = $defer
            ? $queued.' WhatsApp "'.$label.'" masuk antrian (dikirim bertahap dengan jeda acak).'
            : $queued.' WhatsApp "'.$label.'" terkirim.';
        if ($failed > 0) {
            $message .= ' '.$failed.' gagal.';
        }
        if ($skipped > 0) {
            $message .= ' '.$skipped.' dilewati.';
        }

        return back()->with('success', $message);
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

        return AdminListState::to('admin.billing.index', AdminListState::BILLING)
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

        return back()->with('success', "Tagihan {$voided->number} dibatalkan (void).");
    }

    public function grantGrace(Request $request, PppoeCustomer $pppoe): RedirectResponse
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', Rule::in([3, 7, 14])],
            'months' => ['nullable', 'integer', Rule::in([2])],
            'grace_until' => ['nullable', 'date', 'after_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if (
            empty($validated['days'])
            && empty($validated['months'])
            && empty($validated['grace_until'])
        ) {
            return back()->with('error', 'Pilih durasi toleransi atau tanggal akhir.');
        }

        if (! empty($validated['grace_until'])) {
            $until = Carbon::parse($validated['grace_until'])->startOfDay();
        } elseif (! empty($validated['months'])) {
            $until = now()->startOfDay()->addMonthsNoOverflow((int) $validated['months']);
        } else {
            $until = now()->startOfDay()->addDays((int) $validated['days']);
        }

        $wasIsolated = $pppoe->status === 'isolated';

        try {
            $updated = $this->billing->grantGrace(
                $pppoe,
                $until,
                $validated['note'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($updated->sync_status === 'error') {
            return back()->with(
                'error',
                'Toleransi tersimpan sampai '.$until->format('d M Y').', tetapi sync MikroTik gagal: '.
                ($updated->sync_message ?: 'tidak ada pesan.')
            );
        }

        $message = 'Toleransi isolir aktif sampai '.$until->format('d M Y').'. Jatuh tempo tagihan tidak digeser.';
        if ($wasIsolated) {
            $message .= ' Profil paket dipulihkan dan sesi diputus agar reconnect.';
        }

        return back()->with('success', $message);
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

        $wasIsolated = $pppoe->status === 'isolated' || $pppoe->isOverdue();

        try {
            $invoice = $this->billing->createCombinedMonthlyInvoice(
                $pppoe->load('package'),
                (int) ($validated['months'] ?? 2),
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $customer = $pppoe->fresh();
        $message = 'Tagihan gabungan '.$invoice->billing_months.' bulan dibuat: '.$invoice->number;

        if ($wasIsolated && $customer?->hasActiveGrace()) {
            $message .= ' Tempo isolir aktif sampai '.$customer->grace_until->format('d M Y').
                '. Profil paket dipulihkan (tagihan tetap belum lunas).';

            if ($customer->sync_status === 'error') {
                return back()->with(
                    'error',
                    $message.' Sync MikroTik gagal: '.($customer->sync_message ?: 'tidak ada pesan.')
                );
            }
        }

        return back()->with('success', $message);
    }
}
