<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    public function up(): void
    {
        Cache::forget('site_settings');
    }

    public function down(): void
    {
        Cache::forget('site_settings');
    }
};
