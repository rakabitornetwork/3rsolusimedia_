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
            'settings' => AppSettings::all(),
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
            'app_notif_whatsapp' => ['sometimes', 'boolean'],
            'app_notif_email' => ['sometimes', 'boolean'],
            'app_auto_isolir' => ['sometimes', 'boolean'],
            'app_logo_mark' => ['nullable', 'image', 'max:2048'],
            'app_logo_full' => ['nullable', 'image', 'max:4096'],
            'app_favicon' => ['nullable', 'image', 'max:1024'],
            'remove_logo_mark' => ['sometimes', 'boolean'],
            'remove_logo_full' => ['sometimes', 'boolean'],
            'remove_favicon' => ['sometimes', 'boolean'],
        ]);

        $values = [
            'app_timezone' => $validated['app_timezone'],
            'app_currency_label' => $validated['app_currency_label'],
            'app_invoice_prefix' => strtoupper($validated['app_invoice_prefix']),
            'app_billing_generate_days' => (string) $validated['app_billing_generate_days'],
            'app_billing_round_to' => (string) $validated['app_billing_round_to'],
            'app_default_billing_day' => (string) $validated['app_default_billing_day'],
            'app_notif_whatsapp' => $request->boolean('app_notif_whatsapp') ? '1' : '0',
            'app_notif_email' => $request->boolean('app_notif_email') ? '1' : '0',
            'app_auto_isolir' => $request->boolean('app_auto_isolir') ? '1' : '0',
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

        SiteSetting::setMany($values);

        return redirect()
            ->route('admin.system.index')
            ->with('success', 'Pengaturan aplikasi berhasil disimpan.');
    }

    private function deleteUploadedAsset(?string $path, string $default): void
    {
        if (! $path || $path === $default || ! str_starts_with($path, '/storage/')) {
            return;
        }

        Storage::disk('public')->delete(str_replace('/storage/', '', $path));
    }
}
