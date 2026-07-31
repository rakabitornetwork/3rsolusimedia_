<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @php
            try {
                $favicon = \App\Support\AppSettings::branding()['favicon'] ?? '/images/brand/favicon.png';
            } catch (\Throwable $e) {
                $favicon = '/images/brand/favicon.png';
            }
        @endphp
        <link rel="icon" type="image/png" href="{{ $favicon }}">
        <link rel="apple-touch-icon" href="{{ $favicon }}">
        @inertiaHead
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
