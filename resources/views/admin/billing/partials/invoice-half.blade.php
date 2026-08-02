<div class="invoice-header">
    <div class="brand">
        @if (! empty($company['logo']))
            <img src="{{ $company['logo'] }}" alt="{{ $company['name'] }}">
        @endif
        <div>
            <h1>{{ $company['name'] }}</h1>
            @if (! empty($company['tagline']))
                <div class="meta">{{ $company['tagline'] }}</div>
            @endif
            @if (! empty($company['address']))
                <div class="meta">{{ $company['address'] }}</div>
            @endif
            @if ($contact)
                <div class="meta">{{ $contact }}</div>
            @endif
        </div>
    </div>
    <div class="doc-title">
        <div class="label">Invoice</div>
        <div class="number">{{ $invoice->number }}</div>
        <div class="status">{{ $statusLabel }}</div>
    </div>
</div>

<div class="grid">
    <div>
        <div class="block-title">Tagihan kepada</div>
        <div class="block-body">
            <strong>{{ $customer?->name ?: '—' }}</strong>
            @if ($customer?->username)
                <div class="muted">PPPoE: {{ $customer->username }}</div>
            @endif
            @if ($customer?->phone)
                <div class="muted">Telp: {{ $customer->phone }}</div>
            @endif
            @if ($customer?->address)
                <div class="muted">{{ $customer->address }}</div>
            @endif
        </div>
    </div>
    <div>
        <div class="block-title">Rincian jadwal</div>
        <div class="block-body">
            <div>Jatuh tempo: <strong>{{ $fmtDate($invoice->due_date) }}</strong></div>
            <div class="muted">Periode: {{ $fmtDate($invoice->period_start) }} — {{ $fmtDate($invoice->period_end) }}</div>
            <div class="muted">
                Tipe: {{ $typeLabel }}
                @php($billingMonths = max(1, (int) ($invoice->billing_months ?? 1)))
                @if ($billingMonths > 1)
                    · {{ $billingMonths }} bulan
                @endif
            </div>
        </div>
    </div>
</div>

<table class="lines">
    <thead>
        <tr>
            <th>Uraian</th>
            <th class="num">Jumlah</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>
                {{ $invoice->package_name ?: 'Paket layanan' }}
                @if ($invoice->notes)
                    <div class="muted">{{ \Illuminate\Support\Str::limit($invoice->notes, 120) }}</div>
                @endif
            </td>
            <td class="num">{{ $money($invoice->amount) }}</td>
        </tr>
        @if ((int) $invoice->discount > 0)
            <tr>
                <td>Diskon</td>
                <td class="num">- {{ $money($invoice->discount) }}</td>
            </tr>
        @endif
    </tbody>
</table>

<div class="totals">
    <div class="totals-box">
        <div class="totals-row">
            <span>Subtotal</span>
            <span>{{ $money($invoice->amount) }}</span>
        </div>
        @if ((int) $invoice->discount > 0)
            <div class="totals-row">
                <span>Diskon</span>
                <span>- {{ $money($invoice->discount) }}</span>
            </div>
        @endif
        <div class="totals-row grand">
            <span>Total</span>
            <span>{{ $money($invoice->total) }}</span>
        </div>
    </div>
</div>

<div class="footer-note">
    <div>
        Harap lunasi sebelum jatuh tempo agar layanan tetap aktif.
        Simpan bukti transfer bila pembayaran non-tunai.
    </div>
    <div style="text-align:right">
        Dicetak {{ now()->format('d/m/Y H:i') }}
    </div>
</div>
