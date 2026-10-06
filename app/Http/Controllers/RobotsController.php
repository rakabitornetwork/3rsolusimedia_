<?php

namespace App\Http\Controllers;

use App\Support\SeoMeta;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $sitemap = SeoMeta::publicUrl('/sitemap.xml');

        $body = <<<TXT
User-agent: *
Allow: /
Disallow: /admin
Disallow: /admin/
Disallow: /portal
Disallow: /portal/
Disallow: /bayar
Disallow: /bayar/
Disallow: /login
Disallow: /up
Disallow: /webhooks
Disallow: /webhooks/

Sitemap: {$sitemap}

TXT;

        return response($body, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
