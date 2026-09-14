<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use App\Support\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppSettingBankTest extends TestCase
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
            'bank_name' => 'BCA',
            'bank_account_name' => 'PT Tesla Tech',
            'bank_account_number' => '1234567890',
            'bank_note' => 'Konfirmasi transfer ke WhatsApp',
        ], $overrides);
    }

    #[Test]
    public function admin_can_save_bank_account_on_app_settings(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->from('/admin/system')
            ->post('/admin/system', $this->systemPayload())
            ->assertRedirect('/admin/system');

        $this->assertSame('BCA', SiteSetting::getValue('bank_name'));
        $this->assertSame('PT Tesla Tech', SiteSetting::getValue('bank_account_name'));
        $this->assertSame('1234567890', SiteSetting::getValue('bank_account_number'));
        $this->assertTrue(AppSettings::hasBankAccount());
        $this->assertSame('BCA', AppSettings::bankAccount()['bank_name']);
        $this->assertCount(1, AppSettings::bankAccounts());
    }

    #[Test]
    public function admin_can_save_multiple_bank_accounts(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->from('/admin/system')
            ->post('/admin/system', $this->systemPayload([
                'bank_accounts' => [
                    [
                        'bank_name' => 'BCA',
                        'bank_account_name' => 'PT Tesla Tech',
                        'bank_account_number' => '1234567890',
                        'bank_note' => 'Utama',
                    ],
                    [
                        'bank_name' => '',
                        'bank_account_name' => '',
                        'bank_account_number' => '',
                        'bank_note' => '',
                    ],
                    [
                        'bank_name' => 'Mandiri',
                        'bank_account_name' => 'PT Tesla Tech',
                        'bank_account_number' => '9876543210',
                        'bank_note' => '',
                    ],
                ],
            ]))
            ->assertRedirect('/admin/system');

        $accounts = AppSettings::bankAccounts();
        $this->assertCount(2, $accounts);
        $this->assertSame('BCA', $accounts[0]['bank_name']);
        $this->assertSame('Mandiri', $accounts[1]['bank_name']);
        $this->assertSame('1234567890', $accounts[0]['bank_account_number']);
        $this->assertSame('9876543210', $accounts[1]['bank_account_number']);
        $this->assertSame('BCA', SiteSetting::getValue('bank_name'));
    }

    #[Test]
    public function settings_page_exposes_bank_accounts_array(): void
    {
        SiteSetting::setMany(AppSettings::bankAccountSettingValues([
            AppSettings::normalizeBankAccount([
                'bank_name' => 'BRI',
                'bank_account_name' => 'Tesla',
                'bank_account_number' => '111',
                'bank_note' => '',
            ]),
        ]));

        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->get('/admin/system')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/System/Settings')
                ->has('settings.bank_accounts', 1)
                ->where('settings.bank_accounts.0.bank_name', 'BRI')
            );
    }

    #[Test]
    public function saving_app_settings_does_not_change_messaging_notification_toggles(): void
    {
        SiteSetting::setMany([
            'messaging_notify_invoice' => '1',
            'messaging_notify_reminder' => '0',
            'messaging_notify_paid' => '1',
            'app_notif_whatsapp' => '1',
        ]);

        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->post('/admin/system', $this->systemPayload([
                'bank_name' => 'Mandiri',
            ]))
            ->assertRedirect('/admin/system');

        $this->assertTrue(AppSettings::notifyInvoice());
        $this->assertFalse(AppSettings::notifyReminder());
        $this->assertTrue(AppSettings::notifyPaid());
        $this->assertSame('Mandiri', SiteSetting::getValue('bank_name'));
    }

    #[Test]
    public function legacy_single_bank_fields_are_read_as_one_account(): void
    {
        SiteSetting::setMany([
            'bank_name' => 'BRI',
            'bank_account_name' => 'Tesla',
            'bank_account_number' => '111222',
            'bank_note' => 'Lama',
        ]);

        $accounts = AppSettings::bankAccounts();

        $this->assertCount(1, $accounts);
        $this->assertSame('BRI', $accounts[0]['bank_name']);
        $this->assertSame('111222', $accounts[0]['bank_account_number']);
        $this->assertTrue(AppSettings::hasBankAccount());
    }
}
