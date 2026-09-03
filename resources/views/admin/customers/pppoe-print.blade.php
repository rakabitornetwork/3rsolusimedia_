@php
    /** @var \Illuminate\Support\Collection<\App\Models\PppoeCustomer> $customers */
    /** @var \Carbon\Carbon $date */
    $bulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
    $fmtDate = function ($d) use ($bulan) {
        if (! $d) {
            return '—';
        }
        $c = \Carbon\Carbon::parse($d);

        return $c->day.' '.$bulan[$c->month].' '.$c->year;
    };
    $fmtShort = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : '—';
    $fmtDateTime = function ($d) use ($bulan) {
        $c = \Carbon\Carbon::parse($d);

        return $c->day.' '.$bulan[$c->month].' '.$c->year.' '.$c->format('H:i');
    };
    $statusLabel = [
        'active' => 'Aktif',
        'isolated' => 'Isolir',
        'disabled' => 'Nonaktif',
    ];
    $contact = collect([
        $company['phone'] ?? null,
        ! empty($company['whatsapp']) ? 'WA '.$company['whatsapp'] : null,
    ])->filter()->implode(' · ');

    $filterBits = collect([
        $date_field_label.': '.$fmtDate($date),
        $date_field === 'billing_day' ? 'tiap tgl '.$date->day : null,
        $router?->name ? 'Router: '.$router->name : null,
        $status ? 'Status: '.($statusLabel[$status] ?? ($status === 'grace' ? 'Grace' : $status)) : null,
    ])->filter()->implode(' · ');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Pelanggan PPPoE · {{ $fmtShort($date) }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Syne:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 10mm 14mm;
        }

        * { box-sizing: border-box; }

        :root {
            --ink: #0b1526;
            --ink-soft: #3a4658;
            --muted: #6b7789;
            --paper: #ffffff;
            --mist: #f3f6fa;
            --line: #e6ebf2;
            --signal: #1a6eff;
            --signal-deep: #0a2d82;
            --font-body: "Manrope", "Segoe UI", system-ui, sans-serif;
            --font-display: "Syne", "Manrope", system-ui, sans-serif;
        }

        html, body {
            margin: 0;
            padding: 0;
            background: #d5dee6;
            color: var(--ink);
            font-family: var(--font-body);
            font-size: 10pt;
            line-height: 1.4;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            justify-content: space-between;
            padding: 12px 18px;
            background: var(--ink);
            color: #fff;
        }

        .toolbar p {
            margin: 0;
            font-size: 13px;
            color: rgba(255,255,255,0.82);
        }

        .toolbar-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .toolbar a,
        .toolbar button {
            appearance: none;
            border: 1px solid rgba(255,255,255,0.22);
            background: transparent;
            color: #fff;
            padding: 8px 12px;
            font-size: 12.5px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
        }

        .toolbar button.primary {
            background: var(--signal);
            border-color: var(--signal);
        }

        .preview { padding: 22px 12px 48px; }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: var(--paper);
            box-shadow: 0 18px 50px rgba(11, 21, 38, 0.16);
            padding: 12mm 10mm 14mm;
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .brand {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            min-width: 0;
        }

        .logo {
            height: 40px;
            width: 40px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .company {
            margin: 0;
            font-family: var(--font-display);
            font-size: 15pt;
            font-weight: 700;
            line-height: 1.1;
            letter-spacing: -0.02em;
        }

        .tagline {
            margin: 3px 0 0;
            font-size: 8.5pt;
            font-weight: 500;
            color: var(--signal-deep);
        }

        .contact {
            margin: 2px 0 0;
            font-size: 8pt;
            color: var(--muted);
        }

        .doc {
            text-align: right;
            flex-shrink: 0;
        }

        .doc-kicker {
            margin: 0;
            font-family: var(--font-display);
            font-size: 8pt;
            font-weight: 700;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--signal);
        }

        .doc-title {
            margin: 4px 0 0;
            font-family: var(--font-display);
            font-size: 13pt;
            font-weight: 700;
            color: var(--ink);
        }

        .doc-meta {
            margin: 4px 0 0;
            font-size: 8.5pt;
            color: var(--ink-soft);
            max-width: 95mm;
        }

        .accent {
            height: 2.5px;
            margin: 0 0 12px;
            background: linear-gradient(90deg, var(--signal-deep) 0%, var(--signal) 55%, #00b7ff 100%);
            border-radius: 999px;
        }

        .summary {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 18px;
            margin-bottom: 12px;
            padding: 8px 10px;
            background: var(--mist);
            border-left: 3px solid var(--signal);
            font-size: 9pt;
            color: var(--ink-soft);
        }

        .summary strong {
            color: var(--ink);
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 7px 6px;
            text-align: left;
            vertical-align: top;
        }

        th {
            font-family: var(--font-display);
            font-size: 7pt;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--signal-deep);
            border-bottom: 1.5px solid var(--signal-deep);
            padding-bottom: 8px;
        }

        td {
            border-bottom: 1px solid var(--line);
            font-size: 9pt;
            color: var(--ink);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .num {
            width: 28px;
            text-align: center;
            color: var(--muted);
            font-variant-numeric: tabular-nums;
        }

        .name {
            font-weight: 700;
        }

        .sub {
            display: block;
            margin-top: 1px;
            font-size: 8pt;
            font-weight: 500;
            color: var(--muted);
        }

        .empty {
            padding: 36px 12px;
            text-align: center;
            color: var(--muted);
            font-size: 11pt;
        }

        .foot {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-top: 14px;
            padding-top: 8px;
            border-top: 1px solid var(--line);
            font-size: 7.5pt;
            color: var(--muted);
        }

        @media print {
            html, body { background: #fff; }
            .toolbar, .no-print { display: none !important; }
            .preview { padding: 0; }
            .sheet {
                box-shadow: none;
                width: auto;
                min-height: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <p>
            Daftar pelanggan PPPoE · diurutkan A → Z ·
            <strong>{{ $customers->count() }}</strong> data
        </p>
        <div class="toolbar-actions">
            <button type="button" class="primary" onclick="window.print()">Cetak</button>
            <a href="{{ route('admin.customers.pppoe') }}">Kembali</a>
        </div>
    </div>

    <div class="preview">
        <div class="sheet">
            <div class="header">
                <div class="brand">
                    @if (! empty($company['logo']))
                        <img class="logo" src="{{ $company['logo'] }}" alt="">
                    @endif
                    <div>
                        <p class="company">{{ $company['name'] ?: 'RT RW Net' }}</p>
                        @if (! empty($company['tagline']))
                            <p class="tagline">{{ $company['tagline'] }}</p>
                        @endif
                        @if ($contact || ! empty($company['address']))
                            <p class="contact">
                                {{ collect([$company['address'] ?? null, $contact])->filter()->implode(' · ') }}
                            </p>
                        @endif
                    </div>
                </div>
                <div class="doc">
                    <p class="doc-kicker">Laporan</p>
                    <p class="doc-title">Pelanggan PPPoE</p>
                    <p class="doc-meta">{{ $filterBits }}</p>
                </div>
            </div>

            <div class="accent"></div>

            <div class="summary">
                <span>Total: <strong>{{ $customers->count() }}</strong> pelanggan</span>
                <span>Urutan: <strong>Nama A → Z</strong></span>
                <span>Dicetak: <strong>{{ $fmtDateTime(now()) }}</strong></span>
            </div>

            @if ($customers->isEmpty())
                <div class="empty">Tidak ada pelanggan untuk filter tanggal ini.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th class="num">No</th>
                            <th>Pelanggan</th>
                            <th>Username</th>
                            <th>Telepon</th>
                            <th>Paket</th>
                            <th>Jatuh tempo</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($customers as $index => $customer)
                            <tr>
                                <td class="num">{{ $index + 1 }}</td>
                                <td>
                                    <span class="name">{{ $customer->name }}</span>
                                    @if ($customer->address)
                                        <span class="sub">{{ $customer->address }}</span>
                                    @endif
                                    @if ($customer->router?->name)
                                        <span class="sub">{{ $customer->router->name }}</span>
                                    @endif
                                </td>
                                <td>{{ $customer->username }}</td>
                                <td>{{ $customer->phone ?: '—' }}</td>
                                <td>{{ $customer->package?->name ?: '—' }}</td>
                                <td>
                                    {{ $fmtShort($customer->due_date) }}
                                    @if ($customer->billing_day)
                                        <span class="sub">tiap tgl {{ $customer->billing_day }}</span>
                                    @endif
                                </td>
                                <td>{{ $statusLabel[$customer->status] ?? $customer->status }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <div class="foot">
                <span>Diurutkan sesuai abjad nama pelanggan</span>
                <span>{{ $company['name'] ?: 'RT RW Net' }} · halaman cetak</span>
            </div>
        </div>
    </div>

    <script>
        if (new URLSearchParams(window.location.search).get('autoprint') === '1') {
            window.addEventListener('load', () => setTimeout(() => window.print(), 250));
        }
    </script>
</body>
</html>
