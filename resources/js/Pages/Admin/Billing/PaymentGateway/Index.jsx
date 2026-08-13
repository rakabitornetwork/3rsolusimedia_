import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Copy, ExternalLink, Wallet, Wifi } from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '../../../../Layouts/AdminLayout';

const fieldClass =
    'mt-1.5 w-full border border-ink/15 bg-white px-3 py-2.5 text-sm outline-none focus:border-signal disabled:bg-mist';

const GATEWAYS = [
    {
        key: 'xendit',
        label: 'Xendit',
        blurb: 'Invoice API — QRIS, VA, e-wallet, retail.',
        secretFields: [
            { name: 'xendit_secret_key', label: 'Secret key', placeholder: 'xnd_…', hasKey: 'has_secret_key' },
            {
                name: 'xendit_callback_token',
                label: 'Callback verification token',
                placeholder: 'Token dari dashboard Xendit',
                hasKey: 'has_callback_token',
            },
        ],
    },
    {
        key: 'midtrans',
        label: 'Midtrans',
        blurb: 'Payment Link — Snap / berbagai metode lokal.',
        secretFields: [
            {
                name: 'midtrans_server_key',
                label: 'Server key',
                placeholder: 'SB-Mid-server-…',
                hasKey: 'has_server_key',
            },
        ],
        publicFields: [
            { name: 'midtrans_client_key', label: 'Client key', placeholder: 'SB-Mid-client-…' },
        ],
    },
    {
        key: 'duitku',
        label: 'Duitku',
        blurb: 'Payment gateway Indonesia — VA, retail, e-wallet.',
        secretFields: [
            { name: 'duitku_api_key', label: 'API key', placeholder: 'API key merchant', hasKey: 'has_api_key' },
        ],
        publicFields: [
            { name: 'duitku_merchant_code', label: 'Merchant code', placeholder: 'Kode merchant' },
        ],
    },
];

export default function Index({ config, webhook_urls, portal_url, enabled_gateways }) {
    const { auth } = usePage().props;
    const canWrite = auth?.user?.can_write !== false;
    const [copied, setCopied] = useState('');

    const { data, setData, post, processing, errors, transform } = useForm({
        pg_default: config?.default || 'xendit',
        xendit_enabled: Boolean(config?.xendit?.enabled),
        xendit_mode: config?.xendit?.mode || 'sandbox',
        xendit_secret_key: '',
        xendit_callback_token: '',
        midtrans_enabled: Boolean(config?.midtrans?.enabled),
        midtrans_mode: config?.midtrans?.mode || 'sandbox',
        midtrans_server_key: '',
        midtrans_client_key: config?.midtrans?.client_key || '',
        duitku_enabled: Boolean(config?.duitku?.enabled),
        duitku_mode: config?.duitku?.mode || 'sandbox',
        duitku_merchant_code: config?.duitku?.merchant_code || '',
        duitku_api_key: '',
    });

    const save = (e) => {
        e.preventDefault();
        if (!canWrite) return;

        transform((form) => {
            const payload = { ...form };
            [
                'xendit_secret_key',
                'xendit_callback_token',
                'midtrans_server_key',
                'duitku_api_key',
            ].forEach((key) => {
                if (!payload[key]) delete payload[key];
            });
            return payload;
        });

        post('/admin/billing/payment-gateway');
    };

    const copyText = async (label, value) => {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(label);
            setTimeout(() => setCopied(''), 2000);
        } catch {
            window.prompt('Salin URL ini:', value);
        }
    };

    const testGateway = (gateway) => {
        if (!canWrite) return;
        router.post('/admin/billing/payment-gateway/test', { gateway });
    };

    return (
        <AdminLayout
            title="Payment Gateway"
            subtitle="Integrasi Xendit, Midtrans, dan Duitku untuk pembayaran online"
        >
            <Head title="Payment Gateway" />

            <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <p className="max-w-2xl text-sm text-ink-soft">
                    Aktifkan salah satu atau lebih gateway Indonesia. Gateway default dipakai saat
                    membuat link pembayaran dari tagihan atau portal pelanggan{' '}
                    <a href={portal_url} target="_blank" rel="noreferrer" className="font-semibold text-signal-deep hover:underline">
                        {portal_url}
                    </a>
                    .
                </p>
                <div className="text-xs text-ink-soft">
                    Aktif:{' '}
                    {enabled_gateways?.length
                        ? enabled_gateways.map((g) => g.toUpperCase()).join(', ')
                        : 'belum ada'}
                </div>
            </div>

            <form onSubmit={save} className="space-y-5">
                <div className="border border-ink/10 bg-white p-6">
                    <h2 className="text-sm font-semibold text-ink">Gateway default</h2>
                    <p className="mt-1 text-sm text-ink-soft">
                        Dipakai otomatis saat tombol bayar online / portal pelanggan.
                    </p>
                    <select
                        value={data.pg_default}
                        onChange={(e) => setData('pg_default', e.target.value)}
                        disabled={!canWrite}
                        className={`mt-3 max-w-xs ${fieldClass}`}
                    >
                        {GATEWAYS.map((g) => (
                            <option key={g.key} value={g.key}>
                                {g.label}
                            </option>
                        ))}
                    </select>
                    {errors.pg_default && (
                        <p className="mt-1 text-xs text-red-600">{errors.pg_default}</p>
                    )}
                </div>

                {GATEWAYS.map((gateway) => {
                    const cfg = config?.[gateway.key] || {};
                    const enabledKey = `${gateway.key}_enabled`;
                    const modeKey = `${gateway.key}_mode`;

                    return (
                        <div key={gateway.key} className="border border-ink/10 bg-white p-6">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="flex items-start gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center bg-signal/10 text-signal-deep">
                                        <Wallet className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h2 className="text-sm font-semibold text-ink">{gateway.label}</h2>
                                        <p className="mt-1 text-sm text-ink-soft">{gateway.blurb}</p>
                                    </div>
                                </div>
                                <label className="inline-flex items-center gap-2 text-sm font-medium text-ink">
                                    <input
                                        type="checkbox"
                                        checked={Boolean(data[enabledKey])}
                                        onChange={(e) => setData(enabledKey, e.target.checked)}
                                        disabled={!canWrite}
                                        className="h-4 w-4 border-ink/30 text-signal focus:ring-signal"
                                    />
                                    Aktifkan
                                </label>
                            </div>

                            <div className="mt-5 grid gap-4 sm:grid-cols-2">
                                <label className="block text-sm font-medium text-ink">
                                    Mode
                                    <select
                                        value={data[modeKey]}
                                        onChange={(e) => setData(modeKey, e.target.value)}
                                        disabled={!canWrite}
                                        className={fieldClass}
                                    >
                                        <option value="sandbox">Sandbox</option>
                                        <option value="live">Live</option>
                                    </select>
                                </label>

                                {(gateway.publicFields || []).map((field) => (
                                    <label key={field.name} className="block text-sm font-medium text-ink">
                                        {field.label}
                                        <input
                                            type="text"
                                            value={data[field.name] || ''}
                                            onChange={(e) => setData(field.name, e.target.value)}
                                            disabled={!canWrite}
                                            className={fieldClass}
                                            placeholder={field.placeholder}
                                            autoComplete="off"
                                        />
                                    </label>
                                ))}

                                {gateway.secretFields.map((field) => (
                                    <label key={field.name} className="block text-sm font-medium text-ink">
                                        {field.label}
                                        {cfg[field.hasKey] ? (
                                            <span className="ml-2 text-xs font-normal text-emerald-700">
                                                sudah tersimpan
                                            </span>
                                        ) : null}
                                        <input
                                            type="password"
                                            value={data[field.name] || ''}
                                            onChange={(e) => setData(field.name, e.target.value)}
                                            disabled={!canWrite}
                                            className={fieldClass}
                                            placeholder={
                                                cfg[field.hasKey]
                                                    ? 'Kosongkan jika tidak diganti'
                                                    : field.placeholder
                                            }
                                            autoComplete="new-password"
                                        />
                                    </label>
                                ))}
                            </div>

                            <div className="mt-5 flex flex-wrap items-center gap-2 border-t border-ink/10 pt-4">
                                <div className="min-w-0 flex-1">
                                    <p className="text-xs tracking-wide text-ink-soft uppercase">
                                        Webhook URL
                                    </p>
                                    <p className="mt-1 truncate font-mono text-xs text-ink">
                                        {webhook_urls?.[gateway.key]}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() =>
                                        copyText(gateway.key, webhook_urls?.[gateway.key] || '')
                                    }
                                    className="btn-action btn-action-xs btn-secondary"
                                >
                                    {copied === gateway.key ? (
                                        <CheckCircle2 className="mr-1.5 h-3.5 w-3.5 text-emerald-600" />
                                    ) : (
                                        <Copy className="mr-1.5 h-3.5 w-3.5 text-slate-600" />
                                    )}
                                    Salin
                                </button>
                                {canWrite && (
                                    <button
                                        type="button"
                                        onClick={() => testGateway(gateway.key)}
                                        className="btn-action btn-action-xs btn-primary"
                                    >
                                        <Wifi className="mr-1.5 h-3.5 w-3.5" />
                                        Tes koneksi
                                    </button>
                                )}
                            </div>
                        </div>
                    );
                })}

                {canWrite && (
                    <div className="flex justify-end">
                        <button
                            type="submit"
                            disabled={processing}
                            className="btn-action btn-action-sm btn-primary"
                        >
                            {processing ? 'Menyimpan...' : 'Simpan pengaturan'}
                        </button>
                    </div>
                )}
            </form>

            <div className="mt-6 border border-ink/10 bg-white p-6">
                <h2 className="text-sm font-semibold text-ink">Portal pelanggan</h2>
                <p className="mt-1 text-sm text-ink-soft">
                    Pelanggan dapat cek tagihan dengan username PPPoE dan nomor telepon, lalu bayar
                    online tanpa login admin.
                </p>
                <a
                    href={portal_url}
                    target="_blank"
                    rel="noreferrer"
                    className="mt-3 inline-flex items-center text-sm font-semibold text-signal-deep hover:underline"
                >
                    <ExternalLink className="mr-1.5 h-3.5 w-3.5" />
                    Buka {portal_url}
                </a>
            </div>
        </AdminLayout>
    );
}
