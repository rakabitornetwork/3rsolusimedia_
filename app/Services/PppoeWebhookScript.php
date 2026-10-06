<?php

namespace App\Services;

use App\Models\MikrotikRouter;
use App\Models\SiteSetting;
use App\Support\AppSettings;
use Illuminate\Support\Str;

class PppoeWebhookScript
{
    public static function ensureSecret(): string
    {
        $secret = AppSettings::pppoeWebhookSecret();
        if ($secret !== '') {
            return $secret;
        }

        $secret = Str::lower(Str::random(40));
        SiteSetting::setValue('pppoe_webhook_secret', $secret);

        return $secret;
    }

    public static function regenerateSecret(): string
    {
        $secret = Str::lower(Str::random(40));
        SiteSetting::setValue('pppoe_webhook_secret', $secret);

        return $secret;
    }

    /**
     * @return list<array{
     *     router_id: int,
     *     router_name: string,
     *     ping: string,
     *     on_up: string,
     *     on_down: string,
     *     apply_up: string,
     *     apply_down: string,
     *     apply_all: string,
     *     all: string
     * }>
     */
    public static function forActiveRouters(): array
    {
        $secret = self::ensureSecret();
        $base = url('/webhooks/pppoe');

        return MikrotikRouter::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (MikrotikRouter $router) => self::forRouter($router, $base, $secret))
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     router_id: int,
     *     router_name: string,
     *     ping: string,
     *     on_up: string,
     *     on_down: string,
     *     apply_up: string,
     *     apply_down: string,
     *     apply_all: string,
     *     all: string
     * }
     */
    public static function forRouter(MikrotikRouter $router, ?string $base = null, ?string $secret = null): array
    {
        $base ??= url('/webhooks/pppoe');
        $secret ??= self::ensureSecret();
        $ping = self::fetchCommand($base, $secret, (int) $router->id, 'ping');
        $onUp = self::fetchCommand($base, $secret, (int) $router->id, 'up');
        $onDown = self::fetchCommand($base, $secret, (int) $router->id, 'down');
        $applyUp = '/ppp profile set [find] on-up='.self::quoteCli($onUp);
        $applyDown = '/ppp profile set [find] on-down='.self::quoteCli($onDown);

        return [
            'router_id' => (int) $router->id,
            'router_name' => $router->name,
            'ping' => $ping,
            'on_up' => $onUp,
            'on_down' => $onDown,
            'apply_up' => $applyUp,
            'apply_down' => $applyDown,
            'apply_all' => $applyUp."\n".$applyDown,
            'all' => implode("\n", [$ping, $applyUp, $applyDown]),
        ];
    }

    /**
     * Script body for Winbox PPP profile On Up / On Down (already a script editor).
     */
    private static function fetchCommand(string $base, string $secret, int $routerId, string $event): string
    {
        $query = $base.'?token='.rawurlencode($secret).'&event='.$event.'&router='.$routerId;

        if ($event === 'ping') {
            return '/tool fetch url="'.$query.'" keep-result=no check-certificate=no';
        }

        if ($event === 'up') {
            return '/tool fetch url=("'.$query.'&user=".$user."&ip=".$"remote-address"."&mac=".$"caller-id") keep-result=no check-certificate=no';
        }

        return '/tool fetch url=("'.$query.'&user=".$user) keep-result=no check-certificate=no';
    }

    /**
     * Quote a script as a RouterOS CLI property value.
     * PPP profile on-up/on-down reject { ... } blocks (syntax error at `{`).
     */
    private static function quoteCli(string $script): string
    {
        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $script).'"';
    }
}
