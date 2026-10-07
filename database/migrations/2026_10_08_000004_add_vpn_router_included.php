<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vpn_routers', function (Blueprint $table) {
            $table->boolean('included')->default(false)->after('billing_day');
        });

        Schema::table('vpn_router_credits', function (Blueprint $table) {
            $table->boolean('included')->default(false)->after('billing_day');
        });
    }

    public function down(): void
    {
        Schema::table('vpn_routers', function (Blueprint $table) {
            $table->dropColumn('included');
        });

        Schema::table('vpn_router_credits', function (Blueprint $table) {
            $table->dropColumn('included');
        });
    }
};
