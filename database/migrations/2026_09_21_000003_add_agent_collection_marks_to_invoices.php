<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('agent_cash')->default(false)->after('notes');
            $table->boolean('agent_ready_tf')->default(false)->after('agent_cash');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['agent_cash', 'agent_ready_tf']);
        });
    }
};
