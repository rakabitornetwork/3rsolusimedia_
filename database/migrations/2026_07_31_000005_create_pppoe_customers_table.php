<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pppoe_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mikrotik_router_id')->constrained('mikrotik_routers')->cascadeOnDelete();
            $table->foreignId('subscription_package_id')->nullable()->constrained('subscription_packages')->nullOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('username');
            $table->text('password');
            $table->string('service_profile')->nullable();
            $table->date('due_date');
            $table->string('overdue_action')->default('isolir'); // bypass|isolir
            $table->string('isolir_profile')->nullable();
            $table->string('status')->default('active'); // active|isolated|disabled
            $table->string('sync_status')->default('pending'); // synced|pending|error
            $table->text('sync_message')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['mikrotik_router_id', 'username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pppoe_customers');
    }
};
