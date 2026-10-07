<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pppoe_customers', function (Blueprint $table) {
            $table->string('ppp_service', 20)->default('pppoe')->after('username');
            $table->index('ppp_service');
        });
    }

    public function down(): void
    {
        Schema::table('pppoe_customers', function (Blueprint $table) {
            $table->dropIndex(['ppp_service']);
            $table->dropColumn('ppp_service');
        });
    }
};
