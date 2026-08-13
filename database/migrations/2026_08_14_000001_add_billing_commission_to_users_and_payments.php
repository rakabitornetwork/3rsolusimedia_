<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('billing_commission')->default(0)->after('voucher_commission');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('agent_id')
                ->nullable()
                ->after('received_by')
                ->constrained('users')
                ->nullOnDelete();
            $table->unsignedInteger('agent_commission')->default(0)->after('agent_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_id');
            $table->dropColumn('agent_commission');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('billing_commission');
        });
    }
};
