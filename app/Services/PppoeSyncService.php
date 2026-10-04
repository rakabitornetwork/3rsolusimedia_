<?php

namespace App\Services;

use App\Models\PppoeCustomer;
use App\Services\Messaging\CustomerNotifier;

class PppoeSyncService
{
    public function __construct(
        private readonly MikrotikApiService $api,
        private readonly CustomerNotifier $notifier,
    ) {
    }

    /**
     * Sinkronkan status/profile pelanggan ke RouterOS.
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
        $targetProfile = $customer->service_profile;
        $disabled = ! $customer->is_active;
        $status = 'active';

        if ($disabled) {
            $status = 'disabled';
        } elseif ($customer->shouldIsolir()) {
            if (! $customer->isolir_profile) {
                $customer->update([
                    'sync_status' => 'error',
                    'sync_message' => 'Aksi isolir dipilih, tapi profile isolir belum diisi.',
                    'status' => 'isolated',
                ]);

                return;
            }

            $targetProfile = $customer->isolir_profile;
            $status = 'isolated';
        }

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
            $disabled,
            disconnectActive: $disconnectActive,
            updatePassword: $pushPassword && $password !== '',
        );

        $customer->update([
            'status' => $result['ok'] ? $status : $customer->status,
            'sync_status' => $result['ok'] ? 'synced' : 'error',
            'sync_message' => $result['message'],
            'last_synced_at' => now(),
        ]);

        if ($result['ok'] && $status === 'isolated' && ! $wasIsolated) {
            $this->notifier->notifyIsolir($customer->fresh() ?? $customer);
        } elseif ($result['ok'] && $wasIsolated && $status === 'active') {
            $this->notifier->notifyRestore($customer->fresh() ?? $customer);
        }
    }
}
