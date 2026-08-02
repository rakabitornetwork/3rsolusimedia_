@php
    $statusClass = match ($invoice->status) {
        'paid' => 'status-paid',
        'void' => 'status-void',
        default => 'status-unpaid',
    };
    $billingMonths = max(1, (int) ($invoice->billing_months ?? 1));
@endphp
<article class="inv">
    <header class="inv-top">
        <div class="inv-brand">
            @if (! empty($company['logo']))
                <img class="inv-logo" src="{{ $company['logo'] }}" alt="{{ $company['name'] }}">
            @endif
            <div class="inv-brand-text">
                <p class="inv-company">{{ $company['name'] }}</p>
                @if (! empty($company['tagline']))
                    <p class="inv-tagline">{{ $company['tagline'] }}</p>
                @endif
                @if (! empty($company['address']))
                    <p class="inv-contact">{{ $company['address'] }}</p>
                @endif
                @if ($contact)
                    <p class="inv-contact">{{ $contact }}</p>
                @endif
            </div>
        </div>
        <div class="inv-doc">
            <p class="inv-doc-kicker">Invoice</p>
            <p class="inv-doc-number">{{ $invoice->number }}</p>
            <span class="inv-status {{ $statusClass }}">{{ $statusLabel }}</span>
        </div>
    </header>

    <div class="inv-accent" aria-hidden="true"></div>

    <section class="inv-meta">
        <div class="inv-billto">
            <p class="inv-label">Ditagihkan kepada</p>
            <p class="inv-name">{{ $customer?->name ?: '—' }}</p>
            @if ($customer?->username)
                <p class="inv-sub">Akun PPPoE · {{ $customer->username }}</p>
            @endif
            @if ($customer?->phone)
                <p class="inv-sub">Telepon · {{ $customer->phone }}</p>
            @endif
            @if ($customer?->address)
                <p class="inv-sub">{{ $customer->address }}</p>
            @endif
        </div>
        <div class="inv-meta-right">
            <div class="inv-meta-row">
                <span>Jatuh tempo</span>
                <strong>{{ $fmtDate($invoice->due_date) }}</strong>
            </div>
            <div class="inv-meta-row">
                <span>Periode</span>
                <strong>{{ $fmtDate($invoice->period_start) }} — {{ $fmtDate($invoice->period_end) }}</strong>
            </div>
            <div class="inv-meta-row">
                <span>Jenis</span>
                <strong>
                    {{ $typeLabel }}
                    @if ($billingMonths > 1)
                        · {{ $billingMonths }} bulan
                    @endif
                </strong>
            </div>
        </div>
    </section>

    <table class="inv-table">
        <thead>
            <tr>
                <th>Uraian layanan</th>
                <th class="num">Nominal</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <span class="inv-item-title">{{ $invoice->package_name ?: 'Paket layanan' }}</span>
                    @if ($invoice->notes)
                        <span class="inv-item-note">{{ \Illuminate\Support\Str::limit($invoice->notes, 100) }}</span>
                    @endif
                </td>
                <td class="num">{{ $money($invoice->amount) }}</td>
            </tr>
            @if ((int) $invoice->discount > 0)
                <tr>
                    <td><span class="inv-item-title">Diskon</span></td>
                    <td class="num">− {{ $money($invoice->discount) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <section class="inv-summary">
        <div class="inv-thanks">
            <p class="inv-thanks-title">Terima kasih</p>
            <p class="inv-thanks-copy">
                Mohon lunasi sebelum jatuh tempo agar layanan tetap aktif.
                Simpan bukti pembayaran non-tunai.
            </p>
        </div>
        <div class="inv-totals">
            <div class="inv-total-row">
                <span>Subtotal</span>
                <span>{{ $money($invoice->amount) }}</span>
            </div>
            @if ((int) $invoice->discount > 0)
                <div class="inv-total-row">
                    <span>Diskon</span>
                    <span>− {{ $money($invoice->discount) }}</span>
                </div>
            @endif
            <div class="inv-total-grand">
                <span>Total tagihan</span>
                <strong>{{ $money($invoice->total) }}</strong>
            </div>
        </div>
    </section>

    <footer class="inv-foot">
        <span>Dokumen resmi {{ $company['name'] }}</span>
        <span>Dicetak {{ now()->format('d/m/Y H:i') }}</span>
    </footer>
</article>
