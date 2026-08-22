<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MessageLog;
use App\Models\MessagingIdentity;
use App\Models\SiteSetting;
use App\Services\Messaging\EvolutionChannel;
use App\Services\Messaging\MessageTemplate;
use App\Services\Messaging\MessagingManager;
use App\Support\AppSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MessagingController extends Controller
{
    public function __construct(private readonly MessagingManager $channels)
    {
    }

    public function index(): Response
    {
        $config = AppSettings::messagingConfig();
        $telegram = $this->channels->driver('telegram');
        $whatsapp = $this->channels->driver('whatsapp');

        return Inertia::render('Admin/Messaging/Index', [
            'config' => $config,
            'webhook_urls' => [
                'telegram' => url('/webhooks/telegram'),
                'whatsapp' => url('/webhooks/evolution'),
            ],
            'webhook' => $telegram->isConfigured()
                ? [
                    'ok' => false,
                    'message' => 'Klik Tes koneksi atau Pasang webhook untuk memeriksa status Telegram.',
                    'url' => null,
                    'pending' => 0,
                ]
                : ['ok' => false, 'message' => 'Token bot belum diisi.', 'url' => null, 'pending' => 0],
            'whatsapp_status' => $whatsapp instanceof EvolutionChannel && $whatsapp->isConfigured()
                ? [
                    'ok' => false,
                    'reachable' => false,
                    'state' => 'unknown',
                    'message' => 'Klik Tes koneksi atau Hubungkan untuk memeriksa status WhatsApp.',
                ]
                : [
                    'ok' => false,
                    'reachable' => false,
                    'state' => 'unconfigured',
                    'message' => 'Isi URL, API key, dan instance Evolution, lalu simpan.',
                ],
            'enabled_channels' => $this->channels->enabledChannels(),
            'identities' => $this->adminIdentities(),
            'logs' => $this->adminLogs(),
            'stats' => $this->adminStats(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'telegram_enabled' => ['sometimes', 'boolean'],
            'telegram_bot_token' => ['nullable', 'string', 'max:255'],
            'telegram_admin_chat_id' => ['nullable', 'string', 'max:255'],
            'whatsapp_enabled' => ['sometimes', 'boolean'],
            'whatsapp_base_url' => ['nullable', 'string', 'max:255'],
            'whatsapp_api_key' => ['nullable', 'string', 'max:255'],
            'whatsapp_instance' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]*$/'],
            'whatsapp_test_number' => ['nullable', 'string', 'max:40'],
        ]);

        $values = [
            'telegram_enabled' => $request->boolean('telegram_enabled') ? '1' : '0',
            'telegram_admin_chat_id' => trim((string) ($validated['telegram_admin_chat_id'] ?? '')),
        ];

        if (filled($validated['telegram_bot_token'] ?? null)) {
            $values['telegram_bot_token'] = trim((string) $validated['telegram_bot_token']);
        }

        if ($request->exists('whatsapp_base_url') || $request->exists('whatsapp_instance')) {
            $values['whatsapp_enabled'] = $request->boolean('whatsapp_enabled') ? '1' : '0';
            $values['whatsapp_base_url'] = rtrim(trim((string) ($validated['whatsapp_base_url'] ?? '')), '/');
            $values['whatsapp_instance'] = trim((string) ($validated['whatsapp_instance'] ?? '')) ?: 'teslatech';
            $values['whatsapp_test_number'] = trim((string) ($validated['whatsapp_test_number'] ?? ''));
            if (filled($validated['whatsapp_api_key'] ?? null)) {
                $values['whatsapp_api_key'] = trim((string) $validated['whatsapp_api_key']);
            }
        }

        SiteSetting::setMany($values);

        return redirect()
            ->route('admin.messaging.index')
            ->with('success', 'Pengaturan kanal berhasil disimpan.');
    }

    public function updateTemplates(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_notif_whatsapp' => ['sometimes', 'boolean'],
            'messaging_notify_isolir' => ['sometimes', 'boolean'],
            'messaging_notify_welcome' => ['sometimes', 'boolean'],
            'msg_tpl_invoice' => ['nullable', 'string', 'max:4000'],
            'msg_tpl_reminder' => ['nullable', 'string', 'max:4000'],
            'msg_tpl_isolir' => ['nullable', 'string', 'max:4000'],
            'msg_tpl_restore' => ['nullable', 'string', 'max:4000'],
            'msg_tpl_welcome' => ['nullable', 'string', 'max:4000'],
        ]);

        $values = [
            'app_notif_whatsapp' => $request->boolean('app_notif_whatsapp') ? '1' : '0',
            'messaging_notify_isolir' => $request->boolean('messaging_notify_isolir') ? '1' : '0',
            'messaging_notify_welcome' => $request->boolean('messaging_notify_welcome') ? '1' : '0',
        ];

        foreach (array_keys(MessageTemplate::defaults()) as $key) {
            $field = MessageTemplate::settingKey($key);
            if (array_key_exists($field, $validated)) {
                $values[$field] = trim((string) $validated[$field]);
            }
        }

        SiteSetting::setMany($values);

        return redirect()
            ->route('admin.messaging.index')
            ->with('success', 'Template dan pengingat berhasil disimpan.');
    }

    public function test(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'channel' => ['required', Rule::in(['telegram', 'whatsapp'])],
            'chat_id' => ['nullable', 'string', 'max:40'],
        ]);

        $driver = $this->channels->driver($validated['channel']);
        $connection = $driver->testConnection();

        if (! ($connection['ok'] ?? false)) {
            return back()->with('error', $connection['message']);
        }

        if ($validated['channel'] === 'telegram' && ! empty($connection['username'])) {
            SiteSetting::setValue('telegram_bot_username', (string) $connection['username']);
        }

        $chatId = trim((string) ($validated['chat_id'] ?? ''));
        if ($chatId === '') {
            $chatId = $validated['channel'] === 'whatsapp'
                ? trim((string) AppSettings::get('whatsapp_test_number', ''))
                : trim((string) AppSettings::get('telegram_admin_chat_id', ''));
        }

        if ($chatId === '') {
            return back()->with(
                'success',
                $connection['message'].(isset($connection['latency_ms']) ? " ({$connection['latency_ms']} ms)" : '')
                .' Simpan Chat ID / nomor tes untuk mengirim pesan percobaan.'
            );
        }

        $company = AppSettings::companyName();
        $sent = $driver->send($chatId, 'Tes bot '.$company." berhasil.\nKetik bantuan di chat ini.");

        return back()->with(
            $sent['ok'] ? 'success' : 'error',
            $sent['ok']
                ? $connection['message'].' Pesan tes terkirim ke '.$chatId.'.'
                : $sent['message']
        );
    }

    public function setWebhook(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'channel' => ['required', Rule::in(['telegram', 'whatsapp'])],
        ]);

        $driver = $this->channels->driver($validated['channel']);
        if (! $driver->isConfigured()) {
            return back()->with('error', 'Lengkapi dan simpan pengaturan kanal terlebih dahulu.');
        }

        if ($validated['channel'] === 'telegram') {
            $secret = AppSettings::telegramWebhookSecret();
            if ($secret === '') {
                $secret = Str::lower(Str::random(40));
                SiteSetting::setValue('telegram_webhook_secret', $secret);
            }
            $url = url('/webhooks/telegram');
        } else {
            $secret = AppSettings::whatsappWebhookSecret();
            if ($secret === '') {
                $secret = Str::lower(Str::random(40));
                SiteSetting::setValue('whatsapp_webhook_secret', $secret);
            }
            $url = url('/webhooks/evolution');
        }

        $result = $driver->setWebhook($url, $secret);

        if ($validated['channel'] === 'telegram') {
            $connection = $driver->testConnection();
            if (($connection['ok'] ?? false) && ! empty($connection['username'])) {
                SiteSetting::setValue('telegram_bot_username', (string) $connection['username']);
            }
        }

        return back()->with(
            $result['ok'] ? 'success' : 'error',
            $result['message']
        );
    }

    public function telegramStatus(): JsonResponse
    {
        return response()->json($this->channels->driver('telegram')->webhookInfo());
    }

    public function whatsappStatus(): JsonResponse
    {
        $driver = $this->channels->driver('whatsapp');
        if (! $driver instanceof EvolutionChannel) {
            return response()->json(['ok' => false, 'message' => 'Kanal WhatsApp tidak tersedia.']);
        }

        $status = $driver->connectionStatus();

        return response()->json($status);
    }

    public function whatsappConnect(): JsonResponse
    {
        $driver = $this->channels->driver('whatsapp');
        if (! $driver instanceof EvolutionChannel) {
            return response()->json(['ok' => false, 'message' => 'Kanal WhatsApp tidak tersedia.']);
        }

        if (! $driver->isConfigured()) {
            return response()->json(['ok' => false, 'message' => 'Lengkapi URL, API key, dan instance lalu simpan.']);
        }

        $secret = AppSettings::whatsappWebhookSecret();
        if ($secret === '') {
            $secret = Str::lower(Str::random(40));
            SiteSetting::setValue('whatsapp_webhook_secret', $secret);
        }

        $driver->setWebhook(url('/webhooks/evolution'), $secret);
        $result = $driver->connect();

        return response()->json($result);
    }

    public function unbind(MessagingIdentity $identity): RedirectResponse
    {
        $label = $identity->customer?->username ?: $identity->external_id;
        $identity->delete();

        return back()->with('success', 'Ikatan '.$identity->channel.' untuk '.$label.' dilepas.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function adminIdentities(): array
    {
        if (! Schema::hasTable('messaging_identities')) {
            return [];
        }

        try {
            return MessagingIdentity::query()
                ->with('customer')
                ->latest('verified_at')
                ->limit(100)
                ->get()
                ->map(fn (MessagingIdentity $row) => $row->toAdminArray())
                ->values()
                ->all();
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function adminLogs(): array
    {
        if (! Schema::hasTable('message_logs')) {
            return [];
        }

        try {
            return MessageLog::query()
                ->with('customer')
                ->latest('id')
                ->limit(40)
                ->get()
                ->map(fn (MessageLog $row) => $row->toAdminArray())
                ->values()
                ->all();
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * @return array{bound: int, logs_today: int}
     */
    private function adminStats(): array
    {
        $stats = ['bound' => 0, 'logs_today' => 0];

        try {
            if (Schema::hasTable('messaging_identities')) {
                $stats['bound'] = MessagingIdentity::query()->count();
            }
            if (Schema::hasTable('message_logs')) {
                $stats['logs_today'] = MessageLog::query()
                    ->where('created_at', '>=', now()->startOfDay())
                    ->count();
            }
        } catch (Throwable $e) {
            report($e);
        }

        return $stats;
    }
}
