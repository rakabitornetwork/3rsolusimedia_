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

        html, body {
            margin: 0;
            padding: 0;
            background: #e8e8e8;
            color: #111;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            font-size: 11pt;
            line-height: 1.35;
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
            background: #111;
            color: #fff;
        }

        .toolbar p {
            margin: 0;
            font-size: 13px;
            opacity: 0.9;
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
            border: 1px solid rgba(255,255,255,0.25);
            background: transparent;
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
            color: #111;
            border-color: #fff;
        }

        .preview {
            padding: 20px 12px 40px;
        }

        .sheet {
            width: 210mm;
            height: 297mm;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            position: relative;
            overflow: hidden;
        }

        .half {
            height: 148.5mm;
            padding: 10mm 12mm 8mm;
            position: relative;
            overflow: hidden;
        }

        .half.empty {
            background: repeating-linear-gradient(
                -45deg,
                #fff,
                #fff 8px,
                #f7f7f7 8px,
                #f7f7f7 16px
            );
        }

        .cut-line {
            position: absolute;
            left: 8mm;
            right: 8mm;
            top: 148.5mm;
            border-top: 1px dashed #999;
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
            color: #777;
            font-size: 8pt;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 0 8px;
            z-index: 3;
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
            border-bottom: 2px solid #111;
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
            height: 36px;
            width: auto;
            object-fit: contain;
        }

        .brand h1 {
            margin: 0;
            font-size: 14pt;
            line-height: 1.2;
        }

        .brand .meta {
            margin-top: 2px;
            font-size: 8.5pt;
            color: #444;
        }

        .doc-title {
            text-align: right;
            flex-shrink: 0;
        }

        .doc-title .label {
            font-size: 8pt;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #666;
        }

        .doc-title .number {
            font-size: 13pt;
            font-weight: 700;
            margin-top: 2px;
        }

        .doc-title .status {
            display: inline-block;
            margin-top: 4px;
            font-size: 8pt;
            font-weight: 700;
            padding: 2px 6px;
            border: 1px solid #111;
        }

        .grid {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 10px 16px;
            margin-bottom: 10px;
        }

        .block-title {
            font-size: 8pt;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 3px;
        }

        .block-body {
            font-size: 10.5pt;
        }

        .block-body strong {
            font-size: 11.5pt;
        }

        .muted {
            color: #555;
            font-size: 9.5pt;
        }

        table.lines {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        table.lines th,
        table.lines td {
            border-top: 1px solid #ddd;
            border-bottom: 1px solid #ddd;
            padding: 7px 6px;
            text-align: left;
            vertical-align: top;
            font-size: 10pt;
        }

        table.lines th {
            background: #f3f3f3;
            font-size: 8pt;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #444;
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
            min-width: 52%;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 3px 0;
            font-size: 10pt;
        }

        .totals-row.grand {
            border-top: 2px solid #111;
            margin-top: 4px;
            padding-top: 6px;
            font-size: 13pt;
            font-weight: 700;
        }

        .footer-note {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px solid #ddd;
            font-size: 8.5pt;
            color: #555;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .empty-hint {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 10pt;
            text-align: center;
            padding: 16px;
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
                color: #bbb;
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
