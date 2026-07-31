<?php

namespace App\Http\Middleware;

use App\Support\AppSettings;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()
                    ? [
                        ...$request->user()->only('id', 'name', 'email', 'role'),
                        'role_label' => $request->user()->roleLabel(),
                        'avatar_url' => $request->user()->avatarUrl(),
                        'initials' => $request->user()->initials(),
                        'can_write' => $request->user()->canWrite(),
                        'can_manage_users' => $request->user()->canManageUsers(),
                    ]
                    : null,
            ],
            'app' => AppSettings::branding(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'generated_vouchers' => fn () => $request->session()->get('generated_vouchers'),
            ],
        ];
    }
}
