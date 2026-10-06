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
    public function default_portal_shows_three_rotating_banners(): void
    {
        SiteSetting::setValue('whatsapp', '6287778888820');

        $banners = AppSettings::portalBanners('tokenportal');

        $this->assertCount(3, $banners);
        $this->assertStringContainsString('/images/portal/banner-referral.png', $banners[0]['image']);
        $this->assertStringContainsString('wa.me/6287778888820', $banners[0]['link']);
        $this->assertSame(
            'https://wa.me/6285168100781?text=bayar',
            $banners[1]['link'],
        );
        $this->assertSame('/portal/tokenportal/perangkat', $banners[2]['link']);
    }

    #[Test]
    public function disabled_banner_is_omitted(): void
    {
        SiteSetting::setValue('portal_banner_2_enabled', '0');

        $banners = AppSettings::portalBanners();

        $this->assertCount(2, $banners);
        $this->assertSame('Pasang atau pindah WiFi', $banners[0]['title']);
        $this->assertSame('Pantau WiFi sendiri', $banners[1]['title']);
    }

    #[Test]
    public function admin_can_replace_a_portal_banner_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->from('/admin/system')
            ->post('/admin/system', $this->systemPayload([
                'portal_banner_2_enabled' => '1',
                'portal_banner_2_title' => 'Bayar sekarang',
                'portal_banner_2_link' => 'https://example.com/bayar',
                'portal_banner_2_image' => UploadedFile::fake()->image('banner.jpg', 1280, 720),
            ]))
            ->assertRedirect('/admin/system')
            ->assertSessionHasNoErrors();

        $banners = collect(AppSettings::portalBanners());
        $replaced = $banners->firstWhere('title', 'Bayar sekarang');
        $this->assertNotNull($replaced);
        $this->assertStringStartsWith('/storage/uploads/portal-banner/', $replaced['image']);
        $this->assertSame('https://example.com/bayar', $replaced['link']);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', strtok($replaced['image'], '?')));
    }

    #[Test]
    public function invalid_banner_link_is_rejected(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->from('/admin/system')
            ->post('/admin/system', $this->systemPayload([
                'portal_banner_2_link' => 'javascript:alert(1)',
            ]))
            ->assertSessionHasErrors('portal_banner_2_link');
    }

    #[Test]
    public function portal_home_receives_the_banner_carousel(): void
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
        SiteSetting::setValue('whatsapp', '6287778888820');

        $this->get("/portal/{$token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Home')
                ->has('banners', 3)
                ->where('banners.1.title', 'Bayar tagihan lewat WhatsApp')
                ->where('banners.1.link', 'https://wa.me/6285168100781?text=bayar')
                ->where('banners.2.link', '/portal/'.$token.'/perangkat')
            );
    }
}
