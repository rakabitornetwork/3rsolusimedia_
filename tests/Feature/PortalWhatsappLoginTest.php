<?php

namespace Tests\Feature;

use App\Models\MessageOutbox;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortalWhatsappLoginTest extends TestCase
{
    use RefreshDatabase;

    private function enableWhatsapp(): void
    {
        SiteSetting::setMany([
            'whatsapp_enabled' => '1',
            'whatsapp_base_url' => 'http://evolution.test',
            'whatsapp_api_key' => 'evo-key',
            'whatsapp_instance' => 'teslatech',
        ]);
    }

    private function fakeEvolution(): void
    {
        Http::fake([
            'http://evolution.test/*' => Http::response([
                'key' => ['id' => 'BAE1'],
                'status' => 'PENDING',
            ], 201),
        ]);
    }

    private function customer(array $overrides = []): PppoeCustomer
    {
        $router = MikrotikRouter::query()->create([
            'name' => 'Router '.uniqid(),
            'host' => '192.168.88.1',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);

        return PppoeCustomer::query()->create(array_merge([
            'mikrotik_router_id' => $router->id,
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'username' => 'budi'.uniqid(),
            'password' => 'secret',
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'active',
            'sync_status' => 'synced',
            'is_active' => true,
        ], $overrides));
    }

    private function sentCode(): string
    {
        $code = null;
        Http::assertSent(function ($request) use (&$code) {
            $text = (string) ($request->data()['text'] ?? '');
            if (preg_match('/Kode masuk portal .*: (\d{6})/', $text, $matches)) {
                $code = $matches[1];
            }

            return true;
        });

        $this->assertNotNull($code);

        return $code;
    }

    #[Test]
    public function username_login_still_opens_the_portal(): void
    {
        $customer = $this->customer(['username' => 'budi01']);

        $this->post('/portal/lookup', [
            'username' => 'budi01',
            'phone' => '6281234567890',
        ])->assertRedirect();

        $this->assertSame($customer->id, session('portal_customer_id'));
    }

    #[Test]
    public function registered_number_receives_otp_directly_and_can_enter_the_portal(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();
        $customer = $this->customer();

        $this->post('/portal/otp', ['phone' => '081234567890'])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHas('portal_otp_phone', '6281234567890');

        Http::assertSent(function ($request) {
            $data = $request->data();

            return str_contains($request->url(), '/message/sendText/teslatech')
                && ($data['number'] ?? '') === '6281234567890'
                && str_contains((string) ($data['text'] ?? ''), 'Jangan berikan kode ini');
        });
        $this->assertSame(0, MessageOutbox::query()->count());

        $code = $this->sentCode();

        $this->get('/portal')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Pay/Index')
                ->where('whatsapp_login.pending', true)
                ->where('whatsapp_login.phone', '6281234567890')
                ->where('whatsapp_login.phone_mask', '6281******890')
            )
            ->assertDontSee($code);

        $response = $this->post('/portal/otp/verify', [
            'phone' => '6281234567890',
            'code' => $code,
        ]);

        $response->assertRedirect();
        $this->assertMatchesRegularExpression(
            '#/portal/[a-z0-9]{32,64}$#',
            (string) $response->headers->get('Location'),
        );
        $this->assertSame($customer->id, session('portal_customer_id'));
        $this->assertNull(session('portal_otp_phone'));

        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Portal/Home'));
    }

    #[Test]
    public function unknown_number_does_not_send_and_uses_the_same_message(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();

        $this->post('/portal/otp', ['phone' => '0899990000111'])
            ->assertSessionHas('success', 'Jika nomor ini terdaftar, kode masuk sudah dikirim ke WhatsApp. Berlaku 5 menit.');

        Http::assertNothingSent();
        $this->assertSame(0, MessageOutbox::query()->count());
    }

    #[Test]
    public function shared_number_is_rejected_without_sending(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();
        $this->customer(['phone' => '081234567890', 'username' => 'satu']);
        $this->customer(['phone' => '6281234567890', 'username' => 'dua']);

        $this->post('/portal/otp', ['phone' => '081234567890'])
            ->assertSessionHasErrors([
                'whatsapp' => 'Nomor ini terdaftar pada lebih dari satu akun. Masuk dengan username PPPoE.',
            ]);

        Http::assertNothingSent();
    }

    #[Test]
    public function whatsapp_login_stays_closed_when_gateway_is_off(): void
    {
        $this->fakeEvolution();
        $this->customer();

        $this->post('/portal/otp', ['phone' => '081234567890'])
            ->assertSessionHasErrors('whatsapp');

        Http::assertNothingSent();
    }

    #[Test]
    public function failed_gateway_does_not_open_the_code_form(): void
    {
        $this->enableWhatsapp();
        Http::fake([
            'http://evolution.test/*' => Http::response(['error' => 'closed'], 500),
        ]);
        $this->customer();

        $this->post('/portal/otp', ['phone' => '081234567890'])
            ->assertSessionHasErrors('whatsapp')
            ->assertSessionMissing('portal_otp_phone');
    }

    #[Test]
    public function resend_is_cooled_down_for_one_minute(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();
        $this->customer();

        $this->post('/portal/otp', ['phone' => '081234567890'])->assertSessionHas('success');
        $this->post('/portal/otp', ['phone' => '081234567890'])
            ->assertSessionHasErrors('whatsapp');

        Http::assertSentCount(1);
    }

    #[Test]
    public function hourly_cap_blocks_another_code(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();
        $this->customer();
        Cache::put('portal_otp:hour:6281234567890', 5, now()->addHour());

        $this->post('/portal/otp', ['phone' => '081234567890'])
            ->assertSessionHasErrors('whatsapp');

        Http::assertNothingSent();
    }

    #[Test]
    public function wrong_code_is_rejected_and_locks_after_three_tries(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();
        $this->customer();

        $this->post('/portal/otp', ['phone' => '081234567890']);
        $code = $this->sentCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->post('/portal/otp/verify', ['phone' => '081234567890', 'code' => $wrong])
            ->assertSessionHasErrors(['code' => 'Kode tidak sesuai.']);
        $this->post('/portal/otp/verify', ['phone' => '081234567890', 'code' => $wrong])
            ->assertSessionHasErrors(['code' => 'Kode tidak sesuai.']);
        $this->post('/portal/otp/verify', ['phone' => '081234567890', 'code' => $wrong])
            ->assertSessionHasErrors(['code' => 'Kode salah terlalu banyak. Minta kode baru.']);
        $this->post('/portal/otp/verify', ['phone' => '081234567890', 'code' => $code])
            ->assertSessionHasErrors('code');

        $this->assertNull(session('portal_customer_id'));
    }

    #[Test]
    public function expired_code_cannot_be_used(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();
        $this->customer();

        $this->post('/portal/otp', ['phone' => '081234567890']);
        $code = $this->sentCode();

        $this->travel(6)->minutes();

        $this->post('/portal/otp/verify', ['phone' => '081234567890', 'code' => $code])
            ->assertSessionHasErrors(['code' => 'Kode tidak berlaku atau sudah kedaluwarsa. Minta kode baru.']);
    }
}
