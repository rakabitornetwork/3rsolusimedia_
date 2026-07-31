<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanWrite
{
    /**
     * Blokir aksi tulis untuk role Teknisi (read only).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->canWrite()) {
            return $next($request);
        }

        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        // Logout tetap diizinkan.
        if ($request->routeIs('admin.logout')) {
            return $next($request);
        }

        if ($request->header('X-Inertia')) {
            return back()->with('error', 'Akun Teknisi hanya dapat melihat data (read only).');
        }

        abort(403, 'Akun Teknisi hanya dapat melihat data (read only).');
    }
}
