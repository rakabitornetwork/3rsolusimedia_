<?php

namespace App\Http\Controllers;

use App\Models\PageSection;
use App\Models\SiteSetting;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function __invoke(): Response
    {
        $sections = PageSection::query()
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('key');

        return Inertia::render('Landing', [
            'sections' => $sections,
            'settings' => SiteSetting::allCached(),
        ]);
    }
}
