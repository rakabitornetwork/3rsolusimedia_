<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PppoeCustomer;
use App\Services\Messaging\CustomerNotifier;
use App\Support\AppSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BillingService
{
    /** Fallback jika pengaturan aplikasi belum tersedia. */
    public const UPCOMING_WINDOW_DAYS = 7;

    public function __construct(
        private readonly BillingCycleService $cycle,
        private readonly PppoeSyncService $sync,
        private readonly CustomerNotifier $notifier,
    ) {}

    public function createProrataInvoice(PppoeCustomer $customer): ?Invoice
    {
        $customer->loadMissing('package');

        $amount = (int) ($customer->first_bill_amount ?? 0);
        if ($amount <= 0 || ! $customer->due_date || ! $customer->start_date) {
            return null;
        }

        // Jangan buat ulang: sudah ada prorata (termasuk void diganti gabungan),
        // atau siklus pertama sudah pernah dilunasi.
        if ($this->hasProrataInvoiceHistory($customer) || $this->hasPaidInvoice($customer)) {
            return null;
        }

        // Periode pertama: pakai due yang dipilih di form, bukan "hari tagihan berikutnya".
        $firstDue = $this->cycle->firstDueDate(
            $customer->start_date,
            (int) $customer->billing_day,
            $customer->due_date,
        );

        return $this->createInvoice(
            customer: $customer,
            type: 'prorata',
            periodStart: $customer->start_date->toDateString(),
            periodEnd: $firstDue->toDateString(),
            dueDate: $firstDue->toDateString(),
            amount: $amount,
            notes: 'Tagihan pertama (prorata)',
            notify: false,
        );
    }

    /**
     * Samakan invoice prorata unpaid dengan hitungan pelanggan terkini
     * (setelah ubah start_date / billing_day / paket).
     * Tidak dijalankan setelah siklus pertama selesai (sudah ada pembayaran).
     */
    public function syncUnpaidProrataInvoice(PppoeCustomer $customer): ?Invoice
    {
        $customer->loadMissing('package');

        if ($this->hasPaidInvoice($customer)) {
            return null;
        }

        $invoice = Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('type', 'prorata')
            ->where('status', 'unpaid')
            ->latest('id')
            ->first();

        if (! $invoice) {
            return null;
        }

        $amount = (int) ($customer->first_bill_amount ?? 0);
        if ($amount <= 0 || ! $customer->start_date) {
            return $invoice;
        }

        // Samakan ke due yang tersimpan (tanggal lengkap dari form),
        // bukan nextDueDate yang bisa loncat ke bulan depan.
        $firstDue = $this->cycle->firstDueDate(
            $customer->start_date,
            (int) $customer->billing_day,
            $customer->due_date,
        );

        $invoice->update([
            'subscription_package_id' => $customer->subscription_package_id,
            'period_start' => $customer->start_date->toDateString(),
            'period_end' => $firstDue->toDateString(),
            'due_date' => $firstDue->toDateString(),
            'amount' => $amount,
            'discount' => 0,
            'total' => $amount,
            'package_name' => $customer->package?->name,
            'package_price' => $customer->package?->price,
            'notes' => 'Tagihan pertama (prorata)',
        ]);

        return $invoice->fresh();
    }

    private function alignUnpaidInvoice(PppoeCustomer $customer, Invoice $invoice): Invoice
    {
        if ($invoice->type === 'multi_month') {
            return $invoice;
        }

        $due = $customer->due_date?->toDateString();
        if (! $due) {
            return $invoice;
        }

        if ($invoice->type === 'prorata' && ! $this->hasPaidInvoice($customer)) {
            return $this->syncUnpaidProrataInvoice($customer) ?? $invoice;
        }

        if ($invoice->due_date?->toDateString() === $due) {
            return $invoice;
        }

        $invoice->update([
            'due_date' => $due,
            'period_end' => $due,
            'period_start' => $this->periodStartBeforeDue($customer),
        ]);

        return $invoice->fresh();
    }

    private function createInvoiceForCurrentDue(PppoeCustomer $customer, bool $notify = false): ?Invoice
    {
        if (
            ! $this->hasCompletedFirstBillingCycle($customer)
            && (int) ($customer->first_bill_amount ?? 0) > 0
        ) {
            $invoice = $this->createProrataInvoice($customer);
            if ($invoice) {
                return $invoice;
            }
        }

        $amount = $this->hasCompletedFirstBillingCycle($customer)
            ? (int) ($customer->package?->price ?? 0)
            : (int) (($customer->first_bill_amount ?: $customer->package?->price) ?? 0);

        if ($amount <= 0) {
            return null;
        }

        $type = $this->hasCompletedFirstBillingCycle($customer) ? 'monthly' : 'prorata';

        return $this->createInvoice(
            customer: $customer,
            type: $type,
            periodStart: $type === 'prorata' && $customer->start_date
                ? $customer->start_date->toDateString()
                : $this->periodStartBeforeDue($customer),
            periodEnd: $customer->due_date->toDateString(),
            dueDate: $customer->due_date->toDateString(),
            amount: $amount,
            notes: $type === 'prorata' ? 'Tagihan pertama (prorata)' : 'Tagihan bulanan',
            notify: $notify,
        );
    }

    public function hasPaidInvoice(PppoeCustomer $customer): bool
    {
        return Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('status', 'paid')
            ->exists();
    }

    public function hasProrataInvoiceHistory(PppoeCustomer $customer): bool
    {
        return Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('type', 'prorata')
            ->exists();
    }

    /**
     * Siklus pertama selesai: sudah ada pembayaran, atau prorata diganti (void).
     */
    public function hasCompletedFirstBillingCycle(PppoeCustomer $customer): bool
    {
        if ($this->hasPaidInvoice($customer)) {
            return true;
        }

        return Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('type', 'prorata')
            ->where('status', 'void')
            ->exists();
    }

    /**
     * Samakan / buat tagihan terbuka agar cocok dengan due_date pelanggan.
     * Dipakai saat admin mengubah siklus tagihan, dan saat generate otomatis.
     *
     * @return array{invoice: ?Invoice, created: bool}
     */
    public function ensureOpenInvoice(PppoeCustomer $customer, bool $notify = false): array
    {
        $customer->loadMissing('package');

        if (! $customer->is_active || ! $customer->due_date) {
            return ['invoice' => null, 'created' => false];
        }

        $unpaid = Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('status', 'unpaid')
            ->latest('id')
            ->first();

        if ($unpaid) {
            return [
                'invoice' => $this->alignUnpaidInvoice($customer, $unpaid),
                'created' => false,
            ];
        }

        if (! $this->isWithinUpcomingWindow($customer->due_date)) {
            return ['invoice' => null, 'created' => false];
        }

        $existsForDue = Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->whereDate('due_date', $customer->due_date->toDateString())
            ->whereIn('status', ['unpaid', 'paid'])
            ->exists();

        if ($existsForDue) {
            return ['invoice' => null, 'created' => false];
        }

        $invoice = $this->createInvoiceForCurrentDue($customer, $notify);

        return ['invoice' => $invoice, 'created' => $invoice !== null];
    }

    /**
     * Buat tagihan bulanan terbuka hanya jika jatuh tempo ≤ 7 hari (atau sudah lewat).
     *
     * @return array{created: int, skipped: int}
     */
    public function generateOpenInvoices(): array
    {
        $created = 0;
        $skipped = 0;

        $customers = PppoeCustomer::query()
            ->with('package')
            ->where('is_active', true)
            ->whereNotNull('due_date')
            ->get();

        foreach ($customers as $customer) {
            $result = $this->ensureOpenInvoice($customer, notify: true);
            if ($result['created']) {
                $created++;
            } else {
                $skipped++;
            }
        }

        if ($created > 0) {
            $this->notifier->dispatchWhatsappOutbox();
        }

        return compact('created', 'skipped');
    }

    /**
     * @return array{invoice: Invoice, payment: Payment, next_due_date: ?string}
     */
    public function markPaid(
        Invoice $invoice,
        string $method = 'cash',
        ?string $reference = null,
        ?string $notes = null,
        ?int $receivedBy = null,
        ?Carbon $paidAt = null,
    ): array {
        if (! $invoice->isUnpaid()) {
            throw new InvalidArgumentException('Tagihan ini sudah tidak berstatus belum bayar.');
        }

        $paidAt ??= now();

        $result = DB::transaction(function () use ($invoice, $method, $reference, $notes, $receivedBy, $paidAt) {
            $invoice->loadMissing(['customer.agent', 'customer.package']);

            $customer = $invoice->customer;
            $agent = $customer?->agent;
            $agentId = null;
            $agentCommission = 0;

            if ($agent?->isAgen()) {
                $agentId = $agent->id;
                if ($customer?->agent_pays_commission) {
                    $agentCommission = max(0, (int) ($agent->billing_commission ?? 0));
                }
            }

            $payment = Payment::query()->create([
                'invoice_id' => $invoice->id,
                'received_by' => $receivedBy,
                'agent_id' => $agentId,
                'agent_commission' => $agentCommission,
                'amount' => $invoice->total,
                'method' => $method,
                'paid_at' => $paidAt,
                'reference' => $reference,
                'notes' => $notes,
            ]);

            $invoice->update([
                'status' => 'paid',
                'paid_at' => $paidAt,
            ]);

            $customer = $invoice->customer;
            $nextDueDate = null;

            if ($customer) {
                $months = max(1, (int) ($invoice->billing_months ?: 1));
                $nextDueDate = $this->cycle->dueDateAfterPayment(
                    $invoice->due_date,
                    (int) $customer->billing_day,
                    $months,
                )->toDateString();

                $customer->update([
                    'due_date' => $nextDueDate,
                    'grace_until' => null,
                    'grace_note' => null,
                ]);

                // Tagihan berikutnya tidak dibuat di sini — baru muncul
                // lewat generate saat jatuh tempo ≤ 7 hari.

                try {
                    $this->sync->sync($customer->fresh(['router', 'package']));
                } catch (\Throwable) {
                    // Pembayaran tetap sah meski sync RouterOS gagal.
                }
            }

            return [
                'invoice' => $invoice->fresh(['customer', 'payments.receiver', 'package']),
                'payment' => $payment->load('receiver'),
                'next_due_date' => $nextDueDate,
            ];
        });

        if ($result['invoice']->customer) {
            try {
                $this->notifier->notifyPaid($result['invoice']);
            } catch (\Throwable) {
                // Pelunasan tetap sah meski WhatsApp gagal.
            }
        }

        return $result;
    }

    /**
     * Tandai lunas beberapa tagihan sekaligus. Tagihan yang bukan unpaid dilewati.
     *
     * @param  list<int>  $invoiceIds
     * @return array{paid: int, skipped: int, numbers: list<string>}
     */
    public function markPaidMany(
        array $invoiceIds,
        string $method = 'cash',
        ?string $reference = null,
        ?string $notes = null,
        ?int $receivedBy = null,
        ?int $agentId = null,
    ): array {
        $ids = array_values(array_unique(array_map('intval', $invoiceIds)));

        $query = Invoice::query()
            ->with(['customer'])
            ->whereIn('id', $ids);

        if ($agentId) {
            $query->whereHas('customer', fn ($customer) => $customer->where('agent_id', $agentId));
        }

        $invoices = $query->get()->keyBy('id');

        $paid = 0;
        $skipped = count($ids) - $invoices->count();
        $numbers = [];

        foreach ($ids as $id) {
            $invoice = $invoices->get($id);
            if (! $invoice) {
                continue;
            }

            try {
                $result = $this->markPaid(
                    invoice: $invoice,
                    method: $method,
                    reference: $reference,
                    notes: $notes,
                    receivedBy: $receivedBy,
                );
                $paid++;
                $numbers[] = $result['invoice']->number;
            } catch (InvalidArgumentException) {
                $skipped++;
            }
        }

        return compact('paid', 'skipped', 'numbers');
    }

    public function isWithinUpcomingWindow(Carbon|string $dueDate): bool
    {
        $due = Carbon::parse($dueDate)->startOfDay();
        $today = now()->startOfDay();
        $windowDays = AppSettings::billingGenerateDays();
        $windowEnd = $today->copy()->addDays($windowDays);

        // Sudah lewat tempo atau dalam jendela generate yang dikonfigurasi.
        return $due->lessThanOrEqualTo($windowEnd);
    }

    /**
     * Hapus tagihan belum bayar atau yang sudah dibatalkan (void).
     * Tagihan berstatus lunas harus di-void dulu sebelum bisa dihapus.
     */
    public function deleteInvoice(Invoice $invoice): void
    {
        if ($invoice->status === 'paid') {
            throw new InvalidArgumentException(
                'Tagihan yang masih berstatus lunas tidak bisa dihapus. Batalkan (void) dulu, lalu hapus.'
            );
        }

        if (! in_array($invoice->status, ['unpaid', 'void'], true)) {
            throw new InvalidArgumentException('Status tagihan tidak memungkinkan untuk dihapus.');
        }

        // Hapus payment terkait dulu (jika void dari tagihan lunas), lalu invoice.
        $invoice->payments()->delete();
        $invoice->delete();
    }

    /**
     * Batalkan tagihan tanpa menghapus riwayat (termasuk yang sudah lunas).
     *
     * Tagihan unpaid: hanya status void (dipakai saat ganti ke tagihan gabungan).
     * Tagihan lunas: due_date dikembalikan, invoice unpaid pengganti dibuat,
     * dan sync isolir dijalankan segera.
     *
     * @return array{invoice: Invoice, replacement: ?Invoice, replacement_created: bool}
     */
    public function voidInvoice(Invoice $invoice, ?string $notes = null): array
    {
        if ($invoice->status === 'void') {
            throw new InvalidArgumentException('Tagihan ini sudah dibatalkan.');
        }

        $wasPaid = $invoice->status === 'paid';

        $result = DB::transaction(function () use ($invoice, $notes, $wasPaid) {
            $invoice->update([
                'status' => 'void',
                'notes' => trim(($invoice->notes ? $invoice->notes."\n" : '').($notes ?: 'Dibatalkan dari admin.')),
            ]);

            $replacement = null;
            $replacementCreated = false;
            if ($wasPaid) {
                $invoice->loadMissing('customer');
                $this->restoreCustomerDueAfterVoidedPayment($invoice);
                $resolved = $this->resolveReplacementInvoice($invoice);
                $replacement = $resolved['invoice'];
                $replacementCreated = $resolved['created'];
            }

            return [
                'invoice' => $invoice->fresh(['customer', 'payments.receiver', 'package']),
                'replacement' => $replacement?->fresh(['customer', 'package']),
                'replacement_created' => $replacementCreated,
            ];
        });

        if ($wasPaid && $result['invoice']->customer) {
            try {
                $this->sync->sync($result['invoice']->customer->fresh(['router', 'package']));
            } catch (\Throwable) {
                // Void tetap sah meski sync RouterOS gagal.
            }
        }

        if ($result['replacement_created'] && $result['replacement']) {
            try {
                $this->notifier->notifyInvoice($result['replacement']->loadMissing('customer'));
            } catch (\Throwable) {
                // Invoice pengganti tetap sah meski WhatsApp gagal.
            }
        }

        return [
            'invoice' => $result['invoice']->fresh(['customer', 'payments.receiver', 'package']),
            'replacement' => $result['replacement']?->fresh(['customer', 'package']),
            'replacement_created' => (bool) $result['replacement_created'],
        ];
    }

    /**
     * Kembalikan due_date ke periode tagihan yang dibatalkan, kecuali masih ada
     * tagihan lunas untuk tempo yang lebih baru.
     */
    private function restoreCustomerDueAfterVoidedPayment(Invoice $voided): void
    {
        $customer = $voided->customer;
        if (! $customer || ! $voided->due_date) {
            return;
        }

        $latestRemaining = Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('status', 'paid')
            ->where('id', '!=', $voided->id)
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->first();

        if ($latestRemaining?->due_date?->greaterThan($voided->due_date)) {
            return;
        }

        if ($latestRemaining?->due_date) {
            $restoredDue = $latestRemaining->due_date->copy()->startOfDay();
            $months = max(1, (int) ($latestRemaining->billing_months ?: 1));
            $billingDay = (int) $customer->billing_day;
            for ($i = 0; $i < $months; $i++) {
                $restoredDue = $this->cycle->advanceDueDate($restoredDue, $billingDay);
            }
        } else {
            $restoredDue = $voided->due_date->copy()->startOfDay();
        }

        $customer->update([
            'due_date' => $restoredDue->toDateString(),
            'grace_until' => null,
            'grace_note' => null,
        ]);
    }

    /**
     * @return array{invoice: Invoice, created: bool}
     */
    private function resolveReplacementInvoice(Invoice $voided): array
    {
        $customer = $voided->customer;
        if (! $customer) {
            throw new InvalidArgumentException('Tagihan tidak terkait pelanggan, tidak bisa dibuat ulang.');
        }

        if (! $voided->due_date) {
            throw new InvalidArgumentException('Tagihan tidak punya jatuh tempo, tidak bisa dibuat ulang.');
        }

        $existing = Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('status', 'unpaid')
            ->whereDate('due_date', $voided->due_date->toDateString())
            ->latest('id')
            ->first();

        if ($existing) {
            return ['invoice' => $existing, 'created' => false];
        }

        if (! $voided->period_start || ! $voided->period_end) {
            throw new InvalidArgumentException('Tagihan tidak punya periode lengkap, tidak bisa dibuat ulang.');
        }

        $invoice = Invoice::query()->create([
            'number' => $this->nextNumber(),
            'pppoe_customer_id' => $customer->id,
            'subscription_package_id' => $voided->subscription_package_id,
            'type' => $voided->type,
            'billing_months' => max(1, (int) ($voided->billing_months ?: 1)),
            'period_start' => $voided->period_start->toDateString(),
            'period_end' => $voided->period_end->toDateString(),
            'due_date' => $voided->due_date->toDateString(),
            'amount' => (int) $voided->amount,
            'discount' => (int) $voided->discount,
            'total' => (int) $voided->total,
            'status' => 'unpaid',
            'package_name' => $voided->package_name,
            'package_price' => $voided->package_price,
            'notes' => 'Pengganti tagihan '.$voided->number.' yang dibatalkan.',
        ]);

        return ['invoice' => $invoice, 'created' => true];
    }

    public function grantGrace(PppoeCustomer $customer, Carbon $until, ?string $note = null): PppoeCustomer
    {
        $until = $until->copy()->startOfDay();
        $today = now()->startOfDay();

        if ($until->lessThan($today)) {
            throw new InvalidArgumentException('Tanggal toleransi tidak boleh sebelum hari ini.');
        }

        $customer->update([
            'grace_until' => $until->toDateString(),
            'grace_note' => $note !== null && trim($note) !== '' ? trim($note) : null,
        ]);

        try {
            $this->sync->sync($customer->fresh(['router', 'package']));
        } catch (\Throwable) {
            // Toleransi tetap tersimpan meski sync RouterOS gagal.
        }

        return $customer->fresh(['router', 'package']);
    }

    public function clearGrace(PppoeCustomer $customer, bool $sync = true): PppoeCustomer
    {
        $customer->update([
            'grace_until' => null,
            'grace_note' => null,
        ]);

        if ($sync) {
            try {
                $this->sync->sync($customer->fresh(['router', 'package']));
            } catch (\Throwable) {
                // Cabut grace tetap tersimpan meski sync gagal.
            }
        }

        return $customer->fresh(['router', 'package']);
    }

    /**
     * Buat tagihan gabungan N bulan (default 2).
     * Invoice unpaid bulanan/prorata untuk due yang sama diganti (void) agar tidak dobel.
     */
    public function createCombinedMonthlyInvoice(PppoeCustomer $customer, int $months = 2): Invoice
    {
        $months = max(2, min(6, $months));
        $customer->loadMissing('package');

        $price = (int) ($customer->package?->price ?? 0);
        if ($price <= 0) {
            throw new InvalidArgumentException('Pelanggan belum punya paket berharga valid.');
        }

        if (! $customer->due_date) {
            throw new InvalidArgumentException('Pelanggan belum punya tanggal jatuh tempo.');
        }

        $unpaid = Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('status', 'unpaid')
            ->get();

        foreach ($unpaid as $existing) {
            if ((int) ($existing->billing_months ?: 1) > 1 || $existing->type === 'multi_month') {
                throw new InvalidArgumentException(
                    'Sudah ada tagihan gabungan yang belum dibayar. Lunasi atau batalkan dulu.'
                );
            }
        }

        foreach ($unpaid as $existing) {
            $this->voidInvoice(
                $existing,
                'Diganti tagihan gabungan '.$months.' bulan.'
            );
        }

        $due = $customer->due_date->copy()->startOfDay();
        $periodEnd = $due->copy();
        for ($i = 1; $i < $months; $i++) {
            $periodEnd = $this->cycle->advanceDueDate($periodEnd, (int) $customer->billing_day);
        }

        $amount = $price * $months;

        $invoice = $this->createInvoice(
            customer: $customer,
            type: 'multi_month',
            periodStart: $this->periodStartBeforeDue($customer),
            periodEnd: $periodEnd->toDateString(),
            dueDate: $due->toDateString(),
            amount: $amount,
            notes: 'Tagihan gabungan '.$months.' bulan ('.$customer->package?->name.')',
            billingMonths: $months,
        );

        // Gabung N bulan untuk pelanggan terisolir/nunggak = tempo N bulan
        // tanpa perlu lunas dulu: cabut isolir, pulihkan profil, putus sesi.
        if ($customer->isOverdue() || $customer->status === 'isolated') {
            $this->grantGrace(
                $customer,
                now()->startOfDay()->addMonthsNoOverflow($months),
                'Tempo '.$months.' bulan (tagihan gabungan)',
            );
        }

        return $invoice;
    }

    private function createInvoice(
        PppoeCustomer $customer,
        string $type,
        string $periodStart,
        string $periodEnd,
        string $dueDate,
        int $amount,
        ?string $notes = null,
        int $billingMonths = 1,
        bool $notify = true,
    ): Invoice {
        $package = $customer->relationLoaded('package')
            ? $customer->package
            : $customer->package()->first();

        $discount = 0;
        $total = max(0, $amount - $discount);

        $invoice = Invoice::query()->create([
            'number' => $this->nextNumber(),
            'pppoe_customer_id' => $customer->id,
            'subscription_package_id' => $customer->subscription_package_id,
            'type' => $type,
            'billing_months' => max(1, $billingMonths),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'due_date' => $dueDate,
            'amount' => $amount,
            'discount' => $discount,
            'total' => $total,
            'status' => 'unpaid',
            'package_name' => $package?->name,
            'package_price' => $package?->price,
            'notes' => $notes,
        ]);

        if ($notify) {
            $this->notifier->notifyInvoice($invoice->loadMissing('customer'));
        }

        return $invoice;
    }

    private function periodStartBeforeDue(PppoeCustomer $customer): string
    {
        $due = $customer->due_date->copy()->startOfDay();
        $billingDay = $this->cycle->normalizeBillingDay((int) $customer->billing_day);
        $prevMonth = $due->copy()->subMonthNoOverflow();
        $day = min($billingDay, $prevMonth->daysInMonth);

        return $prevMonth->day($day)->toDateString();
    }

    private function nextNumber(): string
    {
        $code = strtoupper((string) AppSettings::get('app_invoice_prefix', 'INV'));
        $prefix = $code.'/'.now()->format('Y/m').'/';

        $latest = Invoice::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $seq = 1;
        if ($latest && preg_match('/\/(\d+)$/', $latest, $matches)) {
            $seq = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
