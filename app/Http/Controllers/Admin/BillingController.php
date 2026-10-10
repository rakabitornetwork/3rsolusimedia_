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
use App\Support\PhoneNumber;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
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
            'q', 'status', 'overdue', 'grace', 'customer_status', 'router_id', 'sort', 'direction', 'page', 'per_page', 'hide_old_paid',
        ]);

        $user = $request->user();
        $routerId = $request->get('router_id', '');
        $sort = (string) $request->get('sort', 'due_date');
        $direction = strtolower((string) $request->get('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = (int) $request->integer('per_page', 20);
        if (! in_array($perPage, [20, 50, 100, 200, 500], true)) {
            $perPage = 20;
        }

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

        $query = $this->invoiceListQuery($request)->with(['customer.router']);

        if ($sort === 'customer') {
            $query->leftJoin(
                'pppoe_customers',
                'pppoe_customers.id',
                '=',
                'invoices.pppoe_customer_id'
            );
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

        $grace = (string) $request->get('grace', '');
        $customerStatus = (string) $request->get('customer_status', '');
        $hideOldPaid = $request->has('hide_old_paid')
            ? $request->boolean('hide_old_paid')
            : true;

        $collectedAmount = (int) $paymentMonthQuery->sum('amount');
        $unpaidTotal = (int) (clone $unpaidQuery)->sum('total');

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
                'per_page' => $perPage,
                'hide_old_paid' => $hideOldPaid,
            ],
            'routers' => MikrotikRouter::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'host']),
            'stats' => [
                'unpaid' => $unpaidQuery->count(),
                'unpaid_total' => $unpaidTotal,
                'unpaid_total_label' => 'Rp '.number_format($unpaidTotal, 0, ',', '.'),
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
            ],
            'billing_generate_days' => AppSettings::billingGenerateDays(),
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

        $replacementInvoice = null;
        if ($invoice->status === 'void' && $invoice->due_date) {
            $replacementInvoice = Invoice::query()
                ->where('pppoe_customer_id', $invoice->pppoe_customer_id)
                ->where('status', 'unpaid')
                ->whereDate('due_date', $invoice->due_date->toDateString())
                ->latest('id')
                ->first();
        }

        return Inertia::render('Admin/Billing/Show', [
            'invoice' => $invoice->toAdminArray(),
            'replacement_invoice' => $replacementInvoice?->toAdminArray(),
            'advance' => $this->advanceUi($invoice),
            'payment_methods' => [
                ['value' => 'cash', 'label' => 'Tunai'],
                ['value' => 'transfer', 'label' => 'Transfer'],
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
        if (! $user?->canRecordPayment()) {
            return back()->with('error', 'Akun Agen tidak dapat membuat link pembayaran.');
        }
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

    public function printCustomers(Request $request): HttpResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['unpaid', 'paid', 'void'])],
            'overdue' => ['nullable', 'boolean'],
            'grace' => ['nullable', Rule::in(['active', 'none'])],
            'customer_status' => ['nullable', Rule::in(['isolated'])],
            'router_id' => ['nullable', 'integer', 'exists:mikrotik_routers,id'],
            'hide_old_paid' => ['nullable', 'boolean'],
        ]);

        $invoices = $this->invoiceListQuery($request)
            ->with(['customer.package', 'customer.router'])
            ->orderBy('invoices.due_date')
            ->orderBy('invoices.id')
            ->get();

        $rows = $invoices
            ->filter(fn (Invoice $invoice) => $invoice->customer)
            ->groupBy('pppoe_customer_id')
            ->map(function ($group) {
                $invoice = $group->sortBy(fn (Invoice $item) => sprintf(
                    '%d-%s-%010d',
                    $item->status === 'unpaid' ? 0 : 1,
                    $item->due_date?->format('Y-m-d') ?? '9999-99-99',
                    $item->id
                ))->first();

                return [
                    'customer' => $invoice->customer,
                    'amount' => $invoice->total,
                    'due_date' => $invoice->due_date,
                    'agent_cash' => (bool) $invoice->agent_cash,
                    'agent_ready_tf' => (bool) $invoice->agent_ready_tf,
                ];
            })
            ->sortBy(fn (array $row) => mb_strtolower((string) $row['customer']->name), SORT_NATURAL)
            ->values();

        $router = $request->filled('router_id')
            ? MikrotikRouter::query()->find($request->integer('router_id'))
            : null;

        $totalAmount = $rows->sum(fn (array $row) => (int) ($row['amount'] ?? 0));

        return response()->view('admin.customers.pppoe-print', [
            'rows' => $rows,
            'total_amount' => $totalAmount,
            'date' => now(),
            'date_field' => 'due_date',
            'date_field_label' => 'Filter tagihan',
            'router' => $router,
            'status' => '',
            'list_title' => 'Daftar Tagihan Pelanggan',
            'page_title' => 'Cetak Pelanggan · Tagihan & Pembayaran',
            'filter_bits' => $this->billingPrintFilterBits($request, $router),
            'empty_message' => 'Tidak ada pelanggan untuk filter tagihan ini.',
            'company' => $this->companyPrintPayload(),
            'agent_marks' => (bool) $request->user()?->isAgen(),
        ]);
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
            'company' => $this->companyPrintPayload(),
        ]);
    }

    public function pay(Request $request, Invoice $invoice): RedirectResponse
    {
        $user = $request->user();
        if (! $user?->canRecordPayment()) {
            return back()->with('error', 'Akun Agen tidak dapat menandai tagihan lunas.');
        }
        if ($user->isAgen() && $invoice->customer?->agent_id !== $user->id) {
            return back()->with('error', 'Anda tidak memiliki akses untuk memproses pembayaran tagihan pelanggan ini.');
        }
        $validated = $request->validate([
            'method' => ['required', Rule::in(['cash', 'transfer'])],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
            'months' => ['nullable', 'integer', 'min:1', 'max:6'],
        ]);

        $months = (int) ($validated['months'] ?? 1);
        $target = $invoice;

        try {
            if (
                $months >= 2
                && $invoice->customer
                && $invoice->type !== 'vpn_router'
                && ! $invoice->vpn_router_id
            ) {
                $target = $this->billing->createAdvanceInvoice(
                    $invoice->customer->loadMissing('package'),
                    $months,
                );
            }

            $result = $this->billing->markPaid(
                invoice: $target,
                method: $validated['method'],
                reference: $validated['reference'] ?? null,
                notes: $validated['notes'] ?? null,
                receivedBy: $request->user()?->id,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $windowDays = AppSettings::billingGenerateDays();
        $paidMonths = max(1, (int) ($result['invoice']->billing_months ?: 1));
        $message = 'Pembayaran berhasil. Tagihan '.$result['invoice']->number.' lunas';
        if ($paidMonths > 1) {
            $message .= ' untuk '.$paidMonths.' bulan';
        }
        $message .= '.';
        if ($result['next_due_date']) {
            $message .= ' Jatuh tempo berikutnya: '.$result['next_due_date'].
                ". Tagihan baru akan muncul {$windowDays} hari sebelum tanggal tersebut.";
        }

        return back()->with('success', $message);
    }

    public function updateAgentMarks(Request $request, Invoice $invoice): RedirectResponse
    {
        $user = $request->user();
        if (! $user?->isAgen()) {
            return back()->with('error', 'Hanya akun Agen yang dapat menandai Cash / Siap TF.');
        }

        $invoice->loadMissing('customer');
        if ($invoice->customer?->agent_id !== $user->id) {
            return back()->with('error', 'Anda tidak memiliki akses ke tagihan pelanggan ini.');
        }

        $payload = [];
        if ($request->exists('agent_cash')) {
            $payload['agent_cash'] = $request->boolean('agent_cash');
        }
        if ($request->exists('agent_ready_tf')) {
            $payload['agent_ready_tf'] = $request->boolean('agent_ready_tf');
        }

        if ($payload === []) {
            return back();
        }

        $previous = [
            'agent_cash' => (bool) $invoice->agent_cash,
            'agent_ready_tf' => (bool) $invoice->agent_ready_tf,
        ];

        $invoice->update($payload);

        $checked = [];
        foreach ($payload as $field => $value) {
            if ($value && ! $previous[$field]) {
                $checked[] = $field;
            }
        }

        if ($checked !== []) {
            $this->notifier->notifyAdminAgentCollection(
                $invoice->fresh(['customer.router', 'customer.package']),
                $user,
                $checked,
            );
        }

        return back();
    }

    public function bulkPay(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user?->canRecordPayment()) {
            return back()->with('error', 'Akun Agen tidak dapat menandai tagihan lunas.');
        }
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:invoices,id'],
            'method' => ['required', Rule::in(['cash', 'transfer'])],
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

    public function prepare(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['nullable', 'string', 'max:120', 'required_without:customer_id'],
            'customer_id' => ['nullable', 'integer', 'required_without:username'],
            'months' => ['nullable', 'integer', 'min:1', 'max:6'],
        ]);

        $term = trim((string) ($validated['username'] ?? ''));
        $months = (int) ($validated['months'] ?? 1);
        $scoped = $this->earlyPaymentCustomers($request);

        if (! empty($validated['customer_id'])) {
            $customer = (clone $scoped)->whereKey($validated['customer_id'])->first();
            if (! $customer) {
                return back()->with('error', 'Pelanggan tidak ditemukan.');
            }

            return $this->prepareEarlyInvoice($customer, $months);
        }

        $matches = $this->matchEarlyCustomers($scoped, $term);

        if ($matches->isEmpty()) {
            return back()->with('error', 'Pelanggan tidak ditemukan. Gunakan username, nama lengkap, atau nomor telepon.');
        }

        if ($matches->count() > 1) {
            return back()
                ->with('early_query', $term)
                ->with('early_months', $months)
                ->with('early_customers', $matches
                    ->map(fn (PppoeCustomer $customer) => $this->earlyCustomerChoice($customer))
                    ->values()
                    ->all());
        }

        return $this->prepareEarlyInvoice($matches->first(), $months);
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
            $result = $this->billing->voidInvoice($invoice, $validated['notes'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $voided = $result['invoice'];
        $replacement = $result['replacement'];
        $customer = $voided->customer?->fresh();

        $message = "Tagihan {$voided->number} dibatalkan (void).";
        if ($replacement && $result['replacement_created']) {
            $message .= " Tagihan baru {$replacement->number} dibuat.";
        } elseif ($replacement) {
            $message .= " Tagihan belum bayar {$replacement->number} untuk periode yang sama sudah ada.";
        }
        if ($customer?->status === 'isolated') {
            $message .= ' Pelanggan langsung diisolir.';
        }

        if ($replacement) {
            $this->rememberBillingUnpaidFilter($request);

            return redirect()
                ->route('admin.billing.show', $replacement)
                ->with('success', $message);
        }

        return back()->with('success', $message);
    }

    private function denyGrace(Request $request, PppoeCustomer $pppoe): ?RedirectResponse
    {
        $user = $request->user();

        if (! $user?->canGrantGrace()) {
            return back()->with('error', 'Akun ini tidak dapat mengatur toleransi isolir.');
        }

        if ($user->isAgen() && (int) $pppoe->agent_id !== (int) $user->id) {
            return back()->with('error', 'Anda tidak memiliki akses untuk mengatur toleransi pelanggan ini.');
        }

        return null;
    }

    private function rememberBillingUnpaidFilter(Request $request): void
    {
        $key = AdminListState::sessionKey(AdminListState::BILLING);
        $saved = $request->session()->get($key, []);
        if (! is_array($saved)) {
            $saved = [];
        }
        $saved['status'] = 'unpaid';
        $request->session()->put($key, $saved);
    }

    public function grantGrace(Request $request, PppoeCustomer $pppoe): RedirectResponse
    {
        if ($denied = $this->denyGrace($request, $pppoe)) {
            return $denied;
        }

        $validated = $request->validate([
            'days' => ['nullable', 'integer', Rule::in([3, 7, 14])],
            'months' => ['nullable', 'integer', Rule::in([1, 2])],
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

    public function clearGrace(Request $request, PppoeCustomer $pppoe): RedirectResponse
    {
        if ($denied = $this->denyGrace($request, $pppoe)) {
            return $denied;
        }

        $this->billing->clearGrace($pppoe);

        return back()->with('success', 'Toleransi isolir dicabut.');
    }

    public function combineBilling(Request $request, PppoeCustomer $pppoe): RedirectResponse
    {
        $user = $request->user();
        if ($user?->isAgen() && (int) $pppoe->agent_id !== (int) $user->id) {
            return back()->with('error', 'Anda tidak memiliki akses untuk menagih pelanggan ini.');
        }

        $validated = $request->validate([
            'months' => ['nullable', 'integer', 'min:2', 'max:6'],
        ]);

        $wasIsolated = $pppoe->status === 'isolated' || $pppoe->isOverdue();

        try {
            $invoice = $this->billing->createAdvanceInvoice(
                $pppoe->load('package'),
                (int) ($validated['months'] ?? 2),
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $customer = $pppoe->fresh();
        $nextDue = $this->billing->projectedDueAfterPayment($invoice->loadMissing('customer'));
        $message = 'Tagihan '.$invoice->billing_months.' bulan dibuat: '.$invoice->number.
            ' (Rp '.number_format((int) $invoice->total, 0, ',', '.').')';
        if ($nextDue) {
            $message .= '. Setelah lunas, jatuh tempo menjadi '.Carbon::parse($nextDue)->format('d/m/Y');
        }

        if ($wasIsolated && $customer?->hasActiveGrace()) {
            $message .= ' Tempo isolir aktif sampai '.$customer->grace_until->format('d M Y').
                '. Profil paket dipulihkan (tagihan tetap belum lunas).';

            if ($customer->sync_status === 'error') {
                return redirect()
                    ->route('admin.billing.show', $invoice)
                    ->with(
                        'error',
                        $message.' Sync MikroTik gagal: '.($customer->sync_message ?: 'tidak ada pesan.')
                    );
            }
        }

        return redirect()
            ->route('admin.billing.show', $invoice)
            ->with('success', $message);
    }

    /**
     * @return array{blocked: ?string, options: list<array<string, mixed>>, next_due_date: ?string}|null
     */
    private function advanceUi(Invoice $invoice): ?array
    {
        $customer = $invoice->customer;
        if (! $customer || $invoice->type === 'vpn_router' || $invoice->vpn_router_id) {
            return null;
        }

        $nextDue = $invoice->isUnpaid()
            ? $this->billing->projectedDueAfterPayment($invoice)
            : null;

        if (! $invoice->isUnpaid() || (int) ($invoice->billing_months ?: 1) > 1 || $invoice->type === 'multi_month') {
            return [
                'blocked' => null,
                'options' => [],
                'next_due_date' => $nextDue,
            ];
        }

        $choices = $this->billing->advancePaymentChoices($customer);

        return [
            'blocked' => $choices['blocked'],
            'options' => $choices['options'],
            'next_due_date' => $nextDue,
        ];
    }

    private function earlyPaymentCustomers(Request $request): Builder
    {
        $customers = PppoeCustomer::query()->with(['package', 'router']);

        if ($request->user()?->isAgen()) {
            $customers->where('agent_id', $request->user()->id);
        }

        return $customers;
    }

    /**
     * Username, nama lengkap, dan telepon digabung. Lebih dari satu hasil
     * tidak langsung ditagih — admin memilih pelanggan yang tepat.
     */
    private function matchEarlyCustomers(Builder $scoped, string $term): \Illuminate\Support\Collection
    {
        $needle = mb_strtolower($term);
        $usernameHits = (clone $scoped)->whereRaw('LOWER(username) = ?', [$needle])->get();
        $nameHits = (clone $scoped)->whereRaw('LOWER(name) = ?', [$needle])->get();

        $phoneHits = collect();
        $digits = PhoneNumber::normalize($term);
        if (strlen($digits) >= 8) {
            $phoneHits = (clone $scoped)
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->get()
                ->filter(fn (PppoeCustomer $customer) => PhoneNumber::normalize((string) $customer->phone) === $digits);
        }

        return $usernameHits
            ->concat($nameHits)
            ->concat($phoneHits)
            ->unique('id')
            ->sortBy(fn (PppoeCustomer $customer) => mb_strtolower($customer->username))
            ->values();
    }

    /**
     * @return array{id: int, name: string, username: string, phone: ?string, address: ?string, router: ?string, package: ?string, due_date: ?string, status: ?string, is_active: bool}
     */
    private function earlyCustomerChoice(PppoeCustomer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'username' => $customer->username,
            'phone' => $customer->phone,
            'address' => $customer->address,
            'router' => $customer->router?->name,
            'package' => $customer->package?->name,
            'due_date' => $customer->due_date?->format('d/m/Y'),
            'status' => $customer->status,
            'is_active' => (bool) $customer->is_active,
        ];
    }

    private function prepareEarlyInvoice(PppoeCustomer $customer, int $months = 1): RedirectResponse
    {
        if (! $customer->is_active) {
            return back()->with('error', 'Pelanggan '.$customer->name.' ('.$customer->username.') nonaktif. Tagihan tidak dibuat.');
        }

        if ($months >= 2) {
            try {
                $invoice = $this->billing->createAdvanceInvoice($customer->loadMissing('package'), $months);
            } catch (InvalidArgumentException $e) {
                return back()->with('error', $e->getMessage());
            }

            $who = $customer->name.' ('.$customer->username.')';
            $nextDue = $this->billing->projectedDueAfterPayment($invoice->loadMissing('customer'));
            $message = 'Tagihan '.$invoice->number.' untuk '.$months.' bulan disiapkan bagi '.$who.
                ' (Rp '.number_format((int) $invoice->total, 0, ',', '.').')';
            if ($nextDue) {
                $message .= '. Setelah lunas, jatuh tempo menjadi '.Carbon::parse($nextDue)->format('d/m/Y').'.';
            }

            return redirect()
                ->route('admin.billing.show', $invoice)
                ->with('success', $message);
        }

        $result = $this->billing->ensurePayableInvoice($customer);
        $invoice = $result['invoice'];

        if (! $invoice) {
            $due = $customer->due_date?->format('d/m/Y');
            $windowDays = AppSettings::billingGenerateDays();
            $who = $customer->name.' ('.$customer->username.')';

            if (! $this->billing->currentCycleHasStarted($customer)) {
                $when = $due ? "Jatuh tempo {$due} " : 'Tagihan ';

                return back()->with(
                    'error',
                    "{$when}untuk {$who} belum masuk periode berjalan, jadi tagihan belum bisa dibuat. Tagihan otomatis muncul {$windowDays} hari sebelum jatuh tempo."
                );
            }

            return back()->with(
                'error',
                'Tagihan periode ini untuk '.$who.' tidak bisa dibuat. Cek harga paket, atau tagihan jatuh tempo yang sama sudah lunas.'
            );
        }

        $who = $customer->name.' ('.$customer->username.')';
        $message = $result['created']
            ? 'Tagihan '.$invoice->number.' disiapkan untuk '.$who.'.'
            : 'Tagihan '.$invoice->number.' untuk '.$who.' sudah ada.';

        return redirect()
            ->route('admin.billing.show', $invoice)
            ->with('success', $message);
    }

    private function invoiceListQuery(Request $request): Builder
    {
        $user = $request->user();
        $query = Invoice::query()->select('invoices.*');

        if ($user?->isAgen()) {
            $query->whereHas('customer', fn ($c) => $c->where('agent_id', $user->id));
        }

        if ($routerId = $request->get('router_id')) {
            $query->whereHas('customer', fn ($c) => $c->where('mikrotik_router_id', $routerId));
        }

        if ($status = $request->get('status')) {
            $query->where('invoices.status', $status);
        } else {
            // Tagihan dibatalkan tetap tersimpan, tetapi tidak dicampur ke daftar kerja.
            $query->where('invoices.status', '<>', 'void');
        }

        $hideOldPaid = $request->has('hide_old_paid')
            ? $request->boolean('hide_old_paid')
            : true;

        if ($hideOldPaid) {
            $monthStart = now()->startOfMonth()->toDateString();
            $query->where(function ($builder) use ($monthStart) {
                $builder->where('invoices.status', '<>', 'paid')
                    ->orWhereDate('invoices.paid_at', '>=', $monthStart)
                    ->orWhere(function ($fallback) use ($monthStart) {
                        $fallback->where('invoices.status', 'paid')
                            ->whereNull('invoices.paid_at')
                            ->whereDate('invoices.due_date', '>=', $monthStart);
                    });
            });
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

        if ($request->get('customer_status') === 'isolated') {
            $query->whereHas('customer', fn ($customer) => $customer->where('status', 'isolated'));
        }

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($builder) use ($like) {
                $builder->where('invoices.number', 'like', $like)
                    ->orWhere('invoices.package_name', 'like', $like)
                    ->orWhereHas('customer', function ($customer) use ($like) {
                        $customer->where('name', 'like', $like)
                            ->orWhere('username', 'like', $like)
                            ->orWhere('phone', 'like', $like);
                    });
            });
        }

        return $query;
    }

    private function billingPrintFilterBits(Request $request, ?MikrotikRouter $router): string
    {
        $statusLabels = [
            'unpaid' => 'Belum bayar',
            'paid' => 'Lunas',
            'void' => 'Dibatalkan',
        ];

        return collect([
            $router?->name ? 'Router '.$router->name : null,
            ($status = $request->get('status')) ? ($statusLabels[$status] ?? $status) : null,
            $request->boolean('overdue') ? 'Jatuh tempo saja' : null,
            $request->get('grace') === 'active' ? 'Grace aktif' : null,
            $request->get('grace') === 'none' ? 'Tanpa grace' : null,
            $request->get('customer_status') === 'isolated' ? 'Isolir' : null,
            ($request->has('hide_old_paid') ? $request->boolean('hide_old_paid') : true)
                ? 'Sembunyikan lunas bulan lalu'
                : null,
            $request->get('status') ? null : 'Sembunyikan yang dibatalkan',
            ($q = trim((string) $request->get('q', ''))) !== '' ? 'Cari “'.$q.'”' : null,
        ])->filter()->implode(' · ') ?: 'Semua tagihan';
    }

    /**
     * @return array{name: string, logo: mixed, address: string, phone: string, whatsapp: string, tagline: string}
     */
    private function companyPrintPayload(): array
    {
        return [
            'name' => AppSettings::companyName(),
            'logo' => AppSettings::branding()['logo_mark'] ?? AppSettings::branding()['logo_full'],
            'address' => trim((string) SiteSetting::getValue('address', '')),
            'phone' => trim((string) SiteSetting::getValue('phone', '')),
            'whatsapp' => trim((string) SiteSetting::getValue('whatsapp', '')),
            'tagline' => trim((string) SiteSetting::getValue('tagline', '')),
        ];
    }
}
