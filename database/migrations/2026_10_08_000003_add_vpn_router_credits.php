<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vpn_router_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pppoe_customer_id')->constrained('pppoe_customers')->cascadeOnDelete();
            $table->unsignedTinyInteger('billing_day');
            $table->date('service_until')->nullable();
            $table->json('invoice_ids');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vpn_router_credits');
    }
};
