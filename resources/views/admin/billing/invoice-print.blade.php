@php
    /** @var \App\Models\Invoice $invoice */
    $customer = $invoice->customer;
    $typeLabel = match ($invoice->type) {
        'prorata' => 'Prorata',
        'monthly' => 'Bulanan',
        'multi_month' => 'Gabungan '.((int) ($invoice->billing_months ?: 2)).' bulan',
        'adjustment' => 'Penyesuaian',
        default => $invoice->type,
    };
    $statusLabel = match ($invoice->status) {
        'unpaid' => 'Belum bayar',
        'paid' => 'Lunas',
        'void' => 'Dibatalkan',
        default => $invoice->status,
    };
    $money = fn (?int $n) => 'Rp '.number_format((int) $n, 0, ',', '.');
    $fmtDate = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : '—';
    $contact = collect([
        $company['phone'] ?? null,
        ! empty($company['whatsapp']) ? 'WA '.$company['whatsapp'] : null,
    ])->filter()->implode(' · ');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak {{ $invoice->number }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Manrope:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Syne:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }

        * { box-sizing: border-box; }

        :root {
            --ink: #0b1526;
            --ink-soft: #3a4658;
            --muted: #6b7789;
            --paper: #ffffff;
            --mist: #f3f6fa;
            --line-soft: #e6ebf2;
            --signal: #1a6eff;
            --signal-deep: #0a2d82;
            --signal-bright: #00b7ff;
            --amber: #b45309;
            --amber-bg: #fff7ed;
            --green: #047857;
            --green-bg: #ecfdf5;
            --red: #b91c1c;
            --red-bg: #fef2f2;
            --font-body: "Manrope", "Segoe UI", system-ui, sans-serif;
            --font-display: "Syne", "Manrope", system-ui, sans-serif;
            --font-serif: "Instrument Serif", Georgia, serif;
        }

        html, body {
            margin: 0;
            padding: 0;
            background: #d5dee6;
            color: var(--ink);
            font-family: var(--font-body);
            font-size: 10.5pt;
            line-height: 1.45;
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

        .toolbar a.active,
        .toolbar button.primary {
            background: var(--signal);
            border-color: var(--signal);
            color: #fff;
        }

        .preview { padding: 22px 12px 48px; }

        .sheet {
            width: 210mm;
            height: 297mm;
            margin: 0 auto;
            background: var(--paper);
            box-shadow: 0 18px 50px rgba(11, 21, 38, 0.16);
            position: relative;
            overflow: hidden;
        }

        .half {
            height: 148.5mm;
            padding: 7mm 10mm 6mm;
            position: relative;
            overflow: hidden;
        }

        .half.empty {
            background:
                linear-gradient(180deg, rgba(11,21,38,0.02), transparent 40%),
                repeating-linear-gradient(-45deg, #fff, #fff 10px, #f7fafb 10px, #f7fafb 20px);
        }

        .cut-line {
            position: absolute;
            left: 10mm;
            right: 10mm;
            top: 148.5mm;
            border-top: 1px dashed rgba(26, 110, 255, 0.35);
            transform: translateY(-50%);
            z-index: 2;
            pointer-events: none;
        }

        .cut-label {
            position: absolute;
            left: 50%;
            top: 148.5mm;
            transform: translate(-50%, -50%);
            background: #fff;
            color: var(--signal-deep);
            font-family: var(--font-display);
            font-size: 7pt;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            padding: 0 10px;
            z-index: 3;
        }

        /* —— Invoice —— */
        .inv {
            height: 100%;
            display: flex;
            flex-direction: column;
            background: var(--paper);
            position: relative;
            overflow: hidden;
            padding: 0;
        }

        .inv-top {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: flex-start;
            padding: 5.5mm 6.5mm 4.5mm;
            position: relative;
            z-index: 1;
        }

        .inv-brand {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            min-width: 0;
        }

        .inv-logo {
            height: 42px;
            width: 42px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .inv-company {
            margin: 0;
            font-family: var(--font-display);
            font-size: 16.5pt;
            font-weight: 700;
            line-height: 1.05;
            letter-spacing: -0.02em;
            color: var(--ink);
        }

        .inv-tagline {
            margin: 3px 0 0;
            font-size: 8.5pt;
            font-weight: 500;
            color: var(--signal-deep);
        }

        .inv-contact {
            margin: 2px 0 0;
            font-size: 8pt;
            color: var(--muted);
            max-width: 98mm;
        }

        .inv-doc {
            text-align: right;
            flex-shrink: 0;
        }

        .inv-doc-kicker {
            margin: 0;
            font-family: var(--font-display);
            font-size: 8pt;
            font-weight: 700;
            letter-spacing: 0.24em;
            text-transform: uppercase;
            color: var(--signal);
        }

        .inv-doc-number {
            margin: 4px 0 0;
            font-family: var(--font-display);
            font-size: 12pt;
            font-weight: 700;
            line-height: 1.15;
            letter-spacing: 0.02em;
            color: var(--ink);
        }

        .inv-status {
            display: inline-block;
            margin-top: 7px;
            font-family: var(--font-display);
            font-size: 7pt;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            padding: 4px 9px;
        }

        .status-unpaid {
            background: var(--amber-bg);
            color: var(--amber);
        }

        .status-paid {
            background: var(--green-bg);
            color: var(--green);
        }

        .status-void {
            background: var(--red-bg);
            color: var(--red);
        }

        .inv-accent {
            height: 2.5px;
            margin: 0 6.5mm;
            background: linear-gradient(90deg, var(--signal-deep) 0%, var(--signal) 55%, var(--signal-bright) 100%);
            border-radius: 999px;
        }

        .inv-meta {
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            gap: 12px 16px;
            padding: 5mm 6.5mm 4mm;
            position: relative;
            z-index: 1;
        }

        .inv-label {
            margin: 0 0 4px;
            font-family: var(--font-display);
            font-size: 7pt;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--signal);
        }

        .inv-name {
            margin: 0;
            font-family: var(--font-display);
            font-size: 12.5pt;
            font-weight: 700;
            letter-spacing: -0.01em;
            color: var(--ink);
        }

        .inv-sub {
            margin: 2px 0 0;
            font-size: 8.5pt;
            color: var(--ink-soft);
        }

        .inv-meta-right {
            background: var(--mist);
            padding: 7px 10px;
            border-left: 3px solid var(--signal);
        }

        .inv-meta-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: baseline;
            padding: 3.5px 0;
            font-size: 8.5pt;
        }

        .inv-meta-row + .inv-meta-row {
            border-top: 1px solid rgba(230, 235, 242, 0.95);
        }

        .inv-meta-row span {
            font-family: var(--font-display);
            font-size: 7pt;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--signal-deep);
            flex-shrink: 0;
        }

        .inv-meta-row strong {
            text-align: right;
            font-weight: 600;
            color: var(--ink);
        }

        .inv-table {
            width: calc(100% - 13mm);
            margin: 0 6.5mm;
            border-collapse: collapse;
            position: relative;
            z-index: 1;
        }

        .inv-table th,
        .inv-table td {
            padding: 7px 8px;
            text-align: left;
            vertical-align: top;
        }

        .inv-table th {
            background: transparent;
            color: var(--signal-deep);
            font-family: var(--font-display);
            font-size: 7pt;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            border-bottom: 1.5px solid var(--signal-deep);
            padding-bottom: 7px;
        }

        .inv-table td {
            border-bottom: 1px solid var(--line-soft);
            font-size: 10pt;
        }

        .inv-table tbody tr:last-child td {
            border-bottom: none;
        }

        .inv-table .num {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
            font-weight: 700;
            color: var(--ink);
        }

        .inv-item-title {
            display: block;
            font-weight: 700;
            color: var(--ink);
        }

        .inv-item-note {
            display: block;
            margin-top: 2px;
            font-size: 8.5pt;
            color: var(--muted);
        }

        .inv-summary {
            display: grid;
            grid-template-columns: 1.1fr 0.95fr;
            gap: 12px;
            padding: 4mm 6.5mm 3mm;
            margin-top: auto;
            position: relative;
            z-index: 1;
        }

        .inv-thanks-title {
            margin: 0;
            font-family: var(--font-serif);
            font-size: 16pt;
            font-weight: 400;
            font-style: italic;
            color: var(--signal-deep);
        }

        .inv-thanks-copy {
            margin: 4px 0 0;
            font-size: 8pt;
            line-height: 1.5;
            color: var(--muted);
            max-width: 78mm;
        }

        .inv-totals {
            background: var(--mist);
            padding: 6px 0 0;
        }

        .inv-total-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 3px 11px;
            font-size: 9pt;
            color: var(--ink-soft);
        }

        .inv-total-grand {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 12px;
            margin-top: 5px;
            padding: 9px 11px;
            background: linear-gradient(135deg, var(--signal-deep) 0%, #0f47b8 52%, var(--signal) 100%);
            color: #fff;
        }

        .inv-total-grand span {
            font-family: var(--font-display);
            font-size: 7.5pt;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            opacity: 0.92;
        }

        .inv-total-grand strong {
            font-family: var(--font-serif);
            font-size: 17pt;
            font-weight: 400;
            letter-spacing: -0.01em;
        }

        .inv-foot {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin: 0 6.5mm 4.5mm;
            padding-top: 3mm;
            border-top: 1px solid var(--line-soft);
            font-size: 7.5pt;
            color: var(--muted);
            letter-spacing: 0.02em;
            position: relative;
            z-index: 1;
        }

        .empty-hint {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            font-size: 10pt;
            text-align: center;
            padding: 16px;
        }

        @media print {
            html, body { background: #fff; }

            .toolbar,
            .no-print { display: none !important; }

            .preview { padding: 0; }

            .sheet {
                box-shadow: none;
                width: 210mm;
                height: 297mm;
            }

            .half.empty { background: #fff; }
            .empty-hint { display: none; }
            .cut-label { color: var(--signal-deep); }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <p>
            Cetak setengah A4 (portrait) · posisi
            <strong>{{ $half === 'bottom' ? 'bawah' : 'atas' }}</strong>
            — sisa kertas untuk invoice berikutnya.
        </p>
        <div class="toolbar-actions">
            <a href="{{ route('admin.billing.print', ['invoice' => $invoice, 'half' => 'top']) }}"
               class="{{ $half === 'top' ? 'active' : '' }}">Setengah atas</a>
            <a href="{{ route('admin.billing.print', ['invoice' => $invoice, 'half' => 'bottom']) }}"
               class="{{ $half === 'bottom' ? 'active' : '' }}">Setengah bawah</a>
            <button type="button" class="primary" onclick="window.print()">Cetak</button>
            <a href="{{ route('admin.billing.show', $invoice) }}">Kembali</a>
        </div>
    </div>

    <div class="preview">
        <div class="sheet">
            <div class="cut-line"></div>
            <div class="cut-label no-print">garis potong / lipat</div>

            <div class="half {{ $half === 'top' ? '' : 'empty' }}">
                @if ($half === 'top')
                    @include('admin.billing.partials.invoice-half', [
                        'invoice' => $invoice,
                        'customer' => $customer,
                        'company' => $company,
                        'typeLabel' => $typeLabel,
                        'statusLabel' => $statusLabel,
                        'money' => $money,
                        'fmtDate' => $fmtDate,
                        'contact' => $contact,
                    ])
                @else
                    <div class="empty-hint">Area kosong · siap untuk invoice berikutnya<br>(pilih “Setengah atas”)</div>
                @endif
            </div>

            <div class="half {{ $half === 'bottom' ? '' : 'empty' }}">
                @if ($half === 'bottom')
                    @include('admin.billing.partials.invoice-half', [
                        'invoice' => $invoice,
                        'customer' => $customer,
                        'company' => $company,
                        'typeLabel' => $typeLabel,
                        'statusLabel' => $statusLabel,
                        'money' => $money,
                        'fmtDate' => $fmtDate,
                        'contact' => $contact,
                    ])
                @else
                    <div class="empty-hint">Area kosong · siap untuk invoice berikutnya<br>(pilih “Setengah bawah”)</div>
                @endif
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
