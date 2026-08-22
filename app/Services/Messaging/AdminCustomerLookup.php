<?php

namespace App\Services\Messaging;

use App\Models\Invoice;
use App\Models\PppoeCustomer;
use App\Services\GenieAcsService;
use App\Support\AppSettings;
use Illuminate\Support\Collection;

class AdminCustomerLookup
{
    public const CALLBACK_PREFIX = 'cari';

    public function __construct(private readonly GenieAcsService $genie)
    {
    }

    public function isAdmin(IncomingMessage $message): bool
    {
        return $message->channel === 'telegram'
            && AppSettings::isTelegramAdminChat($message->externalId);
    }

    /**
     * @return Collection<int, PppoeCustomer>
     */
    public function search(string $term, int $limit = 8): Collection
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return collect();
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $lower = mb_strtolower($term);

        return PppoeCustomer::query()
            ->with(['package', 'router', 'agent'])
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('username', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            })
            ->orderByRaw(
                'CASE WHEN LOWER(username) = ? THEN 0 WHEN LOWER(name) = ? THEN 1 ELSE 2 END',
                [$lower, $lower],
            )
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function countMatches(string $term): int
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return 0;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return PppoeCustomer::query()
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('username', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            })
            ->count();
    }

    /**
     * @return array{text: string, keyboard: array<int, array<int, array{text: string, callback_data: string}>>}
     */
    public function menu(PppoeCustomer $customer): array
    {
        $customer->loadMissing(['package', 'router']);

        return [
            'text' => implode("\n", [
                '✅ Pelanggan ditemukan',
                '',
                '👤 Nama: '.$customer->name,
                '🔑 Username: '.$customer->username,
                'Status: '.$this->statusLabel($customer),
                '📦 Paket: '.$this->packageLabel($customer),
                '📡 Profil: '.$this->profileLabel($customer),
                '',
                'Pilih informasi yang ingin dilihat:',
            ]),
            'keyboard' => $this->actionKeyboard($customer),
        ];
    }

    /**
     * @param  Collection<int, PppoeCustomer>  $customers
     * @return array{text: string, keyboard: array<int, array<int, array{text: string, callback_data: string}>>}
     */
    public function choices(Collection $customers, int $total): array
    {
        $lines = [
            $total > $customers->count()
                ? '🔎 Ditemukan '.$total.' pelanggan. Menampilkan '.$customers->count().' teratas — perjelas nama jika belum tepat:'
                : '🔎 Ditemukan '.$customers->count().' pelanggan. Pilih salah satu:',
            '',
        ];
        $keyboard = [];

        foreach ($customers as $index => $customer) {
            $n = $index + 1;
            $lines[] = $n.'. '.$customer->name.' ('.$customer->username.') · '.$this->statusLabel($customer);
            $keyboard[] = [[
                'text' => $this->buttonLabel($customer->name, $customer->username),
                'callback_data' => $this->callback('menu', (int) $customer->id),
            ]];
        }

        return [
            'text' => implode("\n", $lines),
            'keyboard' => $keyboard,
        ];
    }

    /**
     * @return array{text: string, keyboard: array<int, array<int, array{text: string, callback_data: string}>>, pending?: array{type: string, customer_id: int}, log_text?: string}
     */
    public function action(string $action, PppoeCustomer $customer): array
    {
        $customer->loadMissing(['package', 'router', 'agent']);

        $result = match ($action) {
            'menu', 'pick' => $this->menu($customer),
            'prof' => $this->profile($customer),
            'rx' => $this->rxPower($customer),
            'temp' => $this->temperature($customer),
            'wifi' => $this->wifi($customer),
            'ewifi' => $this->editWifiPrompt($customer),
            'bill' => $this->bills($customer),
            'info' => $this->info($customer),
            default => [
                'text' => 'Pilihan tidak dikenali. Ketik /cari nama_pelanggan.',
                'keyboard' => $this->actionKeyboard($customer),
            ],
        };

        $result['keyboard'] ??= $this->actionKeyboard($customer);

        return $result;
    }

    /**
     * @return array{ssid: ?string, password: ?string, error?: string}
     */
    public function parseWifiInput(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['ssid' => null, 'password' => null, 'error' => 'Kirim SSID dan/atau password baru.'];
        }

        if (str_contains($text, '|')) {
            [$ssid, $password] = array_pad(array_map('trim', explode('|', $text, 2)), 2, '');
        } elseif (str_starts_with($text, '*')) {
            $ssid = '';
            $password = trim(substr($text, 1));
        } else {
            $parts = preg_split('/\s+/', $text, 2) ?: [];
            $ssid = trim((string) ($parts[0] ?? ''));
            $password = trim((string) ($parts[1] ?? ''));
        }

        if ($ssid === '' && $password === '') {
            return ['ssid' => null, 'password' => null, 'error' => 'Kirim SSID dan/atau password baru.'];
        }

        if ($ssid !== '' && (mb_strlen($ssid) < 1 || mb_strlen($ssid) > 32)) {
            return ['ssid' => null, 'password' => null, 'error' => 'SSID harus 1–32 karakter.'];
        }

        if ($password !== '' && (strlen($password) < 8 || strlen($password) > 63)) {
            return ['ssid' => null, 'password' => null, 'error' => 'Password WiFi harus 8–63 karakter.'];
        }

        return [
            'ssid' => $ssid !== '' ? $ssid : null,
            'password' => $password !== '' ? $password : null,
        ];
    }

    /**
     * @return array{text: string, keyboard: array<int, array<int, array{text: string, callback_data: string}>>}
     */
    public function applyWifi(PppoeCustomer $customer, ?string $ssid, ?string $password): array
    {
        $owned = $this->ownedDevice($customer);
        if (! ($owned['ok'] ?? false)) {
            return [
                'text' => $owned['message'] ?? 'Perangkat ONU tidak ditemukan.',
                'keyboard' => $this->actionKeyboard($customer),
            ];
        }

        $result = $this->genie->updateWifi(
            (string) $owned['device']['id'],
            $ssid,
            $password,
        );

        $lines = [
            ($result['ok'] ?? false) ? '✅ WiFi berhasil diubah.' : '⚠️ Gagal mengubah WiFi.',
            $result['message'] ?? '',
            '',
            'Pelanggan: '.$customer->name.' ('.$customer->username.')',
        ];
        if ($ssid) {
            $lines[] = 'SSID baru: '.$ssid;
        }
        if ($password) {
            $lines[] = 'Password baru: '.$password;
        }

        return [
            'text' => trim(implode("\n", array_filter($lines, fn ($line) => $line !== null))),
            'keyboard' => $this->actionKeyboard($customer),
            'log_text' => $this->redactWifiText(trim(implode("\n", array_filter($lines, fn ($line) => $line !== null))), $password),
        ];
    }

    /**
     * @return array{action: string, customer_id: int}|null
     */
    public function parseCallback(string $data): ?array
    {
        $parts = explode(':', $data);
        if (count($parts) !== 3 || $parts[0] !== self::CALLBACK_PREFIX) {
            return null;
        }

        $action = $parts[1];
        $customerId = (int) $parts[2];
        if ($customerId < 1 || $action === '') {
            return null;
        }

        return [
            'action' => $action,
            'customer_id' => $customerId,
        ];
    }

    /**
     * @return array{text: string, keyboard: array<int, array<int, array{text: string, callback_data: string}>>}
     */
    private function profile(PppoeCustomer $customer): array
    {
        $package = $customer->package;
        $price = $package?->price !== null
            ? 'Rp '.number_format((int) $package->price, 0, ',', '.')
            : '—';

        return [
            'text' => implode("\n", [
                '📡 Profil layanan — '.$customer->name,
                '',
                '📦 Paket: '.($package?->name ?: '—').' ('.$price.')',
                '🛠 Profil MikroTik: '.$this->profileLabel($customer),
                '🚫 Profil isolir: '.($customer->isolir_profile ?: '—'),
                '🖥 Router: '.($customer->router?->name ?: '—'),
                'Status: '.$this->statusLabel($customer),
                '📅 Mulai: '.$this->dateLabel($customer->start_date),
                '🗓 Hari tagihan: '.($customer->billing_day ?: '—'),
                '⏰ Jatuh tempo: '.$this->dateLabel($customer->due_date),
            ]),
            'keyboard' => $this->actionKeyboard($customer),
        ];
    }

    /**
     * @return array{text: string, keyboard: array<int, array<int, array{text: string, callback_data: string}>>}
     */
    private function rxPower(PppoeCustomer $customer): array
    {
        $owned = $this->ownedDevice($customer);
        if (! ($owned['ok'] ?? false)) {
            return [
                'text' => $owned['message'] ?? 'RX Power tidak tersedia.',
                'keyboard' => $this->actionKeyboard($customer),
            ];
        }

        $device = $owned['device'];

        return [
            'text' => implode("\n", [
                '📶 RX Power — '.$customer->name.' ('.$customer->username.')',
                '',
                '📟 ONU: '.$this->onuName($device),
                '🔢 Serial: '.($device['serial'] ?? '—'),
                $this->onuStatusLine($device),
                '📥 RX Power: '.($device['rx_power_label'] ?? '—'),
                '📤 TX Power: '.($device['tx_power_label'] ?? '—'),
                '📉 Redaman: '.($device['redaman_label'] ?? '—'),
                '🕒 Inform terakhir: '.($device['last_inform_label'] ?? '—'),
            ]),
            'keyboard' => $this->actionKeyboard($customer),
        ];
    }

    /**
     * @return array{text: string, keyboard: array<int, array<int, array{text: string, callback_data: string}>>}
     */
    private function temperature(PppoeCustomer $customer): array
    {
        $owned = $this->ownedDevice($customer);
        if (! ($owned['ok'] ?? false)) {
            return [
                'text' => $owned['message'] ?? 'Suhu ONU tidak tersedia.',
                'keyboard' => $this->actionKeyboard($customer),
            ];
        }

        $device = $owned['device'];

        return [
            'text' => implode("\n", [
                '🌡 Suhu ONU — '.$customer->name.' ('.$customer->username.')',
                '',
                '🌡 Suhu: '.($device['temperature_label'] ?? '—'),
                $this->onuStatusLine($device),
                '🕒 Inform terakhir: '.($device['last_inform_label'] ?? '—'),
            ]),
            'keyboard' => $this->actionKeyboard($customer),
        ];
    }

    /**
     * @return array{text: string, keyboard: array<int, array<int, array{text: string, callback_data: string}>>, log_text?: string}
     */
    private function wifi(PppoeCustomer $customer): array
    {
        $owned = $this->ownedDevice($customer);
        if (! ($owned['ok'] ?? false)) {
            return [
                'text' => $owned['message'] ?? 'SSID tidak tersedia.',
                'keyboard' => $this->actionKeyboard($customer),
            ];
        }

        $device = $owned['device'];
        $ssid = (string) ($device['ssid'] ?? '');
        $password = (string) ($device['ssid_password'] ?? '');
        $text = implode("\n", [
            '🔐 WiFi — '.$customer->name.' ('.$customer->username.')',
            '',
            '📡 SSID: '.($ssid !== '' ? $ssid : '—'),
            '🔑 Password: '.($password !== '' ? $password : '—'),
        ]);

        return [
            'text' => $text,
            'keyboard' => $this->actionKeyboard($customer),
            'log_text' => $this->redactWifiText($text, $password !== '' ? $password : null),
        ];
    }

    /**
     * @return array{text: string, keyboard: array<int, array<int, array{text: string, callback_data: string}>>, pending: array{type: string, customer_id: int}}
     */
    private function editWifiPrompt(PppoeCustomer $customer): array
    {
        $currentSsid = '—';
        $owned = $this->ownedDevice($customer);
        if (($owned['ok'] ?? false) && ! empty($owned['device']['ssid'])) {
            $currentSsid = (string) $owned['device']['ssid'];
        }

        return [
            'text' => implode("\n", [
                '✏️ Edit WiFi — '.$customer->name,
                '',
                '📡 SSID saat ini: '.$currentSsid,
                '',
                'Kirim SSID dan/atau password baru, contoh:',
                'RumahBudi | passwordbaru123',
                '',
                'Hanya SSID:',
                'RumahBudi',
                '',
                'Hanya password:',
                '*passwordbaru123',
                '',
                'Ketik /batal untuk membatalkan.',
            ]),
            'keyboard' => $this->actionKeyboard($customer),
            'pending' => [
                'type' => 'edit_wifi',
                'customer_id' => (int) $customer->id,
            ],
        ];
    }

    /**
     * @return array{text: string, keyboard: array<int, array<int, array{text: string, callback_data: string}>>}
     */
    private function bills(PppoeCustomer $customer): array
    {
        $invoices = Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->orderByRaw("CASE WHEN status = 'unpaid' THEN 0 ELSE 1 END")
            ->orderByDesc('due_date')
            ->limit(8)
            ->get();

        $unpaid = $invoices->where('status', 'unpaid');
        $lines = [
            '💳 Tagihan — '.$customer->name.' ('.$customer->username.')',
            '',
        ];

        if ($invoices->isEmpty()) {
            $lines[] = 'Belum ada tagihan tercatat.';
        } else {
            $unpaidTotal = (int) $unpaid->sum('total');
            $lines[] = $unpaid->isEmpty()
                ? '✅ Tidak ada tagihan yang belum lunas.'
                : '🔴 Belum lunas: '.$unpaid->count().' · Total '.$this->rupiah($unpaidTotal);
            $lines[] = '';

            foreach ($invoices as $invoice) {
                $due = $invoice->due_date?->format('d/m/Y') ?? '—';
                $late = $invoice->isOverdue() ? ' · terlambat' : '';
                $status = $invoice->status === 'unpaid' ? '🔴 belum lunas' : ($invoice->status === 'paid' ? '🟢 lunas' : $invoice->status);
                $lines[] = '📄 '.$invoice->number.' · '.$this->rupiah((int) $invoice->total).' · '.$status.' · '.$due.$late;
            }
        }

        $lines[] = '';
        $lines[] = '⏰ Jatuh tempo langganan: '.$this->dateLabel($customer->due_date);

        return [
            'text' => implode("\n", $lines),
            'keyboard' => $this->actionKeyboard($customer),
        ];
    }

    /**
     * @return array{text: string, keyboard: array<int, array<int, array{text: string, callback_data: string}>>}
     */
    private function info(PppoeCustomer $customer): array
    {
        $lines = [
            'ℹ️ Info — '.$customer->name,
            '',
            '🔑 Username: '.$customer->username,
            '📱 HP: '.($customer->phone ?: '—'),
            '📍 Alamat: '.($customer->address ?: '—'),
            'Status: '.$this->statusLabel($customer),
            '📦 Paket: '.$this->packageLabel($customer),
            '📡 Profil: '.$this->profileLabel($customer),
            '🖥 Router: '.($customer->router?->name ?: '—'),
            '🤝 Agen: '.($customer->agent?->name ?: '—'),
            '⏰ Jatuh tempo: '.$this->dateLabel($customer->due_date),
            '⏳ Grace: '.$this->graceLabel($customer),
            '📝 Catatan: '.($customer->notes ?: '—'),
        ];

        $owned = $this->ownedDevice($customer);
        if (($owned['ok'] ?? false)) {
            $device = $owned['device'];
            $lines[] = '';
            $lines[] = '📟 ONU: '.(($device['online'] ?? false) ? '🟢 Online' : '🔴 Offline')
                .' · SN '.($device['serial'] ?? '—')
                .' · RX '.($device['rx_power_label'] ?? '—')
                .' · '.$this->nullToDash($device['temperature_label'] ?? null);
        }

        return [
            'text' => implode("\n", $lines),
            'keyboard' => $this->actionKeyboard($customer),
        ];
    }

    /**
     * @return array<int, array<int, array{text: string, callback_data: string}>>
     */
    private function actionKeyboard(PppoeCustomer $customer): array
    {
        $id = (int) $customer->id;

        return [
            [
                ['text' => '📡 Profil layanan', 'callback_data' => $this->callback('prof', $id)],
            ],
            [
                ['text' => '📶 RX Power', 'callback_data' => $this->callback('rx', $id)],
                ['text' => '🌡 Suhu', 'callback_data' => $this->callback('temp', $id)],
            ],
            [
                ['text' => '🔐 Lihat SSID & Password', 'callback_data' => $this->callback('wifi', $id)],
            ],
            [
                ['text' => '✏️ Edit SSID & Password', 'callback_data' => $this->callback('ewifi', $id)],
            ],
            [
                ['text' => '💳 Tagihan', 'callback_data' => $this->callback('bill', $id)],
                ['text' => 'ℹ️ Info penting', 'callback_data' => $this->callback('info', $id)],
            ],
        ];
    }

    /**
     * @return array{ok: bool, message?: string, device?: array<string, mixed>}
     */
    private function ownedDevice(PppoeCustomer $customer): array
    {
        if (! $this->genie->isConfigured()) {
            return [
                'ok' => false,
                'message' => 'GenieACS belum dikonfigurasi. Data ONU tidak tersedia.',
            ];
        }

        $result = $this->genie->findDeviceByPppoeUsername((string) $customer->username);
        if (! ($result['ok'] ?? false) || empty($result['device']['id'])) {
            return [
                'ok' => false,
                'message' => $result['message'] ?? 'Perangkat ONU tidak ditemukan untuk akun ini.',
            ];
        }

        return [
            'ok' => true,
            'device' => $result['device'],
        ];
    }

    private function callback(string $action, int $customerId): string
    {
        return self::CALLBACK_PREFIX.':'.$action.':'.$customerId;
    }

    private function statusLabel(PppoeCustomer $customer): string
    {
        $label = match ($customer->status) {
            'active' => '🟢 Aktif',
            'isolated' => '🔴 Isolir',
            'disabled' => '⚫ Nonaktif',
            default => (string) $customer->status,
        };

        if ($customer->isOverdue() && $customer->status !== 'isolated') {
            $label .= ' · lewat tempo';
        }

        if ($customer->hasActiveGrace()) {
            $label .= ' · grace';
        }

        return $label;
    }

    private function packageLabel(PppoeCustomer $customer): string
    {
        return $customer->package?->name ?: '—';
    }

    private function profileLabel(PppoeCustomer $customer): string
    {
        return $customer->service_profile
            ?: ($customer->package?->mikrotik_profile ?: '—');
    }

    private function graceLabel(PppoeCustomer $customer): string
    {
        if (! $customer->grace_until) {
            return '—';
        }

        $label = $this->dateLabel($customer->grace_until);
        if ($customer->hasActiveGrace()) {
            $label .= ' (berlaku)';
        }
        if ($customer->grace_note) {
            $label .= ' · '.$customer->grace_note;
        }

        return $label;
    }

    private function dateLabel(mixed $date): string
    {
        if ($date && method_exists($date, 'format')) {
            return $date->format('d/m/Y');
        }

        return '—';
    }

    private function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    private function buttonLabel(string $name, string $username): string
    {
        $label = '👤 '.$name.' ('.$username.')';

        return mb_strlen($label) > 60 ? mb_substr($label, 0, 57).'…' : $label;
    }

    private function nullToDash(mixed $value): string
    {
        $text = trim((string) $value);

        return $text !== '' && $text !== '—' ? $text : '—';
    }

    private function redactWifiText(string $text, ?string $password): string
    {
        if ($password === null || $password === '') {
            return $text;
        }

        return str_replace($password, '••••••••', $text);
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function onuName(array $device): string
    {
        $name = trim(($device['manufacturer'] ?? '').' '.($device['model'] ?? ''));

        return $name !== '' ? $name : '—';
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function onuStatusLine(array $device): string
    {
        return (($device['online'] ?? false) ? '🟢' : '🔴').' Status ONU: '
            .(($device['online'] ?? false) ? 'Online' : 'Offline');
    }
}
