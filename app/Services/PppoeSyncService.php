<?php

namespace App\Services;

use App\Models\PppoeCustomer;
use App\Services\Messaging\CustomerNotifier;
use App\Services\Vpn\VpnProvisioner;

class PppoeSyncService
{
    public function __construct(
        private readonly MikrotikApiService $api,
        private readonly CustomerNotifier $notifier,
        private readonly VpnProvisioner $vpn,
    ) {
    }

    /**
     * Sinkronkan status/profile pelanggan ke RouterOS.
     *
     * Status billing di app (aktif / isolir / nonaktif) tetap dihitung meski
     * RouterOS sedang dimatikan atau API gagal — supaya pembukuan dan daftar
     * lewat tempo tidak "menghilang" saat perangkat offline. Push ke MikroTik
     * dan notifikasi WhatsApp hanya setelah perangkat berhasil di-update.
     *
     * @param  bool  $pushPassword  true hanya saat create/update password sengaja diganti.
     *                              Isolir, lunas, grace, dan sync rutin tidak boleh
     *                              menimpa password secret yang sudah ada di MikroTik.
     * @param  bool  $forceDisconnect  putus sesi aktif supaya CPE reconnect ke profile baru
     *                                 (ganti paket / aktivasi kembali).
     */
    public function sync(PppoeCustomer $customer, bool $pushPassword = false, bool $forceDisconnect = false): void
    {
        $customer->loadMissing(['router', 'package']);

        $router = $customer->router;

        if (! $router) {
            $customer->update([
                'sync_status' => 'error',
                'sync_message' => 'Router tidak ditemukan.',
            ]);

            return;
        }

        $wasIsolated = $customer->status === 'isolated';
        $plan = $this->planLocalStatus($customer);

        if ($plan['error'] !== null) {
            $customer->update([
                'sync_status' => 'error',
                'sync_message' => $plan['error'],
                'status' => $plan['status'],
            ]);

            return;
        }

        if (! $router->is_active) {
            // Billing status tetap ditulis; secret di MikroTik menunggu router aktif lagi.
            $customer->update([
                'status' => $plan['status'],
                'sync_status' => 'error',
                'sync_message' => $router->connectionBlockedMessage(),
            ]);

            return;
        }

        $status = $plan['status'];
        $targetProfile = $plan['target_profile'] ?? $customer->service_profile;
        $disableSecret = $plan['disable_secret'];

        // Putus sesi saat isolir, pulih dari isolir, atau profile layanan baru
        // supaya CPE reconnect dan langsung memakai profile yang benar.
        $disconnectActive = $forceDisconnect
            || $status === 'isolated'
            || ($wasIsolated && $status === 'active');

        $password = (string) ($customer->password ?? '');

        $result = $this->api->upsertPppSecret(
            $router,
            $customer->username,
            $password,
            $targetProfile,
            $customer->name,
            $disableSecret,
            disconnectActive: $disconnectActive,
            updatePassword: $pushPassword && $password !== '',
            service: $customer->pppService(),
        );

        // Isolir/nonaktif: tulis status lokal meski API gagal (nanti di-push ulang).
        // Pulih ke aktif: tunggu API sukses agar tidak menganggap online padahal masih isolir di MikroTik.
        $applyLocalStatus = $result['ok']
            || in_array($status, ['isolated', 'disabled'], true);

        $customer->update([
            'status' => $applyLocalStatus ? $status : $customer->status,
            'sync_status' => $result['ok'] ? 'synced' : 'error',
            'sync_message' => $result['message'],
            'last_synced_at' => now(),
        ]);

        if ($result['ok'] && $status === 'isolated' && ! $wasIsolated) {
            $this->notifier->notifyIsolir($customer->fresh() ?? $customer);
        } elseif ($result['ok'] && $wasIsolated && $status === 'active') {
            $this->notifier->notifyRestore($customer->fresh() ?? $customer);
        }

        if ($result['ok'] && $customer->pppService() === PppoeCustomer::SERVICE_L2TP) {
            $this->vpn->syncSecretState($customer->fresh() ?? $customer);
        }
    }

    /**
     * Hitung status/profile yang seharusnya dipakai di app (dan di MikroTik bila online).
     *
     * @return array{
     *     status: string,
     *     target_profile: ?string,
     *     disable_secret: bool,
     *     error: ?string
     * }
     */
    private function planLocalStatus(PppoeCustomer $customer): array
    {
        $stopService = ! $customer->is_active;
        $disableSecret = $stopService;
        $status = 'active';
        $targetProfile = $customer->service_profile;
        $error = null;

        if ($stopService) {
            $status = 'disabled';
        } elseif ($customer->shouldIsolir()) {
            // VPN L2TP tidak punya halaman isolir. Secret dimatikan supaya
            // login ditolak, lalu dihidupkan lagi saat tagihan lunas.
            if ($customer->pppService() === PppoeCustomer::SERVICE_L2TP) {
                $disableSecret = true;
                $status = 'isolated';
            } elseif (! $customer->isolir_profile) {
                $status = 'isolated';
                $targetProfile = null;
                $disableSecret = false;
                $error = 'Aksi isolir dipilih, tapi profile isolir belum diisi.';
            } else {
                $targetProfile = $customer->isolir_profile;
                $status = 'isolated';
            }
        }

        return [
            'status' => $status,
            'target_profile' => $targetProfile,
            'disable_secret' => $disableSecret,
            'error' => $error,
        ];
    }
}
