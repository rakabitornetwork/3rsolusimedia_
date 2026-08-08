<?php

use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\FinancialReportController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GenieAcsController;
use App\Http\Controllers\Admin\HotspotProfileController;
use App\Http\Controllers\Admin\HotspotSessionController;
use App\Http\Controllers\Admin\HotspotVoucherController;
use App\Http\Controllers\Admin\MikrotikPppProfileController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\NetworkMapController;
use App\Http\Controllers\Admin\PppoeCustomerController;
use App\Http\Controllers\Admin\PppoeSessionController;
use App\Http\Controllers\Admin\RouterOsController;
use App\Http\Controllers\Admin\SectionController;
use App\Http\Controllers\Admin\ServiceProfileController;
use App\Http\Controllers\Admin\AppSettingController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UpdateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WebsiteSectionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LegalPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');
Route::get('/terms-of-service', [LegalPageController::class, 'terms'])->name('terms');

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
    Route::post('/network/hotspot', [HotspotVoucherController::class, 'store'])->name('network.hotspot.store');

    Route::get('/network/hotspot/sessions', [HotspotSessionController::class, 'index'])->name('network.hotspot.sessions');
    Route::delete('/network/hotspot/sessions/{router}/{session}', [HotspotSessionController::class, 'disconnect'])
        ->name('network.hotspot.sessions.disconnect');

    Route::get('/network/hotspot/profiles', [HotspotProfileController::class, 'index'])->name('network.hotspot.profiles');
    Route::get('/network/hotspot/profiles/create', [HotspotProfileController::class, 'create'])->name('network.hotspot.profiles.create');
    Route::post('/network/hotspot/profiles', [HotspotProfileController::class, 'store'])->name('network.hotspot.profiles.store');
    Route::get('/network/hotspot/profiles/{router}/edit/{profile}', [HotspotProfileController::class, 'edit'])->name('network.hotspot.profiles.edit');
    Route::put('/network/hotspot/profiles/{router}/{profile}', [HotspotProfileController::class, 'update'])->name('network.hotspot.profiles.update');
    Route::delete('/network/hotspot/profiles/{router}/{profile}', [HotspotProfileController::class, 'destroy'])->name('network.hotspot.profiles.destroy');

    Route::post('/network/hotspot/{router}/{user}/toggle', [HotspotVoucherController::class, 'toggle'])->name('network.hotspot.toggle');
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
    Route::post('/billing/generate', [BillingController::class, 'generate'])->name('billing.generate');
    Route::post('/billing/customers/{pppoe}/grace', [BillingController::class, 'grantGrace'])->name('billing.grace');
    Route::delete('/billing/customers/{pppoe}/grace', [BillingController::class, 'clearGrace'])->name('billing.grace.clear');
    Route::post('/billing/customers/{pppoe}/combine-billing', [BillingController::class, 'combineBilling'])->name('billing.combine');
    Route::get('/billing/invoices/{invoice}', [BillingController::class, 'show'])->name('billing.show');
    Route::get('/billing/invoices/{invoice}/print', [BillingController::class, 'print'])->name('billing.print');
    Route::post('/billing/invoices/{invoice}/pay', [BillingController::class, 'pay'])->name('billing.pay');
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
