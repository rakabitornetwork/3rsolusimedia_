<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pppoe_customer_id')->constrained('pppoe_customers')->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('external_id', 80);
            $table->string('display_name')->nullable();
            $table->string('username')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['channel', 'external_id']);
            $table->unique(['channel', 'pppoe_customer_id']);
            $table->index(['pppoe_customer_id', 'channel']);
        });

        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20);
            $table->string('direction', 10);
            $table->foreignId('messaging_identity_id')->nullable()->constrained('messaging_identities')->nullOnDelete();
            $table->foreignId('pppoe_customer_id')->nullable()->constrained('pppoe_customers')->nullOnDelete();
            $table->string('external_id', 80);
            $table->string('command', 40)->nullable();
            $table->string('status', 20)->default('received');
            $table->string('body', 500)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['channel', 'created_at']);
            $table->index(['external_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_logs');
        Schema::dropIfExists('messaging_identities');
    }
};
