<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SeoMeta
{
    /**
     * @return array{
     *     title: string,
     *     description: string,
     *     canonical: string,
     *     robots: string,
     *     indexable: bool,
     *     image: string,
     *     locale: string,
     *     company: string,
     *     tagline: string,
     *     json_ld: array<int, array<string, mixed>>
     * }
     */
    public static function fromRequest(?Request $request = null): array
    {
        $request ??= request();

        try {
            $settings = SiteSetting::allCached();
        } catch (\Throwable) {
            $settings = [];
        }

        $company = trim((string) ($settings['company_name'] ?? ''));
        if ($company === '') {
            $company = 'Tesla Tech';
        }

        $tagline = trim((string) ($settings['tagline'] ?? ''));
        if ($tagline === '') {
            $tagline = 'Internet rumah yang rapi, cepat, dan stabil';
        }

        $indexable = self::isIndexable($request);
        $canonical = $indexable
            ? self::publicUrl($request->path() === '/' ? '/' : '/'.$request->path())
            : self::publicUrl($request->getRequestUri() ?: '/');

        $title = self::pageTitle($request, $settings, $company, $tagline);
        $description = self::pageDescription($request, $settings, $company, $tagline);
        $image = self::shareImage($settings);

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $indexable ? 'index, follow' : 'noindex, nofollow',
            'indexable' => $indexable,
            'image' => $image,
            'locale' => 'id_ID',
            'company' => $company,
            'tagline' => $tagline,
            'json_ld' => $indexable && $request->is('/')
                ? self::jsonLd($settings, $company, $tagline, $description, $image)
                : [],
        ];
    }

    public static function isIndexable(?Request $request = null): bool
    {
        $request ??= request();

        if ($request->is('admin') || $request->is('admin/*') || $request->is('login')) {
            return false;
        }

        if ($request->is('portal') || $request->is('portal/*') || $request->is('bayar') || $request->is('bayar/*')) {
            return false;
        }

        if ($request->routeIs('home', 'terms')) {
            return true;
        }

        return $request->is('/') || $request->is('terms-of-service');
    }

    public static function publicUrl(string $path = '/'): string
    {
        $base = rtrim((string) config('app.url'), '/');
        if ($base === '') {
            $base = rtrim(request()->getSchemeAndHttpHost(), '/');
        }

        if ($path === '/' || $path === '') {
            return $base.'/';
        }

        return $base.'/'.ltrim($path, '/');
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private static function pageTitle(Request $request, array $settings, string $company, string $tagline): string
    {
        if (! self::isIndexable($request)) {
            return $company.' — Panel';
        }

        if ($request->is('terms-of-service')) {
            return 'Syarat & Ketentuan — '.$company;
        }

        $seoTitle = trim((string) ($settings['seo_title'] ?? ''));
        if ($seoTitle !== '') {
            return $seoTitle;
        }

        return $company.' — '.$tagline;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private static function pageDescription(Request $request, array $settings, string $company, string $tagline): string
    {
        if (! self::isIndexable($request)) {
            return 'Halaman internal '.$company.'.';
        }

        if ($request->is('terms-of-service')) {
            return 'Syarat dan ketentuan penggunaan layanan internet dan pemasangan WiFi rumahan '.$company.'.';
        }

        $seoDescription = trim((string) ($settings['seo_description'] ?? ''));
        if ($seoDescription !== '') {
            return $seoDescription;
        }

        return $company.' menyediakan pemasangan WiFi rumahan yang rapi, cepat, dan stabil. '
            .$tagline.'. Konsultasi gratis, instalasi profesional, dan dukungan after-sales.';
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private static function shareImage(array $settings): string
    {
        $custom = trim((string) ($settings['seo_image'] ?? ''));
        if ($custom !== '') {
            return self::absoluteAsset($custom);
        }

        $og = '/images/brand/og-image.png';
        if (is_file(public_path(ltrim($og, '/')))) {
            return self::absoluteAsset($og);
        }

        try {
            $favicon = AppSettings::branding()['favicon'] ?? '';
        } catch (\Throwable) {
            $favicon = '';
        }

        if (is_string($favicon) && $favicon !== '') {
            return self::absoluteAsset($favicon);
        }

        return self::absoluteAsset('/images/brand/logo-mark.svg');
    }

    private static function absoluteAsset(string $path): string
    {
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $clean = explode('?', $path, 2)[0];

        return self::publicUrl($clean);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, mixed>>
     */
    private static function jsonLd(
        array $settings,
        string $company,
        string $tagline,
        string $description,
        string $image,
    ): array {
        $url = self::publicUrl('/');
        $telephone = self::normalizePhone((string) ($settings['phone'] ?? $settings['whatsapp'] ?? ''));
        $email = trim((string) ($settings['email'] ?? ''));
        $address = trim((string) ($settings['address'] ?? ''));
        $sameAs = array_values(array_filter([
            trim((string) ($settings['instagram'] ?? '')),
            trim((string) ($settings['facebook'] ?? '')),
        ]));

        $organization = [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $company,
            'alternateName' => 'Teslatech',
            'url' => $url,
            'image' => $image,
            'logo' => self::absoluteAsset('/images/brand/logo-mark.svg'),
            'description' => $description,
            'slogan' => $tagline,
            'priceRange' => '$$',
            'areaServed' => [
                '@type' => 'Country',
                'name' => 'Indonesia',
            ],
        ];

        if ($telephone !== '') {
            $organization['telephone'] = $telephone;
        }

        if ($email !== '') {
            $organization['email'] = $email;
        }

        if ($address !== '') {
            $organization['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $address,
                'addressCountry' => 'ID',
            ];
        }

        if ($sameAs !== []) {
            $organization['sameAs'] = $sameAs;
        }

        $website = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $company,
            'url' => $url,
            'inLanguage' => 'id-ID',
            'description' => $description,
            'publisher' => [
                '@type' => 'Organization',
                'name' => $company,
                'url' => $url,
            ],
        ];

        return [$organization, $website];
    }

    private static function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?: '';
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return '+'.$digits;
    }
}
