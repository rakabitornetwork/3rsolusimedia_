<?php

namespace Database\Seeders;

use App\Models\SubscriptionPackage;
use Illuminate\Database\Seeder;

class SubscriptionPackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Hemat',
                'price' => 120000,
                'mikrotik_profile' => 'hemat',
                'description' => 'Paket WiFi rumahan hemat',
                'sort_order' => 1,
            ],
            [
                'name' => 'Keluarga',
                'price' => 150000,
                'mikrotik_profile' => 'keluarga',
                'description' => 'Paket WiFi rumahan keluarga',
                'sort_order' => 2,
            ],
            [
                'name' => 'Plus',
                'price' => 250000,
                'mikrotik_profile' => 'plus',
                'description' => 'Paket WiFi rumahan lebih kencang',
                'sort_order' => 3,
            ],
        ];

        foreach ($packages as $package) {
            SubscriptionPackage::updateOrCreate(
                ['name' => $package['name']],
                [...$package, 'is_active' => true]
            );
        }
    }
}
