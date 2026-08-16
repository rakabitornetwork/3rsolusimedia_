<?php

namespace App\Http\Controllers;

use App\Support\SeoMeta;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = [
            [
                'loc' => SeoMeta::publicUrl('/'),
                'changefreq' => 'weekly',
                'priority' => '1.0',
            ],
            [
                'loc' => SeoMeta::publicUrl('/terms-of-service'),
                'changefreq' => 'yearly',
                'priority' => '0.3',
            ],
        ];

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
