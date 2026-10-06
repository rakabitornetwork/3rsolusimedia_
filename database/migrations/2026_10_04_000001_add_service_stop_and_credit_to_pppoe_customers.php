<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pppoe_customers', function (Blueprint $table) {
            $table->date('stopped_at')->nullable()->after('due_date');
            $table->date('reactivated_at')->nullable()->after('stopped_at');
            $table->unsignedInteger('billing_credit')->default(0)->after('reactivated_at');
        });
    }

    public function down(): void
    {
        Schema::table('pppoe_customers', function (Blueprint $table) {
            $table->dropColumn(['stopped_at', 'reactivated_at', 'billing_credit']);
        });
    }
};
