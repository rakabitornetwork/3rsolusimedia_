<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pppoe_customers', function (Blueprint $table) {
            $table->date('grace_until')->nullable()->after('due_date');
            $table->string('grace_note', 255)->nullable()->after('grace_until');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedTinyInteger('billing_months')->default(1)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('pppoe_customers', function (Blueprint $table) {
            $table->dropColumn(['grace_until', 'grace_note']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('billing_months');
        });
    }
};
