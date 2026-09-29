<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Support\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AppSettingController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Admin/System/Settings', [
            'settings' => [
                ...AppSettings::all(),
                'bank_accounts' => AppSettings::bankAccounts(),
            ],
            'branding' => AppSettings::branding(),
            'timezones' => [
                'Asia/Jakarta',
                'Asia/Makassar',
                'Asia/Jayapura',
                'UTC',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_timezone' => ['required', 'string', 'max:64', Rule::in([
                'Asia/Jakarta',
                'Asia/Makassar',
                'Asia/Jayapura',
                'UTC',
            ])],
            'app_currency_label' => ['required', 'string', 'max:10'],
            'app_invoice_prefix' => ['required', 'string', 'max:20', 'alpha_dash'],
            'app_billing_generate_days' => ['required', 'integer', 'min:1', 'max:31'],
            'app_billing_round_to' => ['required', 'integer', 'min:1', 'max:100000'],
            'app_default_billing_day' => ['required', 'integer', 'min:1', 'max:28'],
            'bank_accounts' => ['nullable', 'array', 'max:'.AppSettings::MAX_BANK_ACCOUNTS],
            'bank_accounts.*.bank_name' => ['nullable', 'string', 'max:80'],
            'bank_accounts.*.bank_account_name' => ['nullable', 'string', 'max:120'],
            'bank_accounts.*.bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_accounts.*.bank_note' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:80'],
            'bank_account_name' => ['nullable', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_note' => ['nullable', 'string', 'max:255'],
            'app_auto_isolir' => ['sometimes', 'boolean'],
            'app_logo_mark' => ['nullable', 'image', 'max:2048'],
            'app_logo_full' => ['nullable', 'image', 'max:4096'],
            'app_favicon' => ['nullable', 'image', 'max:1024'],
            'remove_logo_mark' => ['sometimes', 'boolean'],
            'remove_logo_full' => ['sometimes', 'boolean'],
            'remove_favicon' => ['sometimes', 'boolean'],
            'portal_banner_enabled' => ['sometimes', 'boolean'],
            'portal_banner_title' => ['nullable', 'string', 'max:80'],
            'portal_banner_subtitle' => ['nullable', 'string', 'max:160'],
            'portal_banner_link' => ['nullable', 'string', 'max:500', function (string $attribute, mixed $value, \Closure $fail): void {
                $link = trim((string) $value);
                if ($link === '') {
                    return;
                }

                $internal = str_starts_with($link, '/') && ! str_starts_with($link, '//');
                $external = filter_var($link, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $link) === 1;
                if (! $internal && ! $external) {
                    $fail('Tautan iklan harus URL http(s) atau path internal yang diawali /.');
                }
            }],
            'portal_banner_image' => ['nullable', 'image', 'max:5120'],
            'remove_portal_banner' => ['sometimes', 'boolean'],
        ]);

        $values = [
            'app_timezone' => $validated['app_timezone'],
            'app_currency_label' => $validated['app_currency_label'],
            'app_invoice_prefix' => strtoupper($validated['app_invoice_prefix']),
            'app_billing_generate_days' => (string) $validated['app_billing_generate_days'],
            'app_billing_round_to' => (string) $validated['app_billing_round_to'],
            'app_default_billing_day' => (string) $validated['app_default_billing_day'],
            'app_auto_isolir' => $request->boolean('app_auto_isolir') ? '1' : '0',
            ...AppSettings::bankAccountSettingValues($this->bankAccountsFromRequest($validated)),
        ];

        foreach ([
            'app_logo_mark' => [AppSettings::DEFAULT_LOGO_MARK, 'remove_logo_mark'],
            'app_logo_full' => [AppSettings::DEFAULT_LOGO_FULL, 'remove_logo_full'],
            'app_favicon' => [AppSettings::DEFAULT_FAVICON, 'remove_favicon'],
        ] as $key => [$default, $removeFlag]) {
            $current = (string) AppSettings::get($key, $default);

            if ($request->boolean($removeFlag)) {
                $this->deleteUploadedAsset($current, $default);
                $values[$key] = $default;
            } else {
                $values[$key] = $current ?: $default;
            }

            if ($request->hasFile($key)) {
                $this->deleteUploadedAsset($values[$key], $default);
                $values[$key] = '/storage/'.$request->file($key)->store('uploads/branding', 'public');
            }
        }

        if ($request->exists('portal_banner_enabled')) {
            $values['portal_banner_enabled'] = $request->boolean('portal_banner_enabled') ? '1' : '0';
        }
        foreach (['portal_banner_title', 'portal_banner_subtitle', 'portal_banner_link'] as $key) {
            if (array_key_exists($key, $validated)) {
                $values[$key] = trim((string) ($validated[$key] ?? ''));
            }
        }

        $currentBanner = (string) AppSettings::get('portal_banner_image', '');
        if ($request->boolean('remove_portal_banner')) {
            $this->deleteUploadedAsset($currentBanner, '');
            $values['portal_banner_image'] = '';
        } elseif ($request->hasFile('portal_banner_image')) {
            $this->deleteUploadedAsset($currentBanner, '');
            $values['portal_banner_image'] = '/storage/'.$request->file('portal_banner_image')->store('uploads/portal-banner', 'public');
        }

        SiteSetting::setMany($values);

        return redirect()
            ->route('admin.system.index')
            ->with('success', 'Pengaturan aplikasi berhasil disimpan.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<array{bank_name: string, bank_account_name: string, bank_account_number: string, bank_note: string}>
     */
    private function bankAccountsFromRequest(array $validated): array
    {
        if (array_key_exists('bank_accounts', $validated) && is_array($validated['bank_accounts'])) {
            return AppSettings::normalizeBankAccounts($validated['bank_accounts']);
        }

        return AppSettings::normalizeBankAccounts([[
            'bank_name' => $validated['bank_name'] ?? '',
            'bank_account_name' => $validated['bank_account_name'] ?? '',
            'bank_account_number' => $validated['bank_account_number'] ?? '',
            'bank_note' => $validated['bank_note'] ?? '',
        ]]);
    }

    private function deleteUploadedAsset(?string $path, string $default): void
    {
        if (! $path || $path === $default || ! str_starts_with($path, '/storage/')) {
            return;
        }

        Storage::disk('public')->delete(str_replace('/storage/', '', $path));
    }
}
