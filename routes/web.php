<?php

use App\Http\Controllers\Admin\AgentCommissionReportController;
use App\Http\Controllers\Admin\AppSettingController;
use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FinancialReportController;
use App\Http\Controllers\Admin\GenieAcsController;
use App\Http\Controllers\Admin\HotspotOpsController;
use App\Http\Controllers\Admin\HotspotProfileController;
use App\Http\Controllers\Admin\HotspotSessionController;
use App\Http\Controllers\Admin\HotspotVoucherController;
use App\Http\Controllers\Admin\HotspotVoucherReportController;
use App\Http\Controllers\Admin\MikrotikPppProfileController;
use App\Http\Controllers\Admin\MessagingController;
use App\Http\Controllers\Admin\NetworkMapController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Admin\PppoeCustomerController;
use App\Http\Controllers\Admin\PppoeSessionController;
use App\Http\Controllers\Admin\RouterOsController;
use App\Http\Controllers\Admin\SectionController;
use App\Http\Controllers\Admin\ServiceProfileController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UpdateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WebsiteSectionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LegalPageController;
use App\Http\Controllers\Portal\CustomerPortalController;
use App\Http\Controllers\Portal\PaymentPortalController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Webhook\EvolutionWebhookController;
use App\Http\Controllers\Webhook\PaymentGatewayWebhookController;
use App\Http\Controllers\Webhook\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');
Route::get('/terms-of-service', [LegalPageController::class, 'terms'])->name('terms');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');

// Alias lama → portal pelanggan
Route::permanentRedirect('/bayar', '/portal');
Route::get('/bayar/{path}', function (string $path) {
    $target = '/portal/'.$path;
    if ($query = request()->getQueryString()) {
        $target .= '?'.$query;
    }

    return redirect($target, 301);
})->where('path', '.*');

Route::get('/portal', [PaymentPortalController::class, 'index'])->name('portal.pay.index');
Route::post('/portal/lookup', [PaymentPortalController::class, 'lookup'])
    ->middleware('throttle:10,1')
    ->name('portal.pay.lookup');

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/portal/{token}', [CustomerPortalController::class, 'home'])
        ->name('portal.home')
        ->where('token', '[A-Za-z0-9]+');
    Route::get('/portal/{token}/tagihan', [PaymentPortalController::class, 'invoices'])
        ->name('portal.pay.invoices')
        ->where('token', '[A-Za-z0-9]+');
    Route::post('/portal/{token}/pay/{invoice}', [PaymentPortalController::class, 'pay'])
        ->name('portal.pay.checkout')
        ->where('token', '[A-Za-z0-9]+')
        ->whereNumber('invoice');

    Route::get('/portal/{token}/perangkat', [CustomerPortalController::class, 'device'])
        ->name('portal.device')
        ->where('token', '[A-Za-z0-9]+');
    Route::get('/portal/{token}/trafik', [CustomerPortalController::class, 'traffic'])
        ->middleware('throttle:120,1')
        ->name('portal.traffic')
        ->where('token', '[A-Za-z0-9]+');
    Route::post('/portal/{token}/perangkat/wifi', [CustomerPortalController::class, 'updateWifi'])
        ->middleware('throttle:10,1')
        ->name('portal.device.wifi')
        ->where('token', '[A-Za-z0-9]+');
    Route::post('/portal/{token}/perangkat/reboot', [CustomerPortalController::class, 'reboot'])
        ->middleware('throttle:5,1')
        ->name('portal.device.reboot')
        ->where('token', '[A-Za-z0-9]+');
    Route::post('/portal/{token}/perangkat/refresh', [CustomerPortalController::class, 'refresh'])
        ->middleware('throttle:10,1')
        ->name('portal.device.refresh')
        ->where('token', '[A-Za-z0-9]+');
});

Route::post('/webhooks/xendit', [PaymentGatewayWebhookController::class, 'xendit'])->name('webhooks.xendit');
Route::post('/webhooks/midtrans', [PaymentGatewayWebhookController::class, 'midtrans'])->name('webhooks.midtrans');
Route::post('/webhooks/duitku', [PaymentGatewayWebhookController::class, 'duitku'])->name('webhooks.duitku');
Route::post('/webhooks/telegram', TelegramWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('webhooks.telegram');
Route::post('/webhooks/evolution', EvolutionWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.evolution');

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [LoginController::class, 'create'])->name('login');
    Route::post('/admin/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'can.write'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/website/sections', [WebsiteSectionController::class, 'index'])->name('website.sections');
    Route::get('/sections/{section}/edit', [SectionController::class, 'edit'])->name('sections.edit');
    Route::post('/sections/{section}', [SectionController::class, 'update'])->name('sections.update');

    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');

    Route::get('/network/routeros', [RouterOsController::class, 'index'])->name('network.routeros');
    Route::get('/network/routeros/create', [RouterOsController::class, 'create'])->name('network.routeros.create');
    Route::post('/network/routeros', [RouterOsController::class, 'store'])->name('network.routeros.store');
    Route::get('/network/routeros/{router}', [RouterOsController::class, 'show'])->name('network.routeros.show');
    Route::get('/network/routeros/{router}/edit', [RouterOsController::class, 'edit'])->name('network.routeros.edit');
    Route::put('/network/routeros/{router}', [RouterOsController::class, 'update'])->name('network.routeros.update');
    Route::delete('/network/routeros/{router}', [RouterOsController::class, 'destroy'])->name('network.routeros.destroy');
    Route::post('/network/routeros/{router}/test', [RouterOsController::class, 'test'])->name('network.routeros.test');
    Route::get('/network/routeros/{router}/interfaces', [RouterOsController::class, 'interfaces'])->name('network.routeros.interfaces');
    Route::get('/network/routeros/{router}/traffic', [RouterOsController::class, 'traffic'])->name('network.routeros.traffic');

    Route::get('/network/map', [NetworkMapController::class, 'index'])->name('network.map');
    Route::get('/network/map/customers/{pppoe}/traffic', [NetworkMapController::class, 'customerTraffic'])
        ->whereNumber('pppoe')
        ->name('network.map.traffic');
    Route::post('/network/map/customers/{pppoe}/reboot', [NetworkMapController::class, 'customerReboot'])
        ->whereNumber('pppoe')
        ->name('network.map.reboot');

    Route::get('/network/genieacs', [GenieAcsController::class, 'index'])->name('network.genieacs');
    Route::post('/network/genieacs/settings', [GenieAcsController::class, 'updateSettings'])->name('network.genieacs.settings');
    Route::post('/network/genieacs/test', [GenieAcsController::class, 'test'])->name('network.genieacs.test');
    Route::get('/network/genieacs/devices/{device}', [GenieAcsController::class, 'show'])
        ->where('device', '.*')
        ->name('network.genieacs.show');
    Route::post('/network/genieacs/devices/{device}/summon', [GenieAcsController::class, 'summon'])
        ->where('device', '.*')
        ->name('network.genieacs.summon');
    Route::post('/network/genieacs/devices/{device}/wifi', [GenieAcsController::class, 'updateWifi'])
        ->where('device', '.*')
        ->name('network.genieacs.wifi');

    Route::get('/network/hotspot', [HotspotVoucherController::class, 'index'])->name('network.hotspot');
    Route::get('/network/hotspot/generate', [HotspotVoucherController::class, 'create'])->name('network.hotspot.generate');
    Route::get('/network/hotspot/users/create', [HotspotVoucherController::class, 'createUser'])->name('network.hotspot.users.create');
    Route::post('/network/hotspot/users', [HotspotVoucherController::class, 'storeUser'])->name('network.hotspot.users.store');
    Route::get('/network/hotspot/reports', [HotspotVoucherReportController::class, 'index'])->name('network.hotspot.reports');
    Route::get('/network/hotspot/print', [HotspotVoucherController::class, 'printCards'])->name('network.hotspot.print');
    Route::post('/network/hotspot', [HotspotVoucherController::class, 'store'])->name('network.hotspot.store');
    Route::post('/network/hotspot/purge', [HotspotVoucherController::class, 'purge'])->name('network.hotspot.purge');
    Route::post('/network/hotspot/delete-by-comment', [HotspotVoucherController::class, 'destroyByComment'])->name('network.hotspot.delete-by-comment');

    Route::get('/network/hotspot/sessions', [HotspotSessionController::class, 'index'])->name('network.hotspot.sessions');
    Route::delete('/network/hotspot/sessions/{router}/{session}', [HotspotSessionController::class, 'disconnect'])
        ->name('network.hotspot.sessions.disconnect');

    Route::get('/network/hotspot/profiles', [HotspotProfileController::class, 'index'])->name('network.hotspot.profiles');
    Route::get('/network/hotspot/profiles/create', [HotspotProfileController::class, 'create'])->name('network.hotspot.profiles.create');
    Route::post('/network/hotspot/profiles', [HotspotProfileController::class, 'store'])->name('network.hotspot.profiles.store');
    Route::get('/network/hotspot/profiles/{router}/edit/{profile}', [HotspotProfileController::class, 'edit'])->name('network.hotspot.profiles.edit');
    Route::put('/network/hotspot/profiles/{router}/{profile}', [HotspotProfileController::class, 'update'])->name('network.hotspot.profiles.update');
    Route::delete('/network/hotspot/profiles/{router}/{profile}', [HotspotProfileController::class, 'destroy'])->name('network.hotspot.profiles.destroy');

    Route::get('/network/hotspot/tools', [HotspotOpsController::class, 'index'])->name('network.hotspot.tools');
    Route::delete('/network/hotspot/tools/{router}/hosts/{id}', [HotspotOpsController::class, 'destroyHost'])
        ->where('id', '.*')
        ->name('network.hotspot.tools.hosts.destroy');
    Route::post('/network/hotspot/tools/{router}/hosts/{id}/bind', [HotspotOpsController::class, 'bindHost'])
        ->where('id', '.*')
        ->name('network.hotspot.tools.hosts.bind');
    Route::delete('/network/hotspot/tools/{router}/cookies/{id}', [HotspotOpsController::class, 'destroyCookie'])
        ->where('id', '.*')
        ->name('network.hotspot.tools.cookies.destroy');
    Route::post('/network/hotspot/tools/{router}/bindings', [HotspotOpsController::class, 'storeBinding'])
        ->name('network.hotspot.tools.bindings.store');
    Route::post('/network/hotspot/tools/{router}/bindings/{id}/toggle', [HotspotOpsController::class, 'toggleBinding'])
        ->where('id', '.*')
        ->name('network.hotspot.tools.bindings.toggle');
    Route::delete('/network/hotspot/tools/{router}/bindings/{id}', [HotspotOpsController::class, 'destroyBinding'])
        ->where('id', '.*')
        ->name('network.hotspot.tools.bindings.destroy');

    Route::post('/network/hotspot/{router}/{user}/toggle', [HotspotVoucherController::class, 'toggle'])->name('network.hotspot.toggle');
    Route::post('/network/hotspot/{router}/{user}/reset', [HotspotVoucherController::class, 'reset'])->name('network.hotspot.reset');
    Route::delete('/network/hotspot/{router}/{user}', [HotspotVoucherController::class, 'destroy'])->name('network.hotspot.destroy');

    Route::get('/customers/pppoe', [PppoeCustomerController::class, 'index'])->name('customers.pppoe');
    Route::get('/customers/pppoe/create', [PppoeCustomerController::class, 'create'])->name('customers.pppoe.create');
    Route::get('/customers/pppoe/profiles', [PppoeCustomerController::class, 'profiles'])->name('customers.pppoe.profiles');
    Route::get('/customers/pppoe/secret', [PppoeCustomerController::class, 'secret'])->name('customers.pppoe.secret');
    Route::post('/customers/pppoe/import-sessions', [PppoeCustomerController::class, 'importFromSessions'])->name('customers.pppoe.import-sessions');
    Route::post('/customers/pppoe/bulk-destroy', [PppoeCustomerController::class, 'bulkDestroy'])->name('customers.pppoe.bulk-destroy');
    Route::post('/customers/pppoe/sync-overdue', [PppoeCustomerController::class, 'syncOverdue'])->name('customers.pppoe.sync-overdue');
    Route::post('/customers/pppoe', [PppoeCustomerController::class, 'store'])->name('customers.pppoe.store');

    Route::get('/customers/pppoe/service-profiles', [ServiceProfileController::class, 'index'])->name('customers.pppoe.service-profiles');
    Route::get('/customers/pppoe/service-profiles/create', [ServiceProfileController::class, 'create'])->name('customers.pppoe.service-profiles.create');
    Route::post('/customers/pppoe/service-profiles', [ServiceProfileController::class, 'store'])->name('customers.pppoe.service-profiles.store');
    Route::get('/customers/pppoe/service-profiles/{service_profile}/edit', [ServiceProfileController::class, 'edit'])->name('customers.pppoe.service-profiles.edit');
    Route::put('/customers/pppoe/service-profiles/{service_profile}', [ServiceProfileController::class, 'update'])->name('customers.pppoe.service-profiles.update');
    Route::delete('/customers/pppoe/service-profiles/{service_profile}', [ServiceProfileController::class, 'destroy'])->name('customers.pppoe.service-profiles.destroy');

    Route::get('/customers/pppoe/mikrotik-profiles', [MikrotikPppProfileController::class, 'index'])->name('customers.pppoe.mikrotik-profiles');
    Route::get('/customers/pppoe/mikrotik-profiles/create', [MikrotikPppProfileController::class, 'create'])->name('customers.pppoe.mikrotik-profiles.create');
    Route::get('/customers/pppoe/mikrotik-profiles/options', [MikrotikPppProfileController::class, 'options'])->name('customers.pppoe.mikrotik-profiles.options');
    Route::get('/customers/pppoe/mikrotik-profiles/pools', [MikrotikPppProfileController::class, 'pools'])->name('customers.pppoe.mikrotik-profiles.pools');
    Route::post('/customers/pppoe/mikrotik-profiles', [MikrotikPppProfileController::class, 'store'])->name('customers.pppoe.mikrotik-profiles.store');
    Route::get('/customers/pppoe/mikrotik-profiles/{router}/edit/{profile}', [MikrotikPppProfileController::class, 'edit'])->name('customers.pppoe.mikrotik-profiles.edit');
    Route::put('/customers/pppoe/mikrotik-profiles/{router}/{profile}', [MikrotikPppProfileController::class, 'update'])->name('customers.pppoe.mikrotik-profiles.update');
    Route::delete('/customers/pppoe/mikrotik-profiles/{router}/{profile}', [MikrotikPppProfileController::class, 'destroy'])->name('customers.pppoe.mikrotik-profiles.destroy');

    Route::get('/customers/pppoe/sessions', [PppoeSessionController::class, 'index'])->name('customers.pppoe.sessions');
    Route::delete('/customers/pppoe/sessions/{router}/{session}', [PppoeSessionController::class, 'disconnect'])->name('customers.pppoe.sessions.disconnect');

    Route::get('/customers/pppoe/{pppoe}/edit', [PppoeCustomerController::class, 'edit'])->whereNumber('pppoe')->name('customers.pppoe.edit');
    Route::put('/customers/pppoe/{pppoe}', [PppoeCustomerController::class, 'update'])->whereNumber('pppoe')->name('customers.pppoe.update');
    Route::delete('/customers/pppoe/{pppoe}', [PppoeCustomerController::class, 'destroy'])->whereNumber('pppoe')->name('customers.pppoe.destroy');
    Route::post('/customers/pppoe/{pppoe}/sync', [PppoeCustomerController::class, 'sync'])->whereNumber('pppoe')->name('customers.pppoe.sync');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('/billing/reports', [FinancialReportController::class, 'index'])->name('billing.reports');
    Route::get('/billing/agent-commissions', [AgentCommissionReportController::class, 'index'])->name('billing.agent-commissions');
    Route::get('/billing/payment-gateway', [PaymentGatewayController::class, 'index'])->name('billing.payment-gateway');
    Route::post('/billing/payment-gateway', [PaymentGatewayController::class, 'update'])->name('billing.payment-gateway.update');
    Route::post('/billing/payment-gateway/test', [PaymentGatewayController::class, 'test'])->name('billing.payment-gateway.test');

    Route::get('/messaging', [MessagingController::class, 'index'])->name('messaging.index');
    Route::post('/messaging', [MessagingController::class, 'update'])->name('messaging.update');
    Route::post('/messaging/templates', [MessagingController::class, 'updateTemplates'])->name('messaging.templates');
    Route::post('/messaging/test', [MessagingController::class, 'test'])->name('messaging.test');
    Route::post('/messaging/webhook', [MessagingController::class, 'setWebhook'])->name('messaging.webhook');
    Route::get('/messaging/whatsapp/status', [MessagingController::class, 'whatsappStatus'])->name('messaging.whatsapp.status');
    Route::post('/messaging/whatsapp/connect', [MessagingController::class, 'whatsappConnect'])->name('messaging.whatsapp.connect');
    Route::delete('/messaging/identities/{identity}', [MessagingController::class, 'unbind'])->name('messaging.unbind');
    Route::post('/billing/generate', [BillingController::class, 'generate'])->name('billing.generate');
    Route::post('/billing/bulk-pay', [BillingController::class, 'bulkPay'])->name('billing.bulk-pay');
    Route::post('/billing/customers/{pppoe}/grace', [BillingController::class, 'grantGrace'])->name('billing.grace');
    Route::delete('/billing/customers/{pppoe}/grace', [BillingController::class, 'clearGrace'])->name('billing.grace.clear');
    Route::post('/billing/customers/{pppoe}/combine-billing', [BillingController::class, 'combineBilling'])->name('billing.combine');
    Route::get('/billing/invoices/{invoice}', [BillingController::class, 'show'])->name('billing.show');
    Route::get('/billing/invoices/{invoice}/print', [BillingController::class, 'print'])->name('billing.print');
    Route::post('/billing/invoices/{invoice}/pay', [BillingController::class, 'pay'])->name('billing.pay');
    Route::post('/billing/invoices/{invoice}/online-pay', [BillingController::class, 'createOnlinePayment'])->name('billing.online-pay');
    Route::post('/billing/invoices/{invoice}/void', [BillingController::class, 'void'])->name('billing.void');
    Route::delete('/billing/invoices/{invoice}', [BillingController::class, 'destroy'])->name('billing.destroy');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    Route::get('/system', [AppSettingController::class, 'edit'])->name('system.index');
    Route::post('/system', [AppSettingController::class, 'update'])->name('system.update');

    Route::get('/system/update', [UpdateController::class, 'index'])->name('system.update.index');
    Route::post('/system/update/check', [UpdateController::class, 'check'])->name('system.update.check');
    Route::post('/system/update/pull', [UpdateController::class, 'pull'])->name('system.update.pull');

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
