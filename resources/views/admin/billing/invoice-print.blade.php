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
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        :root {
            --ink: #0f172a;
            --ink-soft: #334155;
            --muted: #64748b;
            --line: #cbd5e1;
            --paper: #ffffff;
            --mist: #f0fdfa;
            --signal: #0d9488;
            --signal-deep: #0f5c5a;
            --signal-bright: #14b8a6;
            --amber: #d97706;
            --amber-bg: #fff7ed;
            --green: #047857;
            --green-bg: #ecfdf5;
            --red: #b91c1c;
            --red-bg: #fef2f2;
        }

        html, body {
            margin: 0;
            padding: 0;
            background: #dbe7e6;
            color: var(--ink);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            font-size: 11pt;
            line-height: 1.35;
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
            padding: 12px 16px;
            background: linear-gradient(135deg, var(--signal-deep), var(--signal));
            color: #fff;
        }

        .toolbar p {
            margin: 0;
            font-size: 13px;
            opacity: 0.95;
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
            border: 1px solid rgba(255,255,255,0.35);
            background: rgba(255,255,255,0.08);
            color: #fff;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }

        .toolbar a.active,
        .toolbar button.primary {
            background: #fff;
            color: var(--signal-deep);
            border-color: #fff;
        }

        .preview {
            padding: 20px 12px 40px;
        }

        .sheet {
            width: 210mm;
            height: 297mm;
            margin: 0 auto;
            background: var(--paper);
            box-shadow: 0 10px 36px rgba(15, 92, 90, 0.18);
            position: relative;
            overflow: hidden;
        }

        .half {
            height: 148.5mm;
            padding: 8mm 11mm 7mm;
            position: relative;
            overflow: hidden;
        }

        .half.empty {
            background: repeating-linear-gradient(
                -45deg,
                #fff,
                #fff 8px,
                #f0fdfa 8px,
                #f0fdfa 16px
            );
        }

        .cut-line {
            position: absolute;
            left: 8mm;
            right: 8mm;
            top: 148.5mm;
            border-top: 1.5px dashed var(--signal);
            transform: translateY(-50%);
            z-index: 2;
            pointer-events: none;
            opacity: 0.55;
        }

        .cut-label {
            position: absolute;
            left: 50%;
            top: 148.5mm;
            transform: translate(-50%, -50%);
            background: #fff;
            color: var(--signal-deep);
            font-size: 8pt;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 0 8px;
            z-index: 3;
        }

        .invoice-card {
            height: 100%;
            border: 1.5px solid color-mix(in srgb, var(--signal) 45%, #94a3b8);
            background:
                linear-gradient(180deg, rgba(13,148,136,0.08) 0%, rgba(13,148,136,0.02) 42px, #fff 42px);
            display: flex;
            flex-direction: column;
            padding: 7mm 8mm 6mm;
            position: relative;
            overflow: hidden;
        }

        .invoice-card::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4.5px;
            background: linear-gradient(180deg, var(--signal-bright), var(--signal-deep));
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
            border-bottom: 2.5px solid var(--signal-deep);
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .brand {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            min-width: 0;
        }

        .brand img {
            height: 38px;
            width: auto;
            object-fit: contain;
        }

        .brand h1 {
            margin: 0;
            font-size: 14.5pt;
            line-height: 1.15;
            color: var(--signal-deep);
        }

        .brand .meta {
            margin-top: 2px;
            font-size: 8.5pt;
            color: var(--ink-soft);
        }

        .doc-title {
            text-align: right;
            flex-shrink: 0;
        }

        .doc-title .label {
            font-size: 8pt;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--signal);
            font-weight: 700;
        }

        .doc-title .number {
            font-size: 13pt;
            font-weight: 800;
            margin-top: 2px;
            color: var(--ink);
        }

        .doc-title .status {
            display: inline-block;
            margin-top: 5px;
            font-size: 8pt;
            font-weight: 800;
            padding: 3px 8px;
            border: 1px solid transparent;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .status-unpaid {
            background: var(--amber-bg);
            color: var(--amber);
            border-color: #fdba74;
        }

        .status-paid {
            background: var(--green-bg);
            color: var(--green);
            border-color: #6ee7b7;
        }

        .status-void {
            background: var(--red-bg);
            color: var(--red);
            border-color: #fca5a5;
        }

        .grid {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 10px 14px;
            margin-bottom: 10px;
        }

        .info-box {
            background: var(--mist);
            border: 1px solid color-mix(in srgb, var(--signal) 28%, #e2e8f0);
            padding: 7px 9px;
        }

        .block-title {
            font-size: 8pt;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--signal-deep);
            font-weight: 800;
            margin-bottom: 4px;
        }

        .block-body {
            font-size: 10.5pt;
            color: var(--ink);
        }

        .block-body strong {
            font-size: 11.5pt;
            color: var(--ink);
        }

        .muted {
            color: var(--ink-soft);
            font-size: 9.5pt;
        }

        table.lines {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2px;
        }

        table.lines th,
        table.lines td {
            border-bottom: 1px solid var(--line);
            padding: 7px 8px;
            text-align: left;
            vertical-align: top;
            font-size: 10pt;
        }

        table.lines th {
            background: linear-gradient(135deg, var(--signal-deep), var(--signal));
            color: #fff;
            font-size: 8pt;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            border-bottom: none;
        }

        table.lines tbody tr:nth-child(even) td {
            background: #f8fffe;
        }

        table.lines td.num,
        table.lines th.num {
            text-align: right;
            white-space: nowrap;
        }

        .totals {
            margin-top: 8px;
            display: flex;
            justify-content: flex-end;
        }

        .totals-box {
            min-width: 54%;
            border: 1px solid color-mix(in srgb, var(--signal) 30%, #e2e8f0);
            background: #f8fffe;
            padding: 6px 10px;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 3px 0;
            font-size: 10pt;
            color: var(--ink-soft);
        }

        .totals-row.grand {
            margin-top: 4px;
            padding: 7px 8px;
            font-size: 13pt;
            font-weight: 800;
            color: #fff;
            background: linear-gradient(135deg, var(--signal-deep), var(--signal));
        }

        .footer-note {
            margin-top: auto;
            padding-top: 8px;
            border-top: 2px solid color-mix(in srgb, var(--signal) 35%, #e2e8f0);
            font-size: 8.5pt;
            color: var(--ink-soft);
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .footer-note strong {
            color: var(--signal-deep);
        }

        .empty-hint {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--signal-deep);
            font-size: 10pt;
            text-align: center;
            padding: 16px;
            opacity: 0.55;
        }

        @media print {
            html, body {
                background: #fff;
            }

            .toolbar,
            .no-print {
                display: none !important;
            }

            .preview {
                padding: 0;
            }

            .sheet {
                box-shadow: none;
                width: 210mm;
                height: 297mm;
            }

            .half.empty {
                background: #fff;
            }

            .empty-hint {
                display: none;
            }

            .cut-label {
                color: var(--signal);
            }
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
                    <div class="empty-hint">Kosong — siap dipakai cetak invoice berikutnya<br>(pilih “Setengah atas”)</div>
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
                    <div class="empty-hint">Kosong — siap dipakai cetak invoice berikutnya<br>(pilih “Setengah bawah”)</div>
                @endif
            </div>
        </div>
    </div>

    <script>
        // Opsional: cetak otomatis jika ?autoprint=1
        if (new URLSearchParams(window.location.search).get('autoprint') === '1') {
            window.addEventListener('load', () => setTimeout(() => window.print(), 250));
        }
    </script>
</body>
</html>
