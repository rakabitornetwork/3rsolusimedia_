<?php

namespace App\Services\Vpn;

use App\Models\Invoice;
use App\Models\PppoeCustomer;
use App\Models\VpnRouter;
use App\Models\VpnRouterCredit;
use App\Services\BillingService;
use Illuminate\Support\Facades\DB;

class VpnRouterAccounts
{
    public function __construct(
        private readonly VpnAccessPlan $plan,
        private readonly VpnProvisioner $provisioner,
        private readonly BillingService $billing,
    ) {}

    /**
     * Router baru memakai tagihan yang ditahan kalau ada. Kalau tidak, tagihan pembuka dibuat.
     *
     * @return array{router: VpnRouter, invoice: ?Invoice, reused: bool}
     */
    public function enroll(PppoeCustomer $customer, string $name): array
    {
        return DB::transaction(function () use ($customer, $name) {
            $router = $this->plan->addRouter($customer, $name);
            $credit = VpnRouterCredit::query()
                ->where('pppoe_customer_id', $customer->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $credit) {
                $hasIncluded = VpnRouter::query()
                    ->where('pppoe_customer_id', $customer->id)
                    ->where('included', true)
                    ->where('id', '!=', $router->id)
                    ->exists();

                if (! $hasIncluded && $customer->vpn_self_signup && $customer->vpn_trial_ends_at === null) {
                    $today = now()->startOfDay();
                    $trialEnds = $today->copy()->addDays(3);
                    $customer->update([
                        'start_date' => $today->toDateString(),
                        'billing_day' => min(28, (int) $trialEnds->day),
                        'due_date' => $trialEnds->toDateString(),
                        'vpn_trial_ends_at' => $trialEnds->toDateString(),
                        'status' => 'active',
                        'is_active' => true,
                    ]);
                    $customer->refresh();
                }

                if (! $hasIncluded) {
                    $router->update([
                        'included' => true,
                        'billing_day' => (int) ($customer->billing_day ?: now()->day),
                        'service_until' => $customer->due_date?->toDateString(),
                    ]);

                    return [
                        'router' => $router->fresh('portForwards') ?? $router,
                        'invoice' => null,
                        'reused' => false,
                    ];
                }

                $invoice = $this->billing->createVpnRouterOpeningInvoice($customer->fresh() ?? $customer, $router);

                return [
                    'router' => $router,
                    'invoice' => $invoice,
                    'reused' => false,
                ];
            }

            $router->update([
                'included' => (bool) $credit->included,
                'billing_day' => $credit->billing_day,
                'service_until' => $credit->included
                    ? ($customer->due_date?->toDateString() ?? $credit->service_until?->toDateString())
                    : $credit->service_until?->toDateString(),
            ]);

            $ids = array_values(array_filter(array_map('intval', $credit->invoice_ids ?? [])));
            if ($ids !== []) {
                Invoice::query()
                    ->whereIn('id', $ids)
                    ->where('pppoe_customer_id', $customer->id)
                    ->where('type', 'vpn_router')
                    ->update(['vpn_router_id' => $router->id]);
            }

            $credit->delete();

            return [
                'router' => $router->fresh('portForwards') ?? $router,
                'invoice' => null,
                'reused' => true,
            ];
        });
    }

    /**
     * Hapus secret dan port nama router ini di CHR, lalu tahan tagihannya untuk router berikutnya.
     *
     * @return array{ok: bool, message: string}
     */
    public function release(VpnRouter $router): array
    {
        $router->loadMissing(['portForwards', 'invoices']);
        $removed = $this->provisioner->removeRouter($router);
        if (! $removed['ok']) {
            return $removed;
        }

        DB::transaction(function () use ($router) {
            $invoiceIds = $router->invoices
                ->where('status', '!=', 'void')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            if ($invoiceIds !== [] || $router->included) {
                VpnRouterCredit::query()->create([
                    'pppoe_customer_id' => $router->pppoe_customer_id,
                    'billing_day' => $router->billing_day,
                    'included' => (bool) $router->included,
                    'service_until' => $router->service_until?->toDateString(),
                    'invoice_ids' => $invoiceIds,
                ]);
            }

            $router->delete();
        });

        return [
            'ok' => true,
            'message' => 'Router '.$router->name.' dihapus dari CHR. Tagihannya tetap berlaku untuk router berikutnya.',
        ];
    }

    /**
     * Hapus semua router akun yang sudah 3 bulan tidak diperpanjang.
     * Akun pelanggan dan tagihannya tidak dihapus, dan hak router tidak ditahan.
     *
     * @return array{removed: int, failed: list<string>, credits: int}
     */
    public function purgeLapsedRouters(PppoeCustomer $customer): array
    {
        $removed = 0;
        $failed = [];

        foreach ($customer->vpnRouters()->with(['portForwards', 'invoices'])->get() as $router) {
            $result = $this->provisioner->removeRouter($router);
            if (! $result['ok']) {
                $failed[] = $router->name;

                continue;
            }

            $router->delete();
            $removed++;
        }

        $credits = 0;
        if ($customer->vpnRouters()->count() === 0) {
            $credits = VpnRouterCredit::query()
                ->where('pppoe_customer_id', $customer->id)
                ->delete();
        }

        return [
            'removed' => $removed,
            'failed' => $failed,
            'credits' => $credits,
        ];
    }

    /**
     * Akun daftar mandiri yang sebulan tidak punya pembayaran kehilangan router di CHR.
     *
     * @return array{customers: int, removed: int, failed: int}
     */
    public function purgeUnpaidTrials(): array
    {
        $cutoff = now()->startOfDay()->subMonthNoOverflow()->toDateString();
        $customers = PppoeCustomer::query()
            ->where('ppp_service', PppoeCustomer::SERVICE_L2TP)
            ->whereNotNull('vpn_trial_ends_at')
            ->whereDate('start_date', '<=', $cutoff)
            ->whereDoesntHave('invoices', fn ($query) => $query->where('status', 'paid'))
            ->get();

        $removed = 0;
        $failed = 0;

        foreach ($customers as $customer) {
            $result = $this->purgeLapsedRouters($customer);
            $removed += $result['removed'];
            $failed += count($result['failed']);

            if ($result['failed'] !== []) {
                continue;
            }

            if (VpnChrSettings::configured() || $customer->vpn_remote_address) {
                $cleared = $this->provisioner->removeCustomer($customer->fresh(['vpnPortForwards', 'vpnRouters.portForwards']));
                if (! $cleared['ok']) {
                    $failed++;

                    continue;
                }
            }

            $note = 'Akun gratis dihapus dari CHR karena sebulan tidak ada pembayaran.';
            if (! str_contains((string) $customer->notes, $note)) {
                $customer->update([
                    'status' => 'isolated',
                    'notes' => trim((string) $customer->notes."\n".$note),
                ]);
            }
        }

        return [
            'customers' => $customers->count(),
            'removed' => $removed,
            'failed' => $failed,
        ];
    }

    public function spareCount(PppoeCustomer $customer): int
    {
        return VpnRouterCredit::query()->where('pppoe_customer_id', $customer->id)->count();
    }

    public function nextWithoutInvoice(PppoeCustomer $customer): bool
    {
        if ($this->spareCount($customer) > 0) {
            return true;
        }

        $includedRouter = VpnRouter::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('included', true)
            ->exists();
        $includedCredit = VpnRouterCredit::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('included', true)
            ->exists();

        return ! $includedRouter && ! $includedCredit;
    }
}
