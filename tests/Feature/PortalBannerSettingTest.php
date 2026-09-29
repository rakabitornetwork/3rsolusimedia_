<?php

namespace Tests\Feature;

use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortalBannerSettingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function systemPayload(array $overrides = []): array
    {
        return array_merge([
            'app_timezone' => 'Asia/Jakarta',
            'app_currency_label' => 'Rp',
            'app_invoice_prefix' => 'INV',
            'app_billing_generate_days' => 7,
            'app_billing_round_to' => 1000,
            'app_default_billing_day' => 1,
            'app_auto_isolir' => '1',
        ], $overrides);
    }

    #[Test]
    public function banner_stays_hidden_until_it_is_enabled_with_an_image(): void
    {
        $this->assertFalse(AppSettings::portalBanner()['enabled']);

        SiteSetting::setMany([
            'portal_banner_enabled' => '1',
            'portal_banner_title' => 'Tanpa gambar',
        ]);

        $this->assertFalse(AppSettings::portalBanner()['enabled']);
    }

    #[Test]
    public function admin_can_save_a_portal_header_banner(): void
    {
        Storage::fake('public');
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->from('/admin/system')
            ->post('/admin/system', $this->systemPayload([
                'portal_banner_enabled' => '1',
                'portal_banner_title' => 'Upgrade Fiber',
                'portal_banner_subtitle' => 'Promo bulan ini',
                'portal_banner_link' => 'https://example.com/promo',
                'portal_banner_image' => UploadedFile::fake()->image('banner.jpg', 1680, 640),
            ]))
            ->assertRedirect('/admin/system')
            ->assertSessionHasNoErrors();

        $banner = AppSettings::portalBanner();
        $this->assertTrue($banner['enabled']);
        $this->assertSame('Upgrade Fiber', $banner['title']);
        $this->assertSame('Promo bulan ini', $banner['subtitle']);
        $this->assertSame('https://example.com/promo', $banner['link']);
        $this->assertStringStartsWith('/storage/uploads/portal-banner/', $banner['image']);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $banner['image']));
    }

    #[Test]
    public function invalid_banner_link_is_rejected(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->from('/admin/system')
            ->post('/admin/system', $this->systemPayload([
                'portal_banner_link' => 'javascript:alert(1)',
            ]))
            ->assertSessionHasErrors('portal_banner_link');
    }

    #[Test]
    public function portal_home_receives_the_saved_banner(): void
    {
        $router = MikrotikRouter::query()->create([
            'name' => 'Router 1',
            'host' => '192.168.88.1',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $customer = PppoeCustomer::query()->create([
            'mikrotik_router_id' => $router->id,
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'username' => 'budi01',
            'password' => 'secret',
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'active',
            'sync_status' => 'synced',
            'is_active' => true,
        ]);
        $token = Str::lower(Str::random(48));
        Cache::put('portal_pay:'.$token, $customer->id, now()->addHours(2));

        SiteSetting::setMany([
            'portal_banner_enabled' => '1',
            'portal_banner_image' => '/storage/uploads/portal-banner/promo.jpg',
            'portal_banner_title' => 'Halo pelanggan',
            'portal_banner_link' => '/promo',
        ]);

        $this->get("/portal/{$token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Home')
                ->where('banner.enabled', true)
                ->where('banner.image', '/storage/uploads/portal-banner/promo.jpg')
                ->where('banner.title', 'Halo pelanggan')
                ->where('banner.link', '/promo')
            );
    }
}
