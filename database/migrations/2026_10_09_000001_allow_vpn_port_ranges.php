<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vpn_port_forwards', function (Blueprint $table) {
            $table->unsignedInteger('public_port_end')->nullable()->after('public_port');
            $table->unsignedInteger('dst_port_end')->nullable()->after('dst_port');
            $table->dropUnique(['vpn_router_id', 'dst_port']);
        });
    }

    public function down(): void
    {
        Schema::table('vpn_port_forwards', function (Blueprint $table) {
            $table->unique(['vpn_router_id', 'dst_port']);
            $table->dropColumn(['public_port_end', 'dst_port_end']);
        });
    }
};
