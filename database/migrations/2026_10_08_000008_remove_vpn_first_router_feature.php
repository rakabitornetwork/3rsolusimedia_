<?php

use App\Models\PageSection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $section = PageSection::query()->where('key', 'vpn')->first();
        if (! $section) {
            return;
        }

        $content = $section->content ?? [];
        $features = array_values(array_filter(
            $content['features'] ?? [],
            fn ($feature) => $feature !== 'Router pertama gratis 3 hari setelah akun dibuat',
        ));

        $content['features'] = $features;
        $section->content = $content;
        $section->save();
    }

    public function down(): void
    {
        $section = PageSection::query()->where('key', 'vpn')->first();
        if (! $section) {
            return;
        }

        $content = $section->content ?? [];
        $features = $content['features'] ?? [];
        if (! in_array('Router pertama gratis 3 hari setelah akun dibuat', $features, true)) {
            $features[] = 'Router pertama gratis 3 hari setelah akun dibuat';
            $content['features'] = array_values($features);
            $section->content = $content;
            $section->save();
        }
    }
};
