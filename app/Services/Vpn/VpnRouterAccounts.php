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
