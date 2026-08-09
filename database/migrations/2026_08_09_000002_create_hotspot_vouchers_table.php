<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotspot_vouchers', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id')->index();
            $table->foreignId('mikrotik_router_id')->constrained('mikrotik_routers')->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('username', 80);
            $table->string('password', 80);
            $table->string('profile', 120)->nullable();
            $table->string('server', 120)->nullable();
            $table->string('limit_uptime', 40)->nullable();
            $table->unsignedBigInteger('limit_bytes_total')->nullable();
            $table->string('comment', 255)->nullable();
            $table->string('code_format', 30)->default('alphanumeric');
            $table->string('agent_name', 120)->nullable();
            $table->unsignedInteger('base_price')->default(0);
            $table->unsignedInteger('commission')->default(0);
            $table->unsignedInteger('sell_price')->default(0);
            $table->string('status', 20)->default('available')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('deleted_from_router_at')->nullable();
            $table->timestamps();

            $table->unique(['mikrotik_router_id', 'username']);
            $table->index(['status', 'created_at']);
            $table->index(['agent_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotspot_vouchers');
    }
};
