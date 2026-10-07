<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pppoe_customers', function (Blueprint $table) {
            $table->string('vpn_remote_address', 45)->nullable()->unique()->after('ppp_service');
            $table->unsignedSmallInteger('vpn_port_series')->nullable()->unique()->after('vpn_remote_address');
        });

        Schema::create('vpn_port_forwards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pppoe_customer_id')->constrained('pppoe_customers')->cascadeOnDelete();
            $table->unsignedInteger('public_port')->unique();
            $table->unsignedInteger('dst_port');
            $table->string('kind', 20);
            $table->string('label', 80);
            $table->timestamp('pushed_at')->nullable();
            $table->timestamps();

            $table->unique(['pppoe_customer_id', 'dst_port']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vpn_port_forwards');

        Schema::table('pppoe_customers', function (Blueprint $table) {
            $table->dropUnique(['vpn_remote_address']);
            $table->dropUnique(['vpn_port_series']);
            $table->dropColumn(['vpn_remote_address', 'vpn_port_series']);
        });
    }
};
