<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_packages', function (Blueprint $table) {
            $table->foreignId('mikrotik_router_id')
                ->nullable()
                ->after('id')
                ->constrained('mikrotik_routers')
                ->nullOnDelete();
        });

        $packageRouters = DB::table('pppoe_customers')
            ->select('subscription_package_id', 'mikrotik_router_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('subscription_package_id')
            ->groupBy('subscription_package_id', 'mikrotik_router_id')
            ->orderByDesc('total')
            ->get()
            ->groupBy('subscription_package_id');

        foreach ($packageRouters as $packageId => $rows) {
            $routerId = $rows->first()?->mikrotik_router_id;
            if ($routerId) {
                DB::table('subscription_packages')
                    ->where('id', $packageId)
                    ->update(['mikrotik_router_id' => $routerId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('subscription_packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mikrotik_router_id');
        });
    }
};
