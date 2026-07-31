<?php

namespace App\Services;

use App\Models\PppoeCustomer;
use App\Support\AppSettings;

class PppoeSyncService
{
    public function __construct(private readonly MikrotikApiService $api)
    {
    }

    public function sync(PppoeCustomer $customer): void
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
        } elseif (
            AppSettings::bool('app_auto_isolir', true)
            && $customer->isOverdue()
            && $customer->overdue_action === 'isolir'
        ) {
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

        // Putus sesi saat isolir ATAU saat restore dari isolir ke profile paket,
        // agar CPE reconnect dengan profile yang benar. Pelanggan yang sudah aktif
        // (mis. bayar sebelum jatuh tempo) tidak diputus.
        $disconnectActive = $status === 'isolated'
            || ($wasIsolated && $status === 'active');

        $result = $this->api->upsertPppSecret(
            $router,
            $customer->username,
            $customer->password,
            $targetProfile,
            $customer->name,
            $disabled,
            disconnectActive: $disconnectActive,
        );

        $customer->update([
            'status' => $result['ok'] ? $status : $customer->status,
            'sync_status' => $result['ok'] ? 'synced' : 'error',
            'sync_message' => $result['message'],
            'last_synced_at' => now(),
        ]);
    }
}
