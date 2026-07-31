<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pppoe_customers', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('service_profile');
            $table->unsignedTinyInteger('billing_day')->nullable()->after('start_date');
            $table->unsignedInteger('first_bill_amount')->nullable()->after('due_date');
            $table->unsignedSmallInteger('first_bill_days')->nullable()->after('first_bill_amount');
        });

        foreach (DB::table('pppoe_customers')->orderBy('id')->get() as $customer) {
            $dueDay = (int) date('j', strtotime((string) $customer->due_date));
            DB::table('pppoe_customers')->where('id', $customer->id)->update([
                'start_date' => $customer->created_at
                    ? date('Y-m-d', strtotime((string) $customer->created_at))
                    : $customer->due_date,
                'billing_day' => max(1, min(28, $dueDay)),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('pppoe_customers', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'billing_day', 'first_bill_amount', 'first_bill_days']);
        });
    }
};
