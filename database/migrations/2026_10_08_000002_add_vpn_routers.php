<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vpn_routers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pppoe_customer_id')->constrained('pppoe_customers')->cascadeOnDelete();
            $table->string('name', 32);
            $table->string('vpn_remote_address', 45)->nullable()->unique();
            $table->unsignedSmallInteger('vpn_port_series')->nullable()->unique();
            $table->unsignedTinyInteger('billing_day');
            $table->date('service_until')->nullable();
            $table->timestamps();

            $table->unique('name');
            $table->unique(['pppoe_customer_id', 'name']);
        });

        Schema::table('vpn_port_forwards', function (Blueprint $table) {
            $table->foreignId('vpn_router_id')->nullable()->after('pppoe_customer_id')->constrained('vpn_routers')->cascadeOnDelete();
        });

        Schema::table('vpn_port_forwards', function (Blueprint $table) {
            $table->dropUnique(['pppoe_customer_id', 'dst_port']);
            $table->unique(['vpn_router_id', 'dst_port']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('vpn_router_id')->nullable()->after('pppoe_customer_id')->constrained('vpn_routers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vpn_router_id');
        });

        Schema::table('vpn_port_forwards', function (Blueprint $table) {
            $table->dropUnique(['vpn_router_id', 'dst_port']);
            $table->unique(['pppoe_customer_id', 'dst_port']);
            $table->dropConstrainedForeignId('vpn_router_id');
        });

        Schema::dropIfExists('vpn_routers');
    }
};
