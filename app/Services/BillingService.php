<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PppoeCustomer;
use App\Models\VpnRouter;
use App\Services\Messaging\CustomerNotifier;
use App\Services\Vpn\VpnProvisioner;
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
        private readonly VpnProvisioner $vpnRouters,
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
            applyCredit: $type === 'monthly',
        );
    }

    public function hasPaidInvoice(PppoeCustomer $customer): bool
    {
        return Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('status', 'paid')
            ->where('type', '!=', 'vpn_router')
            ->exists();
    }

    /**
     * Sudah ada tagihan yang nominalnya ditentukan (belum bayar atau lunas).
     */
    public function hasDeterminedInvoice(PppoeCustomer $customer): bool
    {
        return Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->whereIn('status', ['unpaid', 'paid'])
            ->exists();
    }

    /**
     * Ganti paket pada pelanggan yang sudah jatuh tempo.
     *
     * Tagihan terbuka yang lama dibatalkan. Tagihan baru memakai harga penuh
     * paket baru untuk satu siklus ke depan, jatuh tempo bulan berikutnya.
     * Tanggal mulai layanan di bulan-bulan sebelumnya tidak ikut dihitung.
     */
    public function reissueNextMonthInvoiceForPackageChange(PppoeCustomer $customer): Invoice
    {
        $customer->loadMissing('package');

        $price = (int) ($customer->package?->price ?? 0);
        if ($price <= 0) {
            throw new InvalidArgumentException('Paket baru belum punya harga yang valid.');
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
                    'Sudah ada tagihan gabungan yang belum dibayar. Lunasi atau batalkan dulu sebelum mengganti paket.'
                );
            }
        }

        foreach ($unpaid as $existing) {
            $this->voidInvoice(
                $existing,
                'Diganti paket layanan. Tagihan baru dibayar bulan berikutnya, tanpa hitungan tanggal mulai sebelumnya.'
            );
        }

        return $this->createInvoice(
            customer: $customer,
            type: 'monthly',
            periodStart: $this->periodStartBeforeDue($customer),
            periodEnd: $customer->due_date->toDateString(),
            dueDate: $customer->due_date->toDateString(),
            amount: $price,
            notes: 'Tagihan bulanan paket baru. Jatuh tempo bulan berikutnya, tanpa hitungan tanggal mulai layanan sebelumnya.',
        );
    }

    /**
     * Ganti paket di tengah siklus yang belum jatuh tempo.
     * Tagihan bulan berjalan tetap. Selisih harga dihitung dari tanggal ganti sampai jatuh tempo.
     *
     * @return array{invoice: ?Invoice, credit: int}
     */
    public function applyMidCyclePackageChange(PppoeCustomer $customer, int $oldPrice, Carbon $changeDate): array
    {
        $customer->loadMissing('package');
        $newPrice = (int) ($customer->package?->price ?? 0);
        if ($newPrice <= 0) {
            throw new InvalidArgumentException('Paket baru belum punya harga yang valid.');
        }

        if (! $customer->due_date) {
            throw new InvalidArgumentException('Pelanggan belum punya tanggal jatuh tempo.');
        }

        $due = $customer->due_date->copy()->startOfDay();
        $periodStart = $this->openPeriodStart($customer, $due);
        $change = $this->clampChangeDate($changeDate, $periodStart, $due);
        $cycleDays = max(1, (int) $periodStart->diffInDays($due));
        $remaining = max(0, (int) $change->diffInDays($due));
        $delta = $this->cycle->signedRoundedDelta($oldPrice, $newPrice, $remaining, $cycleDays);

        if ($delta > 0) {
            $invoice = $this->createInvoice(
                customer: $customer,
                type: 'adjustment',
                periodStart: $change->toDateString(),
                periodEnd: $due->toDateString(),
                dueDate: $due->toDateString(),
                amount: $delta,
                notes: 'Ganti layanan. Selisih harga '.$remaining.'/'.$cycleDays.' hari sampai jatuh tempo.',
                applyCredit: true,
            );

            return ['invoice' => $invoice, 'credit' => 0];
        }

        if ($delta < 0) {
            $credit = abs($delta);
            $applied = $this->applyCreditToOpenInvoices($customer, $credit);
            $stored = $credit - $applied;
            if ($stored > 0) {
                $customer->update([
                    'billing_credit' => (int) $customer->billing_credit + $stored,
                ]);
            }

            return ['invoice' => null, 'credit' => $credit];
        }

        return ['invoice' => null, 'credit' => 0];
    }

    /**
     * Pindah tanggal tagihan pelanggan yang sudah pernah bayar.
     * Tagihan awal pendaftaran tidak dihitung ulang. Periode baru dimulai dari
     * jatuh tempo terakhir yang sudah lunas.
     *
     * @return array{invoice: ?Invoice, anchor: string, due_date: string}
     */
    public function reissueInvoiceForBillingDateChange(
        PppoeCustomer $customer,
        ?int $oldPrice = null,
        ?Carbon $changeDate = null,
    ): array {
        $customer->loadMissing('package');
        $newPrice = (int) ($customer->package?->price ?? 0);
        if ($newPrice <= 0) {
            throw new InvalidArgumentException('Pelanggan belum punya paket berharga valid.');
        }

        if (! $customer->due_date) {
            throw new InvalidArgumentException('Pelanggan belum punya tanggal jatuh tempo.');
        }

        $lastPaid = Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('status', 'paid')
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->first();

        if (! $lastPaid?->due_date) {
            throw new InvalidArgumentException('Belum ada tagihan lunas. Tanggal tagihan belum bisa dipindah tanpa menghitung tagihan awal.');
        }

        $anchor = $lastPaid->due_date->copy()->startOfDay();
        $due = $customer->due_date->copy()->startOfDay();
        $billingDay = $this->cycle->normalizeBillingDay((int) $customer->billing_day);

        if ($due->lessThanOrEqualTo($anchor)) {
            $due = $this->cycle->advanceDueDate($anchor, $billingDay);
            $billingDay = $this->cycle->normalizeBillingDay((int) $due->day);
            $customer->update([
                'due_date' => $due->toDateString(),
                'billing_day' => $billingDay,
            ]);
        }

        $cycleDays = $this->cycle->cycleLength($due, $billingDay);
        $splitPrices = $oldPrice !== null && $changeDate !== null && $oldPrice !== $newPrice;
        $daysOld = 0;
        $daysNew = max(0, (int) $anchor->diffInDays($due));

        if ($splitPrices) {
            $change = $changeDate->copy()->startOfDay();
            if ($change->lessThan($anchor)) {
                $change = $anchor->copy();
            }
            if ($change->greaterThan($due)) {
                $change = $due->copy();
            }
            $daysOld = max(0, (int) $anchor->diffInDays($change));
            $daysNew = max(0, (int) $change->diffInDays($due));
            $raw = (int) round(($oldPrice * $daysOld + $newPrice * $daysNew) / max(1, $cycleDays));
        } else {
            $raw = (int) round($newPrice * $daysNew / max(1, $cycleDays));
        }

        $usedDays = $daysOld + $daysNew;
        $samePrice = ! $splitPrices;
        $amount = $usedDays === 0
            ? 0
            : ($samePrice && $usedDays === $cycleDays
                ? $newPrice
                : $this->cycle->roundUpToThousand($raw));

        $this->voidOpenSingleInvoices(
            $customer,
            'Diganti karena tanggal tagihan diubah. Tagihan awal pendaftaran tidak dihitung.',
            'Sudah ada tagihan gabungan yang belum dibayar. Lunasi atau batalkan dulu sebelum mengubah tanggal tagihan.'
        );

        if ($amount <= 0) {
            return [
                'invoice' => null,
                'anchor' => $anchor->toDateString(),
                'due_date' => $due->toDateString(),
            ];
        }

        $invoice = $this->createInvoice(
            customer: $customer,
            type: $samePrice && $usedDays === $cycleDays ? 'monthly' : 'adjustment',
            periodStart: $anchor->toDateString(),
            periodEnd: $due->toDateString(),
            dueDate: $due->toDateString(),
            amount: $amount,
            notes: 'Perubahan tanggal tagihan. Dihitung dari jatuh tempo terakhir yang sudah lunas ('
                .$anchor->format('d/m/Y').'), tanpa tagihan awal pendaftaran.',
            applyCredit: true,
        );

        return [
            'invoice' => $invoice,
            'anchor' => $anchor->toDateString(),
            'due_date' => $due->toDateString(),
        ];
    }

    /**
     * Hitung tagihan pemakaian sampai tanggal berhenti.
     * Jika periode ini sudah lunas dan pemakaian lebih kecil, selisihnya jadi kredit.
     *
     * @return array{invoice: ?Invoice, credit: int, amount: int}
     */
    public function settleStoppedService(PppoeCustomer $customer, Carbon $stopDate): array
    {
        $customer->loadMissing('package');
        $price = (int) ($customer->package?->price ?? 0);
        if (! $customer->due_date || $price <= 0) {
            return ['invoice' => null, 'credit' => 0, 'amount' => 0];
        }

        $due = $customer->due_date->copy()->startOfDay();
        $periodStart = $this->openPeriodStart($customer, $due);
        $stop = $stopDate->copy()->startOfDay();

        if ($stop->greaterThanOrEqualTo($due)) {
            return ['invoice' => null, 'credit' => 0, 'amount' => 0];
        }

        if ($stop->lessThan($periodStart)) {
            $stop = $periodStart->copy();
        }

        $cycleDays = max(1, (int) $periodStart->diffInDays($due));
        $usedDays = max(0, (int) $periodStart->diffInDays($stop));
        $amount = $this->cycle->chargeForSpan($price, $usedDays, $cycleDays);

        $paidThisCycle = (int) Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('status', 'paid')
            ->whereDate('due_date', $due->toDateString())
            ->sum('total');

        $this->voidOpenSingleInvoices(
            $customer,
            'Diganti tagihan pemberhentian layanan per '.$stop->format('d/m/Y').'.',
            'Sudah ada tagihan gabungan yang belum dibayar. Lunasi atau batalkan dulu sebelum menghentikan layanan.'
        );

        if ($paidThisCycle > 0) {
            $credit = max(0, $paidThisCycle - $amount);
            if ($credit > 0) {
                $customer->update([
                    'billing_credit' => (int) $customer->billing_credit + $credit,
                ]);
            }

            $owed = max(0, $amount - $paidThisCycle);
            if ($owed <= 0) {
                return ['invoice' => null, 'credit' => $credit, 'amount' => $amount];
            }

            $amount = $owed;
        }

        if ($amount <= 0) {
            return ['invoice' => null, 'credit' => 0, 'amount' => 0];
        }

        $invoice = $this->createInvoice(
            customer: $customer,
            type: 'adjustment',
            periodStart: $periodStart->toDateString(),
            periodEnd: $stop->toDateString(),
            dueDate: $stop->toDateString(),
            amount: $amount,
            notes: 'Pemberhentian layanan per '.$stop->format('d/m/Y').'. Tagihan pemakaian '.$usedDays.'/'.$cycleDays.' hari.',
            applyCredit: true,
        );

        return ['invoice' => $invoice->fresh(), 'credit' => 0, 'amount' => (int) $invoice->total];
    }

    /**
     * Aktifkan kembali pelanggan yang berhenti. Prorata dihitung dari tanggal
     * aktif kembali sampai jatuh tempo yang dipilih, bukan dari tanggal daftar lama.
     */
    public function createReactivationInvoice(PppoeCustomer $customer, Carbon $from): Invoice
    {
        $customer->loadMissing('package');
        $price = (int) ($customer->package?->price ?? 0);
        if ($price <= 0) {
            throw new InvalidArgumentException('Pelanggan belum punya paket berharga valid.');
        }

        if (! $customer->due_date) {
            throw new InvalidArgumentException('Tentukan tanggal jatuh tempo untuk aktivasi kembali.');
        }

        $start = $from->copy()->startOfDay();
        $due = $customer->due_date->copy()->startOfDay();
        if ($due->lessThanOrEqualTo($start)) {
            throw new InvalidArgumentException('Tanggal jatuh tempo harus setelah tanggal aktif kembali.');
        }

        $calc = $this->cycle->calculateProrata(
            $start,
            (int) $customer->billing_day,
            $price,
            $due->toDateString(),
        );

        return $this->createInvoice(
            customer: $customer,
            type: 'prorata',
            periodStart: $calc['start_date'],
            periodEnd: $calc['due_date'],
            dueDate: $calc['due_date'],
            amount: $calc['amount'],
            notes: 'Aktivasi kembali (prorata)',
            applyCredit: true,
        );
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
            ->where('type', '!=', 'vpn_router')
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
            ->where('type', '!=', 'vpn_router')
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

        $created += $this->generateVpnRouterRenewals();

        if ($created > 0) {
            $this->notifier->dispatchWhatsappOutbox();
        }

        return compact('created', 'skipped');
    }

    public function createVpnRouterOpeningInvoice(PppoeCustomer $customer, VpnRouter $router, bool $notify = false): Invoice
    {
        $customer->loadMissing('package');
        $amount = (int) ($customer->package?->price ?? 0);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Paket pelanggan belum punya harga, jadi tagihan router tidak bisa dibuat.');
        }

        $start = now()->startOfDay();
        $end = $start->copy()->addMonthNoOverflow();

        return $this->createInvoice(
            customer: $customer,
            type: 'vpn_router',
            periodStart: $start->toDateString(),
            periodEnd: $end->toDateString(),
            dueDate: $start->toDateString(),
            amount: $amount,
            notes: 'Router '.$router->name.' — bayar dulu baru bisa dipakai',
            notify: $notify,
            vpnRouterId: $router->id,
        );
    }

    private function generateVpnRouterRenewals(): int
    {
        $created = 0;
        $routers = VpnRouter::query()->with('customer.package')->get();

        foreach ($routers as $router) {
            $customer = $router->customer;
            if (! $customer || ! $customer->is_active || ! $router->service_until) {
                continue;
            }

            if ($router->service_until->copy()->startOfDay()->lessThan(now()->startOfDay())) {
                try {
                    $this->vpnRouters->syncRouterSecret($router);
                } catch (\Throwable) {
                    // Generate tagihan tetap jalan meski CHR tidak terjangkau.
                }
            }

            if (! $this->isWithinUpcomingWindow($router->service_until)) {
                continue;
            }

            $open = Invoice::query()
                ->where('vpn_router_id', $router->id)
                ->where('status', 'unpaid')
                ->exists();
            if ($open) {
                continue;
            }

            $amount = (int) ($customer->package?->price ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $start = $router->service_until->copy()->startOfDay();
            $end = $start->copy()->addMonthNoOverflow();
            $this->createInvoice(
                customer: $customer,
                type: 'vpn_router',
                periodStart: $start->toDateString(),
                periodEnd: $end->toDateString(),
                dueDate: $start->toDateString(),
                amount: $amount,
                notes: 'Perpanjangan router '.$router->name,
                notify: true,
                vpnRouterId: $router->id,
            );
            $created++;
        }

        return $created;
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

            if ($customer && $invoice->type === 'vpn_router' && $invoice->vpn_router_id) {
                $router = VpnRouter::query()->find($invoice->vpn_router_id);
                if ($router) {
                    $end = $invoice->period_end?->copy()->startOfDay();
                    $until = $end && $end->greaterThanOrEqualTo(now()->startOfDay())
                        ? $end->toDateString()
                        : now()->startOfDay()->addMonthNoOverflow()->toDateString();
                    $router->update(['service_until' => $until]);
                    try {
                        $this->vpnRouters->syncRouterSecret($router->fresh() ?? $router);
                    } catch (\Throwable) {
                        // Pelunasan tetap sah meski CHR tidak terjangkau.
                    }
                }

                return [
                    'invoice' => $invoice->fresh(['customer', 'payments.receiver', 'package']),
                    'payment' => $payment->load('receiver'),
                    'next_due_date' => $router?->service_until?->toDateString(),
                ];
            }

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

                $paidCustomer = $customer->fresh(['vpnRouters.portForwards']);
                if ($paidCustomer?->pppService() === PppoeCustomer::SERVICE_L2TP && $paidCustomer->status === 'active') {
                    foreach ($paidCustomer->vpnRouters as $vpnRouter) {
                        if (! $vpnRouter->included || ! $vpnRouter->isUsable()) {
                            continue;
                        }

                        try {
                            $this->vpnRouters->pushRouter($vpnRouter);
                        } catch (\Throwable) {
                            // Pelunasan tetap sah meski CHR belum terisi.
                        }
                    }
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
        int $discount = 0,
        bool $applyCredit = false,
        ?int $vpnRouterId = null,
    ): Invoice {
        $package = $customer->relationLoaded('package')
            ? $customer->package
            : $customer->package()->first();

        $discount = max(0, $discount);
        if ($applyCredit) {
            $discount += $this->takeCredit($customer, max(0, $amount - $discount));
        }
        $total = max(0, $amount - $discount);

        $invoice = Invoice::query()->create([
            'number' => $this->nextNumber(),
            'pppoe_customer_id' => $customer->id,
            'vpn_router_id' => $vpnRouterId,
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
        return $this->cycle->periodStart($customer->due_date, (int) $customer->billing_day)->toDateString();
    }

    private function openPeriodStart(PppoeCustomer $customer, Carbon $due): Carbon
    {
        $periodStart = $this->cycle->periodStart($due, (int) $customer->billing_day);
        $serviceStart = $customer->start_date?->copy()->startOfDay();

        if ($serviceStart && $serviceStart->greaterThan($periodStart) && $serviceStart->lessThan($due)) {
            return $serviceStart;
        }

        return $periodStart;
    }

    private function clampChangeDate(Carbon $changeDate, Carbon $periodStart, Carbon $due): Carbon
    {
        $change = $changeDate->copy()->startOfDay();
        $today = now()->startOfDay();

        if ($change->greaterThan($today)) {
            throw new InvalidArgumentException('Tanggal ganti layanan tidak boleh setelah hari ini.');
        }

        if ($change->lessThan($periodStart) || $change->greaterThanOrEqualTo($due)) {
            throw new InvalidArgumentException('Tanggal ganti layanan harus berada di dalam siklus tagihan yang sedang berjalan.');
        }

        return $change;
    }

    private function voidOpenSingleInvoices(PppoeCustomer $customer, string $reason, string $blockedMessage): void
    {
        $unpaid = Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('status', 'unpaid')
            ->get();

        foreach ($unpaid as $existing) {
            if ((int) ($existing->billing_months ?: 1) > 1 || $existing->type === 'multi_month') {
                throw new InvalidArgumentException($blockedMessage);
            }
        }

        foreach ($unpaid as $existing) {
            $this->voidInvoice($existing, $reason);
        }
    }

    private function applyCreditToOpenInvoices(PppoeCustomer $customer, int $credit): int
    {
        if ($credit <= 0) {
            return 0;
        }

        $invoices = Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('status', 'unpaid')
            ->orderBy('id')
            ->get();

        $remaining = $credit;
        $applied = 0;

        foreach ($invoices as $invoice) {
            if ((int) ($invoice->billing_months ?: 1) > 1 || $invoice->type === 'multi_month') {
                throw new InvalidArgumentException(
                    'Sudah ada tagihan gabungan yang belum dibayar. Lunasi atau batalkan dulu sebelum mengganti paket.'
                );
            }

            $room = max(0, (int) $invoice->total);
            $used = min($remaining, $room);
            if ($used <= 0) {
                continue;
            }

            $discount = (int) $invoice->discount + $used;
            $invoice->update([
                'discount' => $discount,
                'total' => max(0, (int) $invoice->amount - $discount),
                'notes' => trim(($invoice->notes ? $invoice->notes."\n" : '').'Kredit turun paket Rp '.number_format($used, 0, ',', '.').'.'),
            ]);

            $applied += $used;
            $remaining -= $used;
            if ($remaining <= 0) {
                break;
            }
        }

        return $applied;
    }

    private function takeCredit(PppoeCustomer $customer, int $amount): int
    {
        $amount = max(0, $amount);
        $available = max(0, (int) $customer->billing_credit);
        $used = min($available, $amount);

        if ($used > 0) {
            $left = $available - $used;
            $customer->update(['billing_credit' => $left]);
            $customer->billing_credit = $left;
        }

        return $used;
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
