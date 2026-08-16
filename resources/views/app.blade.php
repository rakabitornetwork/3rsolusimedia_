<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @php
            try {
                $seo = \App\Support\SeoMeta::fromRequest(request());
            } catch (\Throwable $e) {
                $seo = [
                    'title' => config('app.name', 'Tesla Tech'),
                    'description' => 'Pemasangan WiFi rumahan yang rapi, cepat, dan stabil.',
                    'canonical' => url()->current(),
                    'robots' => 'index, follow',
                    'indexable' => true,
                    'image' => url('/images/brand/logo-mark.svg'),
                    'locale' => 'id_ID',
                    'company' => config('app.name', 'Tesla Tech'),
                    'tagline' => '',
                    'json_ld' => [],
                ];
            }
            try {
                $favicon = \App\Support\AppSettings::branding()['favicon'] ?? '/images/brand/favicon.png';
            } catch (\Throwable $e) {
                $favicon = '/images/brand/favicon.png';
            }
        @endphp
        <title>{{ $seo['title'] }}</title>
        <meta name="description" content="{{ $seo['description'] }}">
        <meta name="robots" content="{{ $seo['robots'] }}">
        <meta name="author" content="{{ $seo['company'] }}">
        <meta name="theme-color" content="#0A2D82">
        <link rel="canonical" href="{{ $seo['canonical'] }}">
        <link rel="alternate" hreflang="id" href="{{ $seo['canonical'] }}">
        <link rel="icon" type="image/png" href="{{ $favicon }}">
        <link rel="apple-touch-icon" href="{{ $favicon }}">

        <meta property="og:type" content="{{ $seo['indexable'] ? 'website' : 'website' }}">
        <meta property="og:site_name" content="{{ $seo['company'] }}">
        <meta property="og:locale" content="{{ $seo['locale'] }}">
        <meta property="og:title" content="{{ $seo['title'] }}">
        <meta property="og:description" content="{{ $seo['description'] }}">
        <meta property="og:url" content="{{ $seo['canonical'] }}">
        <meta property="og:image" content="{{ $seo['image'] }}">
        <meta property="og:image:alt" content="{{ $seo['company'] }}">

        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $seo['title'] }}">
        <meta name="twitter:description" content="{{ $seo['description'] }}">
        <meta name="twitter:image" content="{{ $seo['image'] }}">

        @foreach ($seo['json_ld'] as $schema)
            <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
        @endforeach

        @inertiaHead
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
