<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('pppoe_customer_id')->constrained('pppoe_customers')->cascadeOnDelete();
            $table->foreignId('subscription_package_id')->nullable()->constrained('subscription_packages')->nullOnDelete();
            $table->string('type', 20)->default('monthly'); // prorata|monthly|adjustment
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date');
            $table->unsignedInteger('amount');
            $table->unsignedInteger('discount')->default(0);
            $table->unsignedInteger('total');
            $table->string('status', 20)->default('unpaid'); // unpaid|paid|void
            $table->timestamp('paid_at')->nullable();
            $table->string('package_name')->nullable();
            $table->unsignedInteger('package_price')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_date']);
            $table->index(['pppoe_customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
