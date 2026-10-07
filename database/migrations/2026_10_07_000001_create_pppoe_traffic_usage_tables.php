<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pppoe_traffic_cursors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pppoe_customer_id')->unique()->constrained('pppoe_customers')->cascadeOnDelete();
            $table->unsignedBigInteger('last_rx_byte')->default(0);
            $table->unsignedBigInteger('last_tx_byte')->default(0);
            $table->timestamp('sampled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pppoe_monthly_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pppoe_customer_id')->constrained('pppoe_customers')->cascadeOnDelete();
            $table->char('period', 7);
            $table->unsignedBigInteger('rx_bytes')->default(0);
            $table->unsignedBigInteger('tx_bytes')->default(0);
            $table->timestamps();

            $table->unique(['pppoe_customer_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pppoe_monthly_usages');
        Schema::dropIfExists('pppoe_traffic_cursors');
    }
};
