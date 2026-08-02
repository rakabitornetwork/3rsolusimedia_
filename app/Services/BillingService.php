<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PppoeCustomer;
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
    ) {
    }

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

        // Periode pertama selalu start → due pertama (bukan due yang sudah dimajukan bayar).
        $firstDue = $this->cycle->nextDueDate(
            $customer->start_date,
            (int) $customer->billing_day,
        );

        return $this->createInvoice(
            customer: $customer,
            type: 'prorata',
            periodStart: $customer->start_date->toDateString(),
            periodEnd: $firstDue->toDateString(),
            dueDate: $firstDue->toDateString(),
            amount: $amount,
            notes: 'Tagihan pertama (prorata)',
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

        // Samakan ke due pertama dari start/billing_day, bukan due berjalan
        // yang bisa sudah dimajukan (penyebab tagihan "62 hari" / dobel).
        $firstDue = $this->cycle->nextDueDate(
            $customer->start_date,
            (int) $customer->billing_day,
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
     * Buat tagihan bulanan terbuka hanya jika jatuh tempo ≤ 7 hari (atau sudah lewat).
     * Tagihan prorata pertama hanya dibuat saat pelanggan baru (bukan di sini),
     * agar hapus invoice tidak langsung dibuat ulang.
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
            $hasUnpaid = Invoice::query()
                ->where('pppoe_customer_id', $customer->id)
                ->where('status', 'unpaid')
                ->exists();

            if ($hasUnpaid) {
                $skipped++;
                continue;
            }

            if (! $this->isWithinUpcomingWindow($customer->due_date)) {
                $skipped++;
                continue;
            }

            // Hindari duplikat untuk due_date yang sama (termasuk yang sudah lunas).
            $existsForDue = Invoice::query()
                ->where('pppoe_customer_id', $customer->id)
                ->whereDate('due_date', $customer->due_date->toDateString())
                ->whereIn('status', ['unpaid', 'paid'])
                ->exists();

            if ($existsForDue) {
                $skipped++;
                continue;
            }

            // Siklus pertama: pakai jumlah prorata (sudah dibulatkan), bukan harga penuh.
            // createProrataInvoice sendiri menolak jika sudah ada riwayat prorata/pembayaran.
            if (
                ! $this->hasCompletedFirstBillingCycle($customer)
                && (int) ($customer->first_bill_amount ?? 0) > 0
            ) {
                $invoice = $this->createProrataInvoice($customer);
            } else {
                $price = (int) ($customer->package?->price ?? 0);
                if ($price <= 0) {
                    $skipped++;
                    continue;
                }

                $invoice = $this->createInvoice(
                    customer: $customer,
                    type: 'monthly',
                    periodStart: $this->periodStartBeforeDue($customer),
                    periodEnd: $customer->due_date->toDateString(),
                    dueDate: $customer->due_date->toDateString(),
                    amount: $price,
                    notes: 'Tagihan bulanan',
                );
            }

            if ($invoice) {
                $created++;
            } else {
                $skipped++;
            }
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

        return DB::transaction(function () use ($invoice, $method, $reference, $notes, $receivedBy, $paidAt) {
            $invoice->loadMissing(['customer.package']);

            $payment = Payment::query()->create([
                'invoice_id' => $invoice->id,
                'received_by' => $receivedBy,
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
                $cursor = $invoice->due_date->copy()->startOfDay();
                $billingDay = (int) $customer->billing_day;

                for ($i = 0; $i < $months; $i++) {
                    $cursor = $this->cycle->advanceDueDate($cursor, $billingDay);
                }

                $nextDueDate = $cursor->toDateString();

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
     * Tidak mengembalikan due_date pelanggan — koreksi due_date tetap manual di PPPoE bila perlu.
     */
    public function voidInvoice(Invoice $invoice, ?string $notes = null): Invoice
    {
        if ($invoice->status === 'void') {
            throw new InvalidArgumentException('Tagihan ini sudah dibatalkan.');
        }

        $invoice->update([
            'status' => 'void',
            'notes' => trim(($invoice->notes ? $invoice->notes."\n" : '').($notes ?: 'Dibatalkan dari admin.')),
        ]);

        return $invoice->fresh(['customer', 'payments.receiver', 'package']);
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

        return $this->createInvoice(
            customer: $customer,
            type: 'multi_month',
            periodStart: $this->periodStartBeforeDue($customer),
            periodEnd: $periodEnd->toDateString(),
            dueDate: $due->toDateString(),
            amount: $amount,
            notes: 'Tagihan gabungan '.$months.' bulan ('.$customer->package?->name.')',
            billingMonths: $months,
        );
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
    ): Invoice {
        $package = $customer->relationLoaded('package')
            ? $customer->package
            : $customer->package()->first();

        $discount = 0;
        $total = max(0, $amount - $discount);

        return Invoice::query()->create([
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
