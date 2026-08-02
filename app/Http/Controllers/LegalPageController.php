<?php

namespace App\Http\Controllers;

use App\Models\PageSection;
use App\Models\SiteSetting;
use Inertia\Inertia;
use Inertia\Response;

class LegalPageController extends Controller
{
    public function terms(): Response
    {
        $section = PageSection::query()->where('key', 'terms')->firstOrFail();
        $footer = PageSection::query()->where('key', 'footer')->first();

        return Inertia::render('Legal/Terms', [
            'section' => $section,
            'footer' => $footer,
            'settings' => SiteSetting::allCached(),
        ]);
    }
}
