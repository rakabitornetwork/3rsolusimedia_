<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageSection;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteSectionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Website/Sections', [
            'sections' => PageSection::query()->orderBy('sort_order')->get(),
        ]);
    }
}
