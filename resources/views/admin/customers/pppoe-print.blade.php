@php
    /** @var \Illuminate\Support\Collection<int, array> $rows */
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
    $money = fn (?int $n) => $n !== null
        ? number_format($n, 0, ',', '.')
        : '—';
    $customerStatus = [
        'active' => 'Aktif',
        'isolated' => 'Isolir',
        'disabled' => 'Nonaktif',
    ];
    $filterBits = $filter_bits ?? collect([
        ($date_field_label ?? 'Tanggal').': '.$fmtDate($date ?? now()),
        ($date_field ?? '') === 'billing_day' ? 'tiap tgl '.($date ?? now())->day : null,
        $router?->name ? 'Router '.$router->name : null,
        $status ? ($customerStatus[$status] ?? ($status === 'grace' ? 'Grace' : $status)) : null,
    ])->filter()->implode(' · ');
    $listTitle = $list_title ?? 'Daftar Tagihan Pelanggan PPPoE';
    $pageTitle = $page_title ?? ('Cetak Tagihan PPPoE · '.$fmtShort($date ?? now()));
    $backUrl = $back_url ?? route('admin.customers.pppoe');
    $emptyMessage = $empty_message ?? 'Tidak ada pelanggan untuk filter tanggal ini.';
    $agentMarks = (bool) ($agent_marks ?? false);
    $tfColumnLabel = $agentMarks ? 'Siap TF' : 'TF';
    $cashCount = $agentMarks
        ? $rows->filter(fn ($row) => ! empty($row['agent_cash']))->count()
        : 0;
    $readyTfCount = $agentMarks
        ? $rows->filter(fn ($row) => ! empty($row['agent_ready_tf']))->count()
        : 0;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 6mm 5mm 7mm;
        }

        * { box-sizing: border-box; }

        :root {
            --ink: #111;
            --muted: #444;
            --line: #222;
            --soft: #888;
            --paper: #fff;
            --zebra: #f4f4f4;
        }

        html, body {
            margin: 0;
            padding: 0;
            background: #cfd6de;
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8pt;
            line-height: 1.2;
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
            padding: 10px 14px;
            background: #0b1526;
            color: #fff;
        }

        .toolbar p {
            margin: 0;
            font-size: 12px;
            color: rgba(255,255,255,0.85);
        }

        .toolbar-actions {
            display: flex;
            gap: 8px;
        }

        .toolbar a,
        .toolbar button {
            appearance: none;
            border: 1px solid rgba(255,255,255,0.25);
            background: transparent;
            color: #fff;
            padding: 7px 11px;
            font-size: 12px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
        }

        .toolbar button.primary {
            background: #1a6eff;
            border-color: #1a6eff;
        }

        .preview { padding: 16px 10px 40px; }

        .sheet {
            width: 200mm;
            margin: 0 auto;
            background: var(--paper);
            box-shadow: 0 12px 36px rgba(0,0,0,0.14);
            padding: 5mm 4mm 6mm;
        }

        .head {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 8px;
            margin-bottom: 3mm;
            padding-bottom: 2mm;
            border-bottom: 1.5px solid var(--line);
        }

        .head-left {
            min-width: 0;
        }

        .company {
            margin: 0;
            font-size: 11pt;
            font-weight: 700;
            letter-spacing: -0.01em;
            line-height: 1.1;
        }

        .title {
            margin: 1px 0 0;
            font-size: 8.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .meta {
            margin: 1px 0 0;
            font-size: 7pt;
            color: var(--muted);
        }

        .head-right {
            text-align: right;
            flex-shrink: 0;
            font-size: 7pt;
            color: var(--muted);
            line-height: 1.35;
        }

        .head-right strong {
            color: var(--ink);
            font-size: 8pt;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th, td {
            border: 0.6pt solid var(--line);
            padding: 2.5px 3px;
            vertical-align: middle;
            overflow: hidden;
        }

        th {
            background: #e8e8e8;
            font-size: 6.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            text-align: center;
            line-height: 1.15;
            padding: 3px 2px;
        }

        td {
            font-size: 7.5pt;
        }

        tbody tr:nth-child(even) td {
            background: var(--zebra);
        }

        .c-no { width: 4%; text-align: center; font-variant-numeric: tabular-nums; color: var(--muted); }
        .c-name { width: 34%; }
        .c-amt { width: 14%; text-align: right; font-variant-numeric: tabular-nums; font-weight: 700; white-space: nowrap; }
        .c-due { width: 11%; text-align: center; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .c-ket { width: 21%; }
        .c-pay { width: 8%; text-align: center; padding: 2px 1px; }

        .name {
            font-weight: 700;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sub {
            display: block;
            font-size: 6.5pt;
            color: var(--soft);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ket {
            min-height: 12px;
        }

        .box {
            display: inline-block;
            width: 8px;
            height: 8px;
            border: 0.8pt solid var(--ink);
            vertical-align: middle;
            background: #fff;
        }

        .box.is-checked {
            background: var(--ink);
            border-color: var(--ink);
        }

        .empty {
            padding: 24px 8px;
            text-align: center;
            color: var(--muted);
            font-size: 10pt;
            border: 0.6pt solid var(--line);
        }

        .foot {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-top: 3mm;
            font-size: 7pt;
            color: var(--muted);
        }

        .totals {
            border: 0.6pt solid var(--line);
            padding: 3px 6px;
            font-size: 7.5pt;
            color: var(--ink);
            background: #e8e8e8;
        }

        .totals strong {
            font-variant-numeric: tabular-nums;
        }

        .sign {
            display: flex;
            gap: 18px;
            margin-top: 2mm;
        }

        .sign div {
            min-width: 55mm;
            text-align: center;
        }

        .sign .line {
            margin-top: 14mm;
            border-top: 0.6pt solid var(--line);
            padding-top: 2px;
            font-size: 6.5pt;
        }

        @media print {
            html, body { background: #fff; }
            .toolbar, .no-print { display: none !important; }
            .preview { padding: 0; }
            .sheet {
                box-shadow: none;
                width: auto;
                padding: 0;
            }
            tbody tr:nth-child(even) td {
                background: #f2f2f2 !important;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <p>
            Lembar tagihan high-density · A → Z ·
            <strong>{{ $rows->count() }}</strong> pelanggan
        </p>
        <div class="toolbar-actions">
            <button type="button" class="primary" onclick="window.print()">Cetak</button>
            <a href="{{ $backUrl }}">Kembali</a>
        </div>
    </div>

    <div class="preview">
        <div class="sheet">
            <div class="head">
                <div class="head-left">
                    <p class="company">{{ $company['name'] ?: 'RT RW Net' }}</p>
                    <p class="title">{{ $listTitle }}</p>
                    <p class="meta">{{ $filterBits }} · urut nama A–Z</p>
                </div>
                <div class="head-right">
                    Dicetak {{ now()->format('d/m/Y H:i') }}<br>
                    Total baris: <strong>{{ $rows->count() }}</strong>
                </div>
            </div>

            @if ($rows->isEmpty())
                <div class="empty">{{ $emptyMessage }}</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th class="c-no">No</th>
                            <th class="c-name">Pelanggan</th>
                            <th class="c-amt">Juml. Tagihan</th>
                            <th class="c-due">Jth Tempo</th>
                            <th class="c-ket">Ket</th>
                            <th class="c-pay">Cash</th>
                            <th class="c-pay">{{ $tfColumnLabel }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $index => $row)
                            @php
                                /** @var \App\Models\PppoeCustomer $customer */
                                $customer = $row['customer'];
                            @endphp
                            <tr>
                                <td class="c-no">{{ $index + 1 }}</td>
                                <td class="c-name">
                                    <span class="name">{{ $customer->name }}</span>
                                    <span class="sub">
                                        {{ $customer->username }}
                                        @if ($customer->phone)
                                            · {{ $customer->phone }}
                                        @endif
                                    </span>
                                </td>
                                <td class="c-amt">{{ $money($row['amount'] !== null ? (int) $row['amount'] : null) }}</td>
                                <td class="c-due">{{ $fmtShort($row['due_date']) }}</td>
                                <td class="c-ket"><div class="ket"></div></td>
                                <td class="c-pay"><span class="box{{ $agentMarks && ! empty($row['agent_cash']) ? ' is-checked' : '' }}"></span></td>
                                <td class="c-pay"><span class="box{{ $agentMarks && ! empty($row['agent_ready_tf']) ? ' is-checked' : '' }}"></span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="foot">
                    <div>
                        <div class="totals">
                            Jumlah pelanggan: <strong>{{ $rows->count() }}</strong>
                            &nbsp;·&nbsp;
                            Total tagihan: <strong>Rp {{ $money((int) $total_amount) }}</strong>
                        </div>
                        <p style="margin: 3px 0 0;">
                            @if ($agentMarks)
                                Kotak Cash / Siap TF terisi sesuai tanda di halaman Tagihan & Pembayaran
                                (Cash {{ $cashCount }}, Siap TF {{ $readyTfCount }}).
                            @else
                                Kolom Ket, Cash, dan TF dikosongkan untuk diisi saat penagihan.
                            @endif
                        </p>
                    </div>
                    <div class="sign">
                        <div>
                            Petugas
                            <div class="line">( ........................ )</div>
                        </div>
                        <div>
                            Kasir / Admin
                            <div class="line">( ........................ )</div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <script>
        if (new URLSearchParams(window.location.search).get('autoprint') === '1') {
            window.addEventListener('load', () => setTimeout(() => window.print(), 250));
        }
    </script>
</body>
</html>
