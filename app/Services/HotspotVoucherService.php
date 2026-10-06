<?php

namespace App\Services;

use App\Models\HotspotVoucher;
use App\Models\MikrotikRouter;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class HotspotVoucherService
{
    public const MAX_GENERATE_QUANTITY = 500;

    public function __construct(private readonly MikrotikApiService $api) {}

    /**
     * Login URL Mikhmon-style: http://dns/login?username=&password=
     */
    public static function buildHotspotLoginUrl(?string $host, string $username, string $password): ?string
    {
        $host = trim((string) $host);
        if ($host === '') {
            return null;
        }

        $base = preg_match('#^https?://#i', $host) === 1 ? $host : 'http://'.$host;
        $base = rtrim($base, '/');
        if (preg_match('#/login$#i', $base) !== 1) {
            $base .= '/login';
        }

        return $base.'?'.http_build_query([
            'username' => $username,
            'password' => $password,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param  array{
     *     quantity: int,
     *     prefix?: ?string,
     *     code_length: int,
     *     code_format?: string,
     *     password_mode: string,
     *     profile: string,
     *     server?: ?string,
     *     limit_uptime?: ?string,
     *     limit_bytes_total?: ?int,
     *     comment?: ?string,
     *     agent_id?: ?int,
     *     agent_name?: ?string,
     *     base_price?: int,
     *     commission?: int,
     *     created_by?: ?int
     * }  $options
     * @return array{ok: bool, message: string, batch_id?: string, vouchers?: array<int, array<string, mixed>>}
     */
    public function generate(MikrotikRouter $router, array $options): array
    {
        $quantity = max(1, min(self::MAX_GENERATE_QUANTITY, (int) ($options['quantity'] ?? 1)));
        $prefix = (string) ($options['prefix'] ?? '');
        $length = max(4, min(12, (int) ($options['code_length'] ?? 6)));
        $format = $this->normalizeFormat((string) ($options['code_format'] ?? 'numbers'));
        $passwordMode = ($options['password_mode'] ?? 'same') === 'random' ? 'random' : 'same';

        $agent = null;
        $agentId = isset($options['agent_id']) ? (int) $options['agent_id'] : null;
        if ($agentId) {
            $agent = User::query()
                ->where('id', $agentId)
                ->where('role', User::ROLE_AGEN)
                ->first();
        }

        $agentName = trim((string) ($options['agent_name'] ?? ''));
        if ($agentName === '' && $agent) {
            $agentName = (string) $agent->name;
        }

        $basePrice = max(0, (int) ($options['base_price'] ?? 0));
        $commission = $agentName !== '' ? max(0, (int) ($options['commission'] ?? 0)) : 0;
        $sellPrice = $basePrice + $commission;

        $batchId = (string) Str::uuid();
        // Mikhmon-style vc- prefix so profile on-login expire gate matches.
        $comment = trim((string) ($options['comment'] ?? ''));
        if ($comment === '') {
            $comment = 'vc-'.now()->format('Ymd');
        }
        if ($agentName !== '') {
            $comment = trim($comment.' | agen:'.$agentName);
        }

        $pending = [];
        $usedNames = [];

        for ($i = 0; $i < $quantity; $i++) {
            $name = '';
            $password = '';

            // Hindari bentrok nama dalam batch yang sama.
            for ($attempt = 0; $attempt < 8; $attempt++) {
                $code = $this->api->generateVoucherCode($length, $format);
                $candidate = $prefix !== '' ? $prefix.$code : $code;
                if (isset($usedNames[$candidate])) {
                    continue;
                }

                $name = $candidate;
                $password = $passwordMode === 'random'
                    ? $this->api->generateVoucherCode($length, $format)
                    : $name;
                $usedNames[$name] = true;
                break;
            }

            if ($name === '') {
                continue;
            }

            $pending[] = [
                'name' => $name,
                'password' => $password,
                'profile' => $options['profile'] ?? null,
                'server' => $options['server'] ?? null,
                'limit_uptime' => $options['limit_uptime'] ?? null,
                'limit_bytes_total' => $options['limit_bytes_total'] ?? null,
                'comment' => $comment !== '' ? $comment : null,
            ];
        }

        $apiResult = $this->api->createHotspotUsers($router, $pending);
        $createdRemote = collect($apiResult['created'] ?? [])->keyBy('name');
        $errors = $apiResult['errors'] ?? [];

        if ($createdRemote->isEmpty()) {
            return [
                'ok' => false,
                'message' => $errors[0] ?? $apiResult['message'] ?? 'Gagal membuat voucher hotspot.',
                'vouchers' => [],
            ];
        }

        $now = now();
        $rows = [];
        foreach ($pending as $item) {
            if (! $createdRemote->has($item['name'])) {
                continue;
            }

            $rows[] = [
                'batch_id' => $batchId,
                'mikrotik_router_id' => $router->id,
                'agent_id' => $agent?->id,
                'created_by' => $options['created_by'] ?? null,
                'username' => $item['name'],
                'password' => $item['password'],
                'profile' => $options['profile'] ?? null,
                'server' => $options['server'] ?? null,
                'limit_uptime' => $options['limit_uptime'] ?? null,
                'limit_bytes_total' => $options['limit_bytes_total'] ?? null,
                'comment' => $comment !== '' ? $comment : null,
                'code_format' => $format,
                'agent_name' => $agentName !== '' ? $agentName : null,
                'base_price' => $basePrice,
                'commission' => $commission,
                'sell_price' => $sellPrice,
                'status' => HotspotVoucher::STATUS_AVAILABLE,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            HotspotVoucher::query()->insert($rows);
        }

        $created = HotspotVoucher::query()
            ->where('batch_id', $batchId)
            ->orderBy('id')
            ->get()
            ->map(fn (HotspotVoucher $voucher) => $voucher->toCardArray())
            ->all();

        if ($created === []) {
            return [
                'ok' => false,
                'message' => $errors[0] ?? 'Gagal menyimpan voucher hotspot.',
                'vouchers' => [],
            ];
        }

        $message = count($created).' voucher berhasil dibuat.';
        if ($errors !== []) {
            $message .= ' '.count($errors).' gagal.';
        }

        return [
            'ok' => true,
            'message' => $message,
            'batch_id' => $batchId,
            'vouchers' => $created,
        ];
    }

    /**
     * Tambah satu user hotspot bernama (bukan generate batch).
     *
     * @param  array{
     *     name: string,
     *     password: string,
     *     profile: string,
     *     server?: ?string,
     *     limit_uptime?: ?string,
     *     limit_bytes_total?: ?int,
     *     comment?: ?string,
     *     created_by?: ?int
     * }  $options
     * @return array{ok: bool, message: string, vouchers?: array<int, array<string, mixed>>}
     */
    public function addUser(MikrotikRouter $router, array $options): array
    {
        $name = trim((string) ($options['name'] ?? ''));
        $password = (string) ($options['password'] ?? '');
        if ($name === '') {
            return ['ok' => false, 'message' => 'Username hotspot wajib diisi.', 'vouchers' => []];
        }
        if ($password === '') {
            $password = $name;
        }

        $comment = MikrotikApiService::prefixHotspotUserComment(
            $name,
            $password,
            (string) ($options['comment'] ?? '')
        );

        $pending = [[
            'name' => $name,
            'password' => $password,
            'profile' => $options['profile'] ?? null,
            'server' => $options['server'] ?? null,
            'limit_uptime' => $options['limit_uptime'] ?? null,
            'limit_bytes_total' => $options['limit_bytes_total'] ?? null,
            'comment' => $comment !== '' ? $comment : null,
        ]];

        $apiResult = $this->api->createHotspotUsers($router, $pending);
        if (! ($apiResult['ok'] ?? false) || ($apiResult['created'] ?? []) === []) {
            return [
                'ok' => false,
                'message' => $apiResult['errors'][0] ?? $apiResult['message'] ?? 'Gagal menambah user hotspot.',
                'vouchers' => [],
            ];
        }

        $batchId = (string) Str::uuid();
        $now = now();
        HotspotVoucher::query()->updateOrCreate(
            [
                'mikrotik_router_id' => $router->id,
                'username' => $name,
            ],
            [
                'batch_id' => $batchId,
                'agent_id' => null,
                'created_by' => $options['created_by'] ?? null,
                'password' => $password,
                'profile' => $options['profile'] ?? null,
                'server' => $options['server'] ?? null,
                'limit_uptime' => $options['limit_uptime'] ?? null,
                'limit_bytes_total' => $options['limit_bytes_total'] ?? null,
                'comment' => $comment !== '' ? $comment : null,
                'code_format' => 'numbers',
                'agent_name' => null,
                'base_price' => 0,
                'commission' => 0,
                'sell_price' => 0,
                'status' => HotspotVoucher::STATUS_AVAILABLE,
                'used_at' => null,
                'deleted_from_router_at' => null,
                'updated_at' => $now,
            ]
        );

        $created = HotspotVoucher::query()
            ->where('batch_id', $batchId)
            ->get()
            ->map(fn (HotspotVoucher $voucher) => $voucher->toCardArray())
            ->all();

        return [
            'ok' => true,
            'message' => 'User hotspot "'.$name.'" ditambahkan.',
            'batch_id' => $batchId,
            'vouchers' => $created,
        ];
    }

    public function markResetLocally(MikrotikRouter $router, string $username): void
    {
        try {
            HotspotVoucher::query()
                ->where('mikrotik_router_id', $router->id)
                ->where('username', $username)
                ->update([
                    'status' => HotspotVoucher::STATUS_AVAILABLE,
                    'used_at' => null,
                    'deleted_from_router_at' => null,
                    'comment' => null,
                ]);
        } catch (Throwable) {
        }
    }

    /**
     * Hapus voucher yang sudah terpakai (kuota waktu/data habis atau pernah login lalu offline)
     * dari RouterOS dan tandai di aplikasi.
     *
     * @return array{ok: bool, message: string, removed: int, sold: int}
     */
    public function purgeUsed(MikrotikRouter $router): array
    {
        $list = $this->api->listHotspotUsers($router);
        if (! ($list['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => $list['message'] ?? 'Gagal membaca user hotspot.',
                'removed' => 0,
                'sold' => 0,
            ];
        }

        /** @var Collection<int, array<string, mixed>> $users */
        $users = collect($list['users'] ?? []);

        // Mikhmon Record-like: catat penjualan saat first use, tanpa hapus dari router.
        $sold = $this->syncSoldFromUsage($router, $users);

        $toRemove = [];
        $usernamesById = [];

        foreach ($users as $user) {
            if (! $this->shouldPurgeUser($user) && ! $this->shouldRemoveExpiredComment($user)) {
                continue;
            }

            $userId = (string) ($user['id'] ?? '');
            $username = (string) ($user['name'] ?? '');

            if ($userId === '' || $username === '') {
                continue;
            }

            $toRemove[] = $userId;
            $usernamesById[$userId] = $username;
        }

        $removed = 0;
        $result = ['ok' => true, 'message' => null];

        if ($toRemove !== []) {
            $result = $this->api->removeHotspotUsers($router, $toRemove);
            $removedIds = $result['removed_ids'] ?? [];
            $removed = count($removedIds);

            if ($removed > 0) {
                $removedUsernames = array_values(array_filter(array_map(
                    static fn (string $id) => $usernamesById[$id] ?? null,
                    $removedIds
                )));

                if ($removedUsernames !== []) {
                    HotspotVoucher::query()
                        ->where('mikrotik_router_id', $router->id)
                        ->whereIn('username', $removedUsernames)
                        ->whereIn('status', [HotspotVoucher::STATUS_AVAILABLE, HotspotVoucher::STATUS_USED])
                        ->update([
                            'status' => HotspotVoucher::STATUS_USED,
                            'deleted_from_router_at' => now(),
                        ]);

                    // Preserve first-use used_at from syncSoldFromUsage.
                    HotspotVoucher::query()
                        ->where('mikrotik_router_id', $router->id)
                        ->whereIn('username', $removedUsernames)
                        ->whereNull('used_at')
                        ->update(['used_at' => now()]);
                }
            }
        }

        // Users already removed by RouterOS expire monitor (Mikhmon scheduler).
        $presentNames = $users
            ->map(fn (array $user) => (string) ($user['name'] ?? ''))
            ->filter(fn (string $name) => $name !== '')
            ->values()
            ->all();
        $syncedMissing = $this->syncMissingFromRouter($router, $presentNames);

        $parts = [];
        if ($sold > 0) {
            $parts[] = "{$sold} ditandai terjual";
        }
        if ($removed > 0) {
            $parts[] = "{$removed} dihapus dari RouterOS";
        }
        if ($syncedMissing > 0) {
            $parts[] = "{$syncedMissing} disinkron (sudah hilang di RouterOS)";
        }
        $message = $parts !== []
            ? implode('; ', $parts).'.'
            : ($result['message'] ?? 'Tidak ada voucher terpakai yang perlu dihapus.');

        return [
            'ok' => (bool) ($result['ok'] ?? false) || $removed > 0 || $sold > 0 || $syncedMissing > 0 || $toRemove === [],
            'message' => $message,
            'removed' => $removed + $syncedMissing,
            'sold' => $sold,
        ];
    }

    /**
     * Tandai voucher sebagai terjual (status used) saat RouterOS menunjukkan usage.
     * Tidak menghapus user dari RouterOS — mirip Mikhmon Record.
     *
     * @param  Collection<int, array<string, mixed>>|array<int, array<string, mixed>>  $users
     */
    public function syncSoldFromUsage(MikrotikRouter $router, Collection|array $users): int
    {
        $usernames = collect($users)
            ->filter(fn (array $user) => $this->hasUsage($user))
            ->map(fn (array $user) => (string) ($user['name'] ?? ''))
            ->filter(fn (string $name) => $name !== '')
            ->unique()
            ->values()
            ->all();

        if ($usernames === []) {
            return 0;
        }

        return HotspotVoucher::query()
            ->where('mikrotik_router_id', $router->id)
            ->whereIn('username', $usernames)
            ->where('status', HotspotVoucher::STATUS_AVAILABLE)
            ->update([
                'status' => HotspotVoucher::STATUS_USED,
                'used_at' => now(),
            ]);
    }

    /**
     * Hapus semua voucher hotspot yang komentarnya cocok (RouterOS + aplikasi).
     *
     * @return array{ok: bool, message: string, removed: int}
     */
    public function deleteByComment(MikrotikRouter $router, string $comment): array
    {
        $comment = trim($comment);
        if ($comment === '') {
            return [
                'ok' => false,
                'message' => 'Pilih komentar terlebih dahulu.',
                'removed' => 0,
            ];
        }

        $list = $this->api->listHotspotUsers($router);
        if (! ($list['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => $list['message'] ?? 'Gagal membaca user hotspot.',
                'removed' => 0,
            ];
        }

        $toRemove = [];
        $usernames = [];

        foreach (collect($list['users'] ?? []) as $user) {
            if (trim((string) ($user['comment'] ?? '')) !== $comment) {
                continue;
            }

            $userId = (string) ($user['id'] ?? '');
            $username = (string) ($user['name'] ?? '');

            if ($userId === '' || $username === '') {
                continue;
            }

            $toRemove[] = $userId;
            $usernames[] = $username;
        }

        $removed = 0;
        if ($toRemove !== []) {
            $result = $this->api->removeHotspotUsers($router, $toRemove);
            $removed = (int) ($result['removed'] ?? 0);

            if (! ($result['ok'] ?? false) && $removed === 0) {
                return [
                    'ok' => false,
                    'message' => $result['message'] ?? 'Gagal menghapus voucher.',
                    'removed' => 0,
                ];
            }
        }

        HotspotVoucher::query()
            ->where('mikrotik_router_id', $router->id)
            ->where('status', HotspotVoucher::STATUS_AVAILABLE)
            ->where(function ($query) use ($comment, $usernames) {
                $query->where('comment', $comment);
                if ($usernames !== []) {
                    $query->orWhereIn('username', $usernames);
                }
            })
            ->update([
                'status' => HotspotVoucher::STATUS_DELETED,
                'deleted_from_router_at' => now(),
            ]);

        $message = $removed > 0
            ? "{$removed} voucher dengan komentar \"{$comment}\" berhasil dihapus."
            : "Tidak ada voucher RouterOS dengan komentar \"{$comment}\".";

        return [
            'ok' => true,
            'message' => $message,
            'removed' => $removed,
        ];
    }

    /**
     * @param  array<string, mixed>  $user
     */
    public function shouldPurgeUser(array $user): bool
    {
        if (! empty($user['is_online'])) {
            return false;
        }

        if (! $this->hasUsage($user)) {
            return false;
        }

        $limitUptime = trim((string) ($user['limit_uptime'] ?? ''));
        if ($limitUptime !== '') {
            $usedSeconds = $this->parseMikrotikDuration((string) ($user['uptime'] ?? '0s'));
            $limitSeconds = $this->parseMikrotikDuration($limitUptime);

            // Kuota waktu habis (toleransi 1 detik).
            if ($limitSeconds > 0 && $usedSeconds >= max(1, $limitSeconds - 1)) {
                return true;
            }
        }

        $limitBytes = isset($user['limit_bytes_total']) ? (int) $user['limit_bytes_total'] : 0;
        if ($limitBytes > 0) {
            $usedBytes = (int) ($user['bytes_in'] ?? 0) + (int) ($user['bytes_out'] ?? 0);
            if ($usedBytes >= $limitBytes) {
                return true;
            }
        }

        // Tanpa limit: anggap terpakai jika sudah pernah login lalu offline.
        if ($limitUptime === '' && $limitBytes <= 0) {
            return true;
        }

        return false;
    }

    /**
     * True when hotspot user comment holds an expire datetime that has passed
     * (written by profile on-login, monitored by Mikhmon-style scheduler).
     *
     * @param  array<string, mixed>  $user
     */
    public function isCommentExpired(array $user): bool
    {
        $expireAt = $this->parseExpireComment((string) ($user['comment'] ?? ''));

        return $expireAt !== null && $expireAt->lessThanOrEqualTo(now());
    }

    /**
     * Remove from RouterOS when comment expire passed and mode is Remove (X),
     * not Notice (N) — Notice leaves the user (limit-uptime=1s via monitor).
     *
     * @param  array<string, mixed>  $user
     */
    public function shouldRemoveExpiredComment(array $user): bool
    {
        if (! $this->isCommentExpired($user)) {
            return false;
        }

        $comment = trim((string) ($user['comment'] ?? ''));
        if (preg_match('/\s+N$/i', $comment)) {
            return false;
        }

        return true;
    }

    public function parseExpireComment(string $comment): ?Carbon
    {
        $comment = trim($comment);
        if ($comment === '') {
            return null;
        }

        // RouterOS 7.10+: 2024-01-15 14:30:25 X|N
        if (preg_match(
            '/^(\d{4})-(\d{2})-(\d{2})\s+(\d{1,2}):(\d{2}):(\d{2})(?:\s+[xn])?/i',
            $comment,
            $m
        )) {
            try {
                return Carbon::create(
                    (int) $m[1],
                    (int) $m[2],
                    (int) $m[3],
                    (int) $m[4],
                    (int) $m[5],
                    (int) $m[6],
                    config('app.timezone')
                );
            } catch (Throwable) {
                return null;
            }
        }

        // Classic: jan/15/2024 14:30:25 X|N
        if (! preg_match(
            '/^(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)\/(\d{1,2})\/(\d{4})\s+(\d{1,2}):(\d{2}):(\d{2})(?:\s+[xn])?/i',
            $comment,
            $m
        )) {
            return null;
        }

        $months = [
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
            'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
        ];
        $month = $months[strtolower($m[1])] ?? 0;
        if ($month < 1) {
            return null;
        }

        try {
            return Carbon::create(
                (int) $m[3],
                $month,
                (int) $m[2],
                (int) $m[4],
                (int) $m[5],
                (int) $m[6],
                config('app.timezone')
            );
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Mark local vouchers that vanished from RouterOS (e.g. expire monitor removed them).
     *
     * @param  array<int, string>  $presentUsernames
     */
    public function syncMissingFromRouter(MikrotikRouter $router, array $presentUsernames): int
    {
        $query = HotspotVoucher::query()
            ->where('mikrotik_router_id', $router->id)
            ->whereNull('deleted_from_router_at')
            ->whereIn('status', [HotspotVoucher::STATUS_AVAILABLE, HotspotVoucher::STATUS_USED]);

        if ($presentUsernames !== []) {
            $query->whereNotIn('username', $presentUsernames);
        }

        $missing = $query->get(['id', 'used_at']);
        if ($missing->isEmpty()) {
            return 0;
        }

        $ids = $missing->pluck('id')->all();
        $now = now();

        HotspotVoucher::query()
            ->whereIn('id', $ids)
            ->update([
                'status' => HotspotVoucher::STATUS_USED,
                'deleted_from_router_at' => $now,
            ]);

        HotspotVoucher::query()
            ->whereIn('id', $ids)
            ->whereNull('used_at')
            ->update(['used_at' => $now]);

        return count($ids);
    }

    /**
     * @param  array<string, mixed>  $user
     */
    private function hasUsage(array $user): bool
    {
        $uptime = strtolower(trim((string) ($user['uptime'] ?? '')));
        if ($uptime !== '' && $uptime !== '0s' && $uptime !== '0' && $this->parseMikrotikDuration($uptime) > 0) {
            return true;
        }

        return ((int) ($user['bytes_in'] ?? 0) + (int) ($user['bytes_out'] ?? 0)) > 0;
    }

    public function parseMikrotikDuration(string $value): int
    {
        $value = strtolower(trim($value));
        if ($value === '' || $value === '0' || $value === '0s') {
            return 0;
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        // WinBox / some API responses: HH:MM:SS or H:MM:SS
        if (preg_match('/^(\d+):([0-5]?\d):([0-5]?\d)$/', $value, $parts)) {
            return ((int) $parts[1] * 3600) + ((int) $parts[2] * 60) + (int) $parts[3];
        }

        // MM:SS
        if (preg_match('/^([0-5]?\d):([0-5]?\d)$/', $value, $parts)) {
            return ((int) $parts[1] * 60) + (int) $parts[2];
        }

        $seconds = 0;
        if (preg_match_all('/(\d+)([wdhms])/', $value, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $n = (int) $match[1];
                $seconds += match ($match[2]) {
                    'w' => $n * 7 * 24 * 3600,
                    'd' => $n * 24 * 3600,
                    'h' => $n * 3600,
                    'm' => $n * 60,
                    's' => $n,
                    default => 0,
                };
            }
        }

        return $seconds;
    }

    public function normalizeFormat(string $format): string
    {
        return match ($format) {
            'numeric' => 'numbers',
            'letters', 'letters_upper' => 'upper',
            'letters_lower' => 'lower',
            'alphanumeric', 'hex' => 'numbers_upper',
            'numbers',
            'upper',
            'lower',
            'numbers_upper',
            'numbers_lower',
            'alt_numbers_upper',
            'alt_numbers_lower' => $format,
            default => 'numbers',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, example: string}>
     */
    public static function codeFormatOptions(): array
    {
        return [
            ['value' => 'numbers', 'label' => '12345 (Hanya Angka)', 'example' => '12345'],
            ['value' => 'upper', 'label' => 'ABCDE (Huruf Kapital)', 'example' => 'ABCDE'],
            ['value' => 'lower', 'label' => 'abcde (Huruf Kecil)', 'example' => 'abcde'],
            ['value' => 'numbers_upper', 'label' => '123ABC (Angka & Kapital)', 'example' => '123ABC'],
            ['value' => 'numbers_lower', 'label' => '123abc (Angka & Huruf Kecil)', 'example' => '123abc'],
            ['value' => 'alt_numbers_upper', 'label' => '1A2B3C (Kombinasi Angka & Kapital Selang-seling)', 'example' => '1A2B3C'],
            ['value' => 'alt_numbers_lower', 'label' => '1a2b3c (Kombinasi Angka & Huruf Kecil Selang-seling)', 'example' => '1a2b3c'],
        ];
    }

    public function markDeletedLocally(MikrotikRouter $router, string $username): void
    {
        try {
            HotspotVoucher::query()
                ->where('mikrotik_router_id', $router->id)
                ->where('username', $username)
                ->where('status', HotspotVoucher::STATUS_AVAILABLE)
                ->update([
                    'status' => HotspotVoucher::STATUS_DELETED,
                    'deleted_from_router_at' => now(),
                ]);
        } catch (Throwable) {
            // ignore local sync errors
        }
    }
}
