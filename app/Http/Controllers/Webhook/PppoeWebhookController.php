<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\MikrotikRouter;
use App\Services\PppoeSessionMonitor;
use App\Support\AppSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PppoeWebhookController extends Controller
{
    public function __invoke(Request $request, PppoeSessionMonitor $monitor): Response
    {
        $token = trim((string) $request->input('token', $request->query('token', '')));
        $secret = AppSettings::pppoeWebhookSecret();

        if ($secret === '' || $token === '' || ! hash_equals($secret, $token)) {
            return response('FORBIDDEN', 403);
        }

        $event = strtolower(trim((string) $request->input('event', $request->query('event', ''))));
        if ($event === 'ping' || $event === 'test') {
            return response('OK', 200);
        }

        if (! in_array($event, ['up', 'down'], true)) {
            return response('BAD_EVENT', 200);
        }

        $username = trim((string) $request->input('user', $request->query('user', '')));
        if ($username === '') {
            return response('NO_USER', 200);
        }

        $router = $this->resolveRouter($request);
        if (! $router) {
            return response('UNKNOWN_ROUTER', 200);
        }

        $monitor->handlePush($router, $event, $username, [
            'name' => $username,
            'address' => $request->input('ip', $request->query('ip')),
            'caller_id' => $request->input('mac', $request->query('mac')),
        ]);

        return response('OK', 200);
    }

    private function resolveRouter(Request $request): ?MikrotikRouter
    {
        $id = (int) $request->input('router', $request->query('router', 0));
        if ($id > 0) {
            return MikrotikRouter::query()->find($id);
        }

        $name = trim((string) $request->input('router_name', $request->query('router_name', '')));
        if ($name === '') {
            return null;
        }

        return MikrotikRouter::query()->where('name', $name)->first();
    }
}
