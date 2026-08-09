<?php

namespace App\Services;

use App\Models\HotspotVoucher;
use App\Models\MikrotikRouter;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class HotspotVoucherService
{
    public function __construct(private readonly MikrotikApiService $api)
    {
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
        $quantity = max(1, min(100, (int) ($options['quantity'] ?? 1)));
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
        $comment = trim((string) ($options['comment'] ?? 'voucher-app'));
        if ($agentName !== '') {
            $comment = trim($comment.' | agen:'.$agentName);
        }

        $created = [];
        $errors = [];

        for ($i = 0; $i < $quantity; $i++) {
            $code = $this->api->generateVoucherCode($length, $format);
            $name = $prefix !== '' ? $prefix.$code : $code;
            $password = $passwordMode === 'random'
                ? $this->api->generateVoucherCode($length, $format)
                : $name;

            $result = $this->api->createHotspotUser($router, [
                'name' => $name,
                'password' => $password,
                'profile' => $options['profile'] ?? null,
                'server' => $options['server'] ?? null,
                'limit_uptime' => $options['limit_uptime'] ?? null,
                'limit_bytes_total' => $options['limit_bytes_total'] ?? null,
                'comment' => $comment !== '' ? $comment : null,
            ]);

            if (! $result['ok']) {
                $errors[] = $name.': '.$result['message'];

                continue;
            }

            $voucher = HotspotVoucher::query()->create([
                'batch_id' => $batchId,
                'mikrotik_router_id' => $router->id,
                'agent_id' => $agent?->id,
                'created_by' => $options['created_by'] ?? null,
                'username' => $name,
                'password' => $password,
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
            ]);

            $created[] = $voucher->toCardArray();
        }

        if ($created === []) {
            return [
                'ok' => false,
                'message' => $errors[0] ?? 'Gagal membuat voucher hotspot.',
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
     * Hapus voucher yang sudah terpakai (kuota waktu/data habis atau pernah login lalu offline)
     * dari RouterOS dan tandai di aplikasi.
     *
     * @return array{ok: bool, message: string, removed: int}
     */
    public function purgeUsed(MikrotikRouter $router): array
    {
        $list = $this->api->listHotspotUsers($router);
        if (! ($list['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => $list['message'] ?? 'Gagal membaca user hotspot.',
                'removed' => 0,
            ];
        }

        /** @var Collection<int, array<string, mixed>> $users */
        $users = collect($list['users'] ?? []);
        $removed = 0;
        $errors = [];

        foreach ($users as $user) {
            if (! $this->shouldPurgeUser($user)) {
                continue;
            }

            $userId = (string) ($user['id'] ?? '');
            $username = (string) ($user['name'] ?? '');

            if ($userId === '' || $username === '') {
                continue;
            }

            $result = $this->api->removeHotspotUser($router, $userId);
            if (! ($result['ok'] ?? false)) {
                $errors[] = $username.': '.($result['message'] ?? 'gagal hapus');

                continue;
            }

            $removed++;

            HotspotVoucher::query()
                ->where('mikrotik_router_id', $router->id)
                ->where('username', $username)
                ->whereIn('status', [HotspotVoucher::STATUS_AVAILABLE, HotspotVoucher::STATUS_USED])
                ->update([
                    'status' => HotspotVoucher::STATUS_USED,
                    'used_at' => now(),
                    'deleted_from_router_at' => now(),
                ]);
        }

        $message = $removed > 0
            ? "{$removed} voucher terpakai dihapus dari RouterOS & aplikasi."
            : 'Tidak ada voucher terpakai yang perlu dihapus.';

        if ($errors !== []) {
            $message .= ' '.count($errors).' gagal dihapus.';
        }

        return [
            'ok' => true,
            'message' => $message,
            'removed' => $removed,
        ];
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

        $removed = 0;
        $errors = [];

        foreach (collect($list['users'] ?? []) as $user) {
            if (trim((string) ($user['comment'] ?? '')) !== $comment) {
                continue;
            }

            $userId = (string) ($user['id'] ?? '');
            $username = (string) ($user['name'] ?? '');

            if ($userId === '' || $username === '') {
                continue;
            }

            $result = $this->api->removeHotspotUser($router, $userId);
            if (! ($result['ok'] ?? false)) {
                $errors[] = $username.': '.($result['message'] ?? 'gagal hapus');

                continue;
            }

            $removed++;
            $this->markDeletedLocally($router, $username);
        }

        HotspotVoucher::query()
            ->where('mikrotik_router_id', $router->id)
            ->where('comment', $comment)
            ->where('status', HotspotVoucher::STATUS_AVAILABLE)
            ->update([
                'status' => HotspotVoucher::STATUS_DELETED,
                'deleted_from_router_at' => now(),
            ]);

        $message = $removed > 0
            ? "{$removed} voucher dengan komentar \"{$comment}\" berhasil dihapus."
            : "Tidak ada voucher RouterOS dengan komentar \"{$comment}\".";

        if ($errors !== []) {
            $message .= ' '.count($errors).' gagal dihapus.';
        }

        return [
            'ok' => $errors === [] || $removed > 0,
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
