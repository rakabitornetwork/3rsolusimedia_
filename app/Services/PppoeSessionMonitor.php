<?php

namespace App\Services;

use App\Models\MessageLog;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Services\Messaging\MessagingManager;
use App\Support\AppSettings;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class PppoeSessionMonitor
{
    public const CACHE_TTL_HOURS = 48;

    public const MASS_MIN_EVENTS = 10;

    public const MASS_RATIO = 0.4;

    public function __construct(
        private readonly MikrotikApiService $api,
        private readonly MessagingManager $channels,
        private readonly GenieAcsService $genie,
    ) {}

    public function isEnabled(): bool
    {
        return AppSettings::bool('messaging_notify_pppoe_session', false)
            && $this->channels->driver('telegram')->isEnabled()
            && AppSettings::telegramAdminChatIds() !== [];
    }

    public function debounceMinutes(): int
    {
        return max(0, min(30, AppSettings::int('messaging_pppoe_session_debounce', 3)));
    }

    /**
     * Event realtime dari script RouterOS (on-up / on-down).
     *
     * @param  array<string, mixed>  $session
     * @return array{ok: bool, message: string, sent: int, type?: string}
     */
    public function handlePush(MikrotikRouter $router, string $event, string $username, array $session = []): array
    {
        if (! $this->isEnabled()) {
            return [
                'ok' => false,
                'message' => 'Notifikasi sesi PPPoE nonaktif, Telegram belum aktif, atau Chat ID admin kosong.',
                'sent' => 0,
            ];
        }

        $username = strtolower(trim($username));
        if ($username === '') {
            return ['ok' => false, 'message' => 'Username kosong.', 'sent' => 0];
        }

        $event = strtolower($event) === 'down' ? 'down' : 'up';
        $lock = Cache::lock('pppoe:session-watch:'.$router->id, 15);

        try {
            return $lock->block(8, fn () => $this->processPush($router, $event, $username, $session));
        } catch (LockTimeoutException) {
            return ['ok' => false, 'message' => 'Pemantauan sesi masih sibuk.', 'sent' => 0];
        }
    }

    /**
     * @return array{
     *     enabled: bool,
     *     routers_ok: int,
     *     routers_fail: int,
     *     first_snapshots: int,
     *     connected: int,
     *     disconnected: int,
     *     flaps: int,
     *     mass_events: int,
     *     sent: int,
     *     events: list<array<string, mixed>>,
     *     message: string
     * }
     */
    public function run(bool $persist = true, bool $send = true): array
    {
        $summary = [
            'enabled' => $this->isEnabled(),
            'routers_ok' => 0,
            'routers_fail' => 0,
            'first_snapshots' => 0,
            'connected' => 0,
            'disconnected' => 0,
            'flaps' => 0,
            'mass_events' => 0,
            'sent' => 0,
            'events' => [],
            'message' => '',
        ];

        if (! $summary['enabled'] && $send) {
            $summary['message'] = 'Notifikasi sesi PPPoE nonaktif, Telegram belum aktif, atau Chat ID admin kosong.';

            return $summary;
        }

        $routers = MikrotikRouter::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        foreach ($routers as $router) {
            $result = $this->pollRouter($router, $persist, $send);
            $summary['routers_ok'] += $result['ok'] ? 1 : 0;
            $summary['routers_fail'] += $result['ok'] ? 0 : 1;
            $summary['first_snapshots'] += $result['first_snapshot'] ? 1 : 0;
            $summary['connected'] += $result['connected'];
            $summary['disconnected'] += $result['disconnected'];
            $summary['flaps'] += $result['flaps'];
            $summary['mass_events'] += $result['mass_events'];
            $summary['sent'] += $result['sent'];
            $summary['events'] = array_merge($summary['events'], $result['events']);
        }

        $summary['message'] = sprintf(
            'Router OK %d, gagal %d, connected %d, disconnected %d, flap %d, mass %d, pesan %d.',
            $summary['routers_ok'],
            $summary['routers_fail'],
            $summary['connected'],
            $summary['disconnected'],
            $summary['flaps'],
            $summary['mass_events'],
            $summary['sent'],
        );

        return $summary;
    }

    /**
     * @return array{
     *     ok: bool,
     *     first_snapshot: bool,
     *     connected: int,
     *     disconnected: int,
     *     flaps: int,
     *     mass_events: int,
     *     sent: int,
     *     events: list<array<string, mixed>>
     * }
     */
    private function pollRouter(MikrotikRouter $router, bool $persist, bool $send): array
    {
        $empty = [
            'ok' => false,
            'first_snapshot' => false,
            'connected' => 0,
            'disconnected' => 0,
            'flaps' => 0,
            'mass_events' => 0,
            'sent' => 0,
            'events' => [],
        ];

        $fetched = $this->api->listPppActiveSessions($router);
        if (! ($fetched['ok'] ?? false)) {
            return $empty;
        }

        $current = $this->mergeInterfaceBytes(
            $router,
            $this->indexSessions($fetched['sessions'] ?? []),
        );
        $previous = $this->rememberedOnline($router);
        $pending = $this->rememberedPending($router);

        if ($previous === null) {
            if ($persist) {
                $this->storeOnline($router, $current);
                $this->storePending($router, []);
            }

            $empty['ok'] = true;
            $empty['first_snapshot'] = true;

            return $empty;
        }

        $appeared = array_values(array_diff(array_keys($current), array_keys($previous)));
        $disappeared = array_values(array_diff(array_keys($previous), array_keys($current)));

        $flaps = 0;
        $notifyUp = [];
        foreach ($appeared as $username) {
            if (isset($pending[$username])) {
                unset($pending[$username]);
                $flaps++;

                continue;
            }

            $notifyUp[$username] = $current[$username];
        }

        $now = now();
        $debounce = $this->debounceMinutes();
        $confirmedDown = [];

        if ($this->isMassEvent(count($disappeared), count($previous))) {
            foreach ($disappeared as $username) {
                unset($pending[$username]);
            }
        } else {
            foreach ($disappeared as $username) {
                if (! isset($pending[$username])) {
                    $pending[$username] = [
                        'since' => $now->toIso8601String(),
                        'session' => $previous[$username],
                    ];
                }
            }
        }

        foreach ($pending as $username => $info) {
            if (isset($current[$username])) {
                unset($pending[$username]);

                continue;
            }

            $since = $this->parseTime($info['since'] ?? null) ?? $now;
            if ($debounce === 0 || $since->lte($now->copy()->subMinutes($debounce))) {
                $confirmedDown[$username] = is_array($info['session'] ?? null)
                    ? $info['session']
                    : ($previous[$username] ?? []);
                unset($pending[$username]);
            }
        }

        $customers = $this->customerMap($router, array_values(array_unique(array_merge(
            array_keys($notifyUp),
            array_keys($confirmedDown),
            $disappeared,
        ))));

        $sent = 0;
        $events = [];
        $massEvents = 0;
        $massDown = $this->isMassEvent(count($disappeared), count($previous));
        $massUp = $this->isMassEvent(count($notifyUp), max(count($previous), count($current)));
        $connectedCount = count($notifyUp);
        $disconnectedCount = count($confirmedDown) + ($massDown ? count($disappeared) : 0);

        if ($massDown) {
            $massEvents++;
            $event = [
                'type' => 'mass_down',
                'router_id' => $router->id,
                'router' => $router->name,
                'count' => count($disappeared),
                'previous' => count($previous),
                'current' => count($current),
            ];
            $events[] = $event;
            $sent += $this->deliver($event, $this->massMessage($router, 'down', count($disappeared), count($previous), count($current)), $send);
        }

        if ($massUp) {
            $massEvents++;
            $event = [
                'type' => 'mass_up',
                'router_id' => $router->id,
                'router' => $router->name,
                'count' => count($notifyUp),
                'previous' => count($previous),
                'current' => count($current),
            ];
            $events[] = $event;
            $sent += $this->deliver($event, $this->massMessage($router, 'up', count($notifyUp), count($previous), count($current)), $send);
            $notifyUp = [];
        }

        foreach ($notifyUp as $username => $session) {
            $customer = $customers->get($username);
            $event = $this->sessionEvent('up', $router, $username, $session, $customer);
            $events[] = $event;
            $sent += $this->deliver($event, $this->sessionMessage($event), $send, $customer?->id);
        }

        foreach ($confirmedDown as $username => $session) {
            $customer = $customers->get($username);
            $event = $this->sessionEvent('down', $router, $username, $session, $customer);
            $events[] = $event;
            $sent += $this->deliver($event, $this->sessionMessage($event), $send, $customer?->id);
        }

        if ($persist) {
            $this->storeOnline($router, $current);
            $this->storePending($router, $pending);
        }

        return [
            'ok' => true,
            'first_snapshot' => false,
            'connected' => $connectedCount,
            'disconnected' => $disconnectedCount,
            'flaps' => $flaps,
            'mass_events' => $massEvents,
            'sent' => $sent,
            'events' => $events,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $sessions
     * @return array<string, array<string, mixed>>
     */
    private function indexSessions(array $sessions): array
    {
        $indexed = [];
        foreach ($sessions as $session) {
            $username = strtolower(trim((string) ($session['name'] ?? '')));
            if ($username === '') {
                continue;
            }

            $indexed[$username] = [
                'name' => (string) ($session['name'] ?? $username),
                'address' => $session['address'] ?? null,
                'caller_id' => $session['caller_id'] ?? null,
                'uptime' => $session['uptime'] ?? null,
                'rx_byte' => $session['rx_byte'] ?? null,
                'tx_byte' => $session['tx_byte'] ?? null,
            ];
        }

        return $indexed;
    }

    /**
     * @param  array<string, mixed>  $session
     * @return array{ok: bool, message: string, sent: int, type?: string}
     */
    private function processPush(MikrotikRouter $router, string $event, string $username, array $session): array
    {
        $online = $this->rememberedOnline($router) ?? [];
        $pending = $this->rememberedPending($router);
        $row = [
            'name' => (string) ($session['name'] ?? $username),
            'address' => $session['address'] ?? $session['ip'] ?? null,
            'caller_id' => $session['caller_id'] ?? $session['mac'] ?? null,
            'uptime' => $session['uptime'] ?? null,
            'rx_byte' => $session['rx_byte'] ?? null,
            'tx_byte' => $session['tx_byte'] ?? null,
        ];

        if ($event === 'up') {
            if (isset($pending[$username])) {
                unset($pending[$username]);
                $online[$username] = $row;
                $this->storeOnline($router, $online);
                $this->storePending($router, $pending);

                return ['ok' => true, 'message' => 'Reconnect singkat diabaikan.', 'sent' => 0, 'type' => 'flap'];
            }

            $online[$username] = $row;
            $burst = $this->incrementBurst($router, 'up');
            $this->storeOnline($router, $online);
            $this->storePending($router, $pending);

            if ($this->isMassEvent($burst, max(count($online), 1))) {
                $sent = 0;
                if ($this->markMassSent($router, 'up')) {
                    $payload = [
                        'type' => 'mass_up',
                        'router_id' => $router->id,
                        'router' => $router->name,
                        'count' => $burst,
                    ];
                    $sent = $this->deliver(
                        $payload,
                        $this->massMessage($router, 'up', $burst, max(0, count($online) - 1), count($online)),
                        true,
                    );
                }

                return ['ok' => true, 'message' => 'Mass connect diringkas.', 'sent' => $sent, 'type' => 'mass_up'];
            }

            $row = $this->withInterfaceBytes($router, $username, $row);
            $online[$username] = $row;
            $this->storeOnline($router, $online);

            $customer = $this->findCustomer($router, $username);
            $payload = $this->sessionEvent('up', $router, $username, $row, $customer);
            $sent = $this->deliver($payload, $this->sessionMessage($payload), true, $customer?->id);

            return ['ok' => true, 'message' => 'PPPoE connected.', 'sent' => $sent, 'type' => 'up'];
        }

        $previous = $this->withInterfaceBytes($router, $username, $online[$username] ?? $row, refresh: true);
        unset($online[$username]);
        $burst = $this->incrementBurst($router, 'down');

        if ($this->isMassEvent($burst, max(count($online) + $burst, 1))) {
            $this->storeOnline($router, $online);
            $this->storePending($router, []);
            $sent = 0;
            if ($this->markMassSent($router, 'down')) {
                $payload = [
                    'type' => 'mass_down',
                    'router_id' => $router->id,
                    'router' => $router->name,
                    'count' => $burst,
                ];
                $sent = $this->deliver(
                    $payload,
                    $this->massMessage($router, 'down', $burst, $burst + count($online), count($online)),
                    true,
                );
            }

            return ['ok' => true, 'message' => 'Mass disconnect diringkas.', 'sent' => $sent, 'type' => 'mass_down'];
        }

        $debounce = $this->debounceMinutes();
        if ($debounce === 0) {
            unset($pending[$username]);
            $this->storeOnline($router, $online);
            $this->storePending($router, $pending);
            $customer = $this->findCustomer($router, $username);
            $payload = $this->sessionEvent('down', $router, $username, $previous, $customer);
            $sent = $this->deliver($payload, $this->sessionMessage($payload), true, $customer?->id);

            return ['ok' => true, 'message' => 'PPPoE disconnected.', 'sent' => $sent, 'type' => 'down'];
        }

        if (! isset($pending[$username])) {
            $pending[$username] = [
                'since' => now()->toIso8601String(),
                'session' => $previous,
            ];
        }
        $this->storeOnline($router, $online);
        $this->storePending($router, $pending);

        return ['ok' => true, 'message' => 'Disconnect ditunda sesuai jeda.', 'sent' => 0, 'type' => 'pending'];
    }

    private function findCustomer(MikrotikRouter $router, string $username): ?PppoeCustomer
    {
        return PppoeCustomer::query()
            ->with('package')
            ->where('mikrotik_router_id', $router->id)
            ->whereRaw('LOWER(username) = ?', [$username])
            ->first();
    }

    private function incrementBurst(MikrotikRouter $router, string $direction): int
    {
        $key = 'pppoe:webhook-burst:'.$router->id.':'.$direction;
        $now = now();
        $data = Cache::get($key);
        $started = is_array($data) ? $this->parseTime($data['started'] ?? null) : null;
        if (! is_array($data) || ! $started || $started->lt($now->copy()->subMinutes(2))) {
            $data = ['started' => $now->toIso8601String(), 'count' => 0];
        }
        $data['count'] = (int) ($data['count'] ?? 0) + 1;
        Cache::put($key, $data, $now->copy()->addMinutes(5));

        return (int) $data['count'];
    }

    private function markMassSent(MikrotikRouter $router, string $direction): bool
    {
        $key = 'pppoe:webhook-mass:'.$router->id.':'.$direction;
        if (Cache::get($key)) {
            return false;
        }
        Cache::put($key, 1, now()->addMinutes(10));

        return true;
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    private function rememberedOnline(MikrotikRouter $router): ?array
    {
        $value = Cache::get($this->onlineKey($router));

        return is_array($value) ? $value : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function rememberedPending(MikrotikRouter $router): array
    {
        $value = Cache::get($this->pendingKey($router));

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $online
     */
    private function storeOnline(MikrotikRouter $router, array $online): void
    {
        Cache::put($this->onlineKey($router), $online, now()->addHours(self::CACHE_TTL_HOURS));
    }

    /**
     * @param  array<string, array<string, mixed>>  $pending
     */
    private function storePending(MikrotikRouter $router, array $pending): void
    {
        Cache::put($this->pendingKey($router), $pending, now()->addHours(self::CACHE_TTL_HOURS));
    }

    private function onlineKey(MikrotikRouter $router): string
    {
        return 'pppoe:session-watch:'.$router->id.':online';
    }

    private function pendingKey(MikrotikRouter $router): string
    {
        return 'pppoe:session-watch:'.$router->id.':pending';
    }

    /**
     * @param  list<string>  $usernames
     * @return Collection<string, PppoeCustomer>
     */
    private function customerMap(MikrotikRouter $router, array $usernames): Collection
    {
        $usernames = array_values(array_unique(array_filter($usernames, fn (string $name) => $name !== '')));

        $query = PppoeCustomer::query()
            ->with('package')
            ->where('mikrotik_router_id', $router->id);

        if ($usernames !== []) {
            $placeholders = implode(',', array_fill(0, count($usernames), '?'));
            $query->orWhereRaw('LOWER(username) in ('.$placeholders.')', $usernames);
        }

        return $query->get()->keyBy(
            fn (PppoeCustomer $customer) => strtolower(trim((string) $customer->username))
        );
    }

    private function isMassEvent(int $changed, int $baseline): bool
    {
        if ($changed < self::MASS_MIN_EVENTS) {
            return false;
        }

        if ($baseline <= 0) {
            return true;
        }

        return $changed >= (int) ceil($baseline * self::MASS_RATIO);
    }

    /**
     * @param  array<string, mixed>  $session
     * @return array<string, mixed>
     */
    private function sessionEvent(
        string $type,
        MikrotikRouter $router,
        string $username,
        array $session,
        ?PppoeCustomer $customer,
    ): array {
        $optical = $this->opticalSnapshot($customer);

        return [
            'type' => $type,
            'router_id' => $router->id,
            'router' => $router->name,
            'username' => $username,
            'name' => $customer?->name,
            'phone' => $customer?->phone,
            'customer_address' => $customer?->address,
            'password_masked' => $this->maskedPassword($customer),
            'status' => $customer?->status,
            'package' => $customer?->package?->name,
            'address' => $session['address'] ?? null,
            'caller_id' => $session['caller_id'] ?? null,
            'uptime' => $session['uptime'] ?? null,
            'rx_byte' => $session['rx_byte'] ?? null,
            'tx_byte' => $session['tx_byte'] ?? null,
            'rx_power' => $optical['rx_power_label'] ?? null,
            'temperature' => $optical['temperature_label'] ?? null,
            'registered' => $customer !== null,
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function sessionMessage(array $event): string
    {
        $up = ($event['type'] ?? '') === 'up';
        $lines = [
            $up ? '🟢 PPPoE connected' : '🔴 PPPoE disconnected',
            '',
            '🖥 Router: '.$this->dash($event['router'] ?? null),
            '👤 Nama: '.$this->dash($event['name'] ?? null),
            '🔑 Username: '.$this->dash($event['username'] ?? null),
            '🔐 Password: '.$this->dash($event['password_masked'] ?? null),
            '📱 HP: '.$this->dash($event['phone'] ?? null),
            '📍 Alamat: '.$this->dash($event['customer_address'] ?? null),
            'Status: '.$this->statusLabel($event['status'] ?? null),
            '📦 Paket: '.$this->dash($event['package'] ?? null),
            '🌐 IP: '.$this->dash($event['address'] ?? null),
            '📟 MAC: '.$this->dash($event['caller_id'] ?? null),
            '⬇️ Rx: '.$this->formatBytes($event['rx_byte'] ?? null),
            '⬆️ Tx: '.$this->formatBytes($event['tx_byte'] ?? null),
            '📶 Rx power: '.$this->dash($event['rx_power'] ?? null),
            '🌡 Suhu: '.$this->dash($event['temperature'] ?? null),
        ];

        if ($up && ! empty($event['uptime'])) {
            $lines[] = '⏱ Uptime: '.$event['uptime'];
        }

        if (empty($event['registered'])) {
            $lines[] = '';
            $lines[] = '⚠️ Username belum terdaftar di panel.';
        }

        $lines[] = '';
        $lines[] = $this->stamp();

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, array<string, mixed>>  $current
     * @return array<string, array<string, mixed>>
     */
    private function mergeInterfaceBytes(MikrotikRouter $router, array $current): array
    {
        if ($current === []) {
            return $current;
        }

        try {
            $map = $this->api->pppoeInterfaceBytesMap($router);
        } catch (Throwable) {
            return $current;
        }

        foreach ($current as $user => $session) {
            if (! isset($map[$user])) {
                continue;
            }

            $current[$user]['rx_byte'] = $map[$user]['rx_byte'];
            $current[$user]['tx_byte'] = $map[$user]['tx_byte'];
        }

        return $current;
    }

    /**
     * @param  array<string, mixed>  $session
     * @return array<string, mixed>
     */
    private function withInterfaceBytes(MikrotikRouter $router, string $username, array $session, bool $refresh = false): array
    {
        $hasBytes = isset($session['rx_byte'], $session['tx_byte']);
        if ($hasBytes && ! $refresh) {
            return $session;
        }

        try {
            $bytes = $this->api->pppoeInterfaceBytes($router, $username);
        } catch (Throwable) {
            return $session;
        }

        if (! $bytes) {
            return $session;
        }

        $session['rx_byte'] = $bytes['rx_byte'];
        $session['tx_byte'] = $bytes['tx_byte'];

        return $session;
    }

    /**
     * @return array{rx_power_label: ?string, temperature_label: ?string}
     */
    private function opticalSnapshot(?PppoeCustomer $customer): array
    {
        $empty = ['rx_power_label' => null, 'temperature_label' => null];
        $username = strtolower(trim((string) ($customer?->username ?? '')));
        if ($username === '' || ! $this->genie->isConfigured()) {
            return $empty;
        }

        $key = 'pppoe:optical:'.$username;
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $result = $this->genie->findDeviceByPppoeUsername($username);
            $device = is_array($result['device'] ?? null) ? $result['device'] : null;
            $snap = $empty;
            if (($result['ok'] ?? false) && $device) {
                $rx = trim((string) ($device['rx_power_label'] ?? ''));
                $temp = trim((string) ($device['temperature_label'] ?? ''));
                $snap = [
                    'rx_power_label' => ($rx !== '' && $rx !== '—') ? $rx : null,
                    'temperature_label' => ($temp !== '' && $temp !== '—') ? $temp : null,
                ];
            }
            Cache::put($key, $snap, now()->addMinutes(5));

            return $snap;
        } catch (Throwable) {
            return $empty;
        }
    }

    private function maskedPassword(?PppoeCustomer $customer): ?string
    {
        $plain = (string) ($customer?->password ?? '');
        if ($plain === '') {
            return null;
        }

        $len = mb_strlen($plain);
        if ($len <= 2) {
            return str_repeat('*', 4);
        }

        return mb_substr($plain, 0, 1).str_repeat('*', $len - 2).mb_substr($plain, -1);
    }

    private function formatBytes(mixed $bytes): string
    {
        if ($bytes === null || $bytes === '') {
            return '—';
        }

        $value = (float) $bytes;
        if ($value < 0) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        $decimals = $i === 0 ? 0 : 1;

        return number_format($value, $decimals, ',', '.').' '.$units[$i];
    }

    private function massMessage(MikrotikRouter $router, string $direction, int $changed, int $previous, int $current): string
    {
        $down = $direction === 'down';

        return implode("\n", [
            $down ? '⚠️ Banyak sesi PPPoE terputus' : '⚠️ Banyak sesi PPPoE kembali online',
            '',
            '🖥 Router: '.$router->name,
            ($down ? '🔴 Terputus: ' : '🟢 Connected: ').$changed
                .' (sebelumnya '.$previous.', sekarang '.$current.')',
            '',
            'Notifikasi per pelanggan dilewati — kemungkinan reboot, gangguan, atau isolir massal.',
            '',
            $this->stamp(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function deliver(array $event, string $body, bool $send, ?int $customerId = null): int
    {
        if (! $send) {
            return 0;
        }

        $sent = 0;
        $command = match ($event['type'] ?? '') {
            'up' => 'pppoe_up',
            'down' => 'pppoe_down',
            'mass_up' => 'pppoe_mass_up',
            default => 'pppoe_mass_down',
        };

        foreach (AppSettings::telegramAdminChatIds() as $chatId) {
            try {
                $result = $this->channels->send('telegram', $chatId, $body);
                $ok = (bool) ($result['ok'] ?? false);
                MessageLog::query()->create([
                    'channel' => 'telegram',
                    'direction' => 'outbound',
                    'messaging_identity_id' => null,
                    'pppoe_customer_id' => $customerId,
                    'external_id' => $chatId,
                    'command' => $command,
                    'status' => $ok ? 'sent' : 'failed',
                    'body' => Str::limit($body, 900, ''),
                    'error_message' => $ok ? null : ($result['message'] ?? 'Gagal mengirim'),
                ]);
                if ($ok) {
                    $sent++;
                }
            } catch (Throwable $e) {
                MessageLog::query()->create([
                    'channel' => 'telegram',
                    'direction' => 'outbound',
                    'pppoe_customer_id' => $customerId,
                    'external_id' => $chatId,
                    'command' => $command,
                    'status' => 'failed',
                    'body' => Str::limit($body, 900, ''),
                    'error_message' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    private function parseTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function statusLabel(mixed $status): string
    {
        return match ($status) {
            'active' => '🟢 Aktif',
            'isolated' => '🔴 Isolir',
            'disabled' => '⚫ Nonaktif',
            null, '' => '—',
            default => (string) $status,
        };
    }

    private function dash(mixed $value): string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : '—';
    }

    private function stamp(): string
    {
        return now()->timezone((string) config('app.timezone'))->format('d/m/Y H:i');
    }
}
