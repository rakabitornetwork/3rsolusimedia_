<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\Payment;
use App\Models\PppoeCustomer;
use App\Models\SiteSetting;
use App\Models\SubscriptionPackage;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $today = now()->toDateString();
        $monthStart = now()->copy()->startOfMonth();

        $customersTotal = PppoeCustomer::query()->count();
        $customersActive = PppoeCustomer::query()->where('status', 'active')->count();
        $customersIsolated = PppoeCustomer::query()->where('status', 'isolated')->count();
        $customersOverdue = PppoeCustomer::query()
            ->whereDate('due_date', '<', $today)
            ->where('is_active', true)
            ->count();
        $customersDisabled = PppoeCustomer::query()->where('status', 'disabled')->count();
        $syncErrors = PppoeCustomer::query()->where('sync_status', 'error')->count();

        $invoicesUnpaid = Invoice::query()->where('status', 'unpaid')->count();
        $invoicesOverdue = Invoice::query()
            ->where('status', 'unpaid')
            ->whereDate('due_date', '<', $today)
            ->count();
        $collectedThisMonth = (int) Payment::query()
            ->where('paid_at', '>=', $monthStart)
            ->sum('amount');
        $paidThisMonth = Invoice::query()
            ->where('status', 'paid')
            ->where('paid_at', '>=', $monthStart)
            ->count();

        $routersTotal = MikrotikRouter::query()->count();
        $routersActive = MikrotikRouter::query()->where('is_active', true)->count();
        $packagesActive = SubscriptionPackage::query()->where('is_active', true)->count();

        $dueSoon = PppoeCustomer::query()
            ->with('package')
            ->where('is_active', true)
            ->whereDate('due_date', '>=', $today)
            ->whereDate('due_date', '<=', now()->copy()->addDays(7)->toDateString())
            ->orderBy('due_date')
            ->limit(6)
            ->get()
            ->map(fn (PppoeCustomer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'username' => $customer->username,
                'due_date' => $customer->due_date?->format('Y-m-d'),
                'package' => $customer->package?->name,
                'status' => $customer->status,
            ]);

        $attentionInvoices = Invoice::query()
            ->with('customer')
            ->where('status', 'unpaid')
            ->whereDate('due_date', '<=', now()->copy()->addDays(7)->toDateString())
            ->orderBy('due_date')
            ->limit(6)
            ->get()
            ->map(fn (Invoice $invoice) => $invoice->toAdminArray());

        return Inertia::render('Admin/Dashboard', [
            'company' => SiteSetting::getValue('company_name', '3R Solusi Media'),
            'stats' => [
                'customers_total' => $customersTotal,
                'customers_active' => $customersActive,
                'customers_isolated' => $customersIsolated,
                'customers_overdue' => $customersOverdue,
                'customers_disabled' => $customersDisabled,
                'sync_errors' => $syncErrors,
                'invoices_unpaid' => $invoicesUnpaid,
                'invoices_overdue' => $invoicesOverdue,
                'paid_this_month' => $paidThisMonth,
                'collected_this_month' => $collectedThisMonth,
                'collected_this_month_label' => 'Rp '.number_format($collectedThisMonth, 0, ',', '.'),
                'routers_total' => $routersTotal,
                'routers_active' => $routersActive,
                'packages_active' => $packagesActive,
            ],
            'due_soon' => $dueSoon,
            'attention_invoices' => $attentionInvoices,
            'quick_actions' => [
                [
                    'label' => 'Tambah Pelanggan',
                    'description' => 'Daftarkan secret PPPoE baru',
                    'href' => '/admin/customers/pppoe/create',
                    'tone' => 'primary',
                ],
                [
                    'label' => 'Tagihan & Bayar',
                    'description' => 'Lihat invoice dan tandai lunas',
                    'href' => '/admin/billing',
                    'tone' => 'default',
                ],
                [
                    'label' => 'Generate Voucher',
                    'description' => 'Buat voucher hotspot',
                    'href' => '/admin/network/hotspot/generate',
                    'tone' => 'default',
                ],
                [
                    'label' => 'Tambah Router',
                    'description' => 'Hubungkan RouterOS baru',
                    'href' => '/admin/network/routeros/create',
                    'tone' => 'default',
                ],
                [
                    'label' => 'Paket Layanan',
                    'description' => 'Kelola harga & profile',
                    'href' => '/admin/customers/pppoe/service-profiles',
                    'tone' => 'default',
                ],
                [
                    'label' => 'Profile PPPoE',
                    'description' => 'Atur PPP profile MikroTik',
                    'href' => '/admin/customers/pppoe/mikrotik-profiles',
                    'tone' => 'default',
                ],
            ],
        ]);
    }
}
