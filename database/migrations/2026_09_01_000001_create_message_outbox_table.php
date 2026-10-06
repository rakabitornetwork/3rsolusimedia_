<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20)->default('whatsapp');
            $table->string('template', 40);
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('pppoe_customer_id')->nullable()->constrained('pppoe_customers')->cascadeOnDelete();
            $table->foreignId('messaging_identity_id')->nullable()->constrained('messaging_identities')->nullOnDelete();
            $table->string('external_id', 80);
            $table->text('body');
            $table->timestamp('available_at');
            $table->timestamp('sent_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('error_message', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'available_at']);
            $table->index(['channel', 'status']);
            $table->index(['invoice_id', 'template', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_outbox');
    }
};
