import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    Copy,
    ExternalLink,
    Link2,
    MessageCircle,
    QrCode,
    Send,
    Unlink,
    Wifi,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { keepPage } from '../../../lib/keepPage';
import { matchesSearch } from '../../../lib/search';

const fieldClass =
    'mt-1.5 w-full border border-ink/15 bg-white px-3 py-2.5 text-sm outline-none focus:border-signal disabled:bg-mist';

const TABS = [
    { id: 'kanal', label: 'Kanal' },
    { id: 'template', label: 'Template' },
    { id: 'binding', label: 'Binding' },
    { id: 'log', label: 'Log' },
];

function formatWhen(value) {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleString('id-ID', {
            day: '2-digit',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return value;
    }
}

function csrfHeaders() {
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
    };
}

export default function Index({
    config,
    webhook_urls = {},
    webhook,
    whatsapp_status,
    enabled_channels = [],
    identities = [],
    logs = [],
    stats = {},
}) {
    const { auth } = usePage().props;
    const canWrite = auth?.user?.can_write !== false;
    const telegram = config?.telegram || {};
    const whatsapp = config?.whatsapp || {};
    const [tab, setTab] = useState('kanal');
    const [copied, setCopied] = useState('');
    const [query, setQuery] = useState('');
    const [waBusy, setWaBusy] = useState(false);
    const [waConnect, setWaConnect] = useState(null);
    const [telegramLive, setTelegramLive] = useState(webhook);
    const [waLive, setWaLive] = useState(whatsapp_status);

    const { data, setData, post, processing, errors, transform } = useForm({
        telegram_enabled: Boolean(telegram.enabled),
        telegram_bot_token: '',
        telegram_admin_chat_id: telegram.admin_chat_id || '',
        whatsapp_enabled: Boolean(whatsapp.enabled),
        whatsapp_base_url: whatsapp.base_url || 'http://127.0.0.1:8080',
        whatsapp_api_key: '',
        whatsapp_instance: whatsapp.instance || 'teslatech',
        whatsapp_test_number: whatsapp.test_number || '',
    });

    const templates = useForm({
        app_notif_whatsapp: Boolean(config?.notify_invoice),
        messaging_notify_isolir: Boolean(config?.notify_isolir),
        msg_tpl_invoice: config?.templates?.invoice || '',
        msg_tpl_reminder: config?.templates?.reminder || '',
        msg_tpl_isolir: config?.templates?.isolir || '',
        msg_tpl_restore: config?.templates?.restore || '',
    });

    useEffect(() => {
        let cancelled = false;

        if (telegram.has_bot_token) {
            fetch('/admin/messaging/telegram/status', {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then((response) => (response.ok ? response.json() : null))
                .then((json) => {
                    if (!cancelled && json) setTelegramLive(json);
                })
                .catch(() => {});
        }

        if (whatsapp.has_api_key) {
            fetch('/admin/messaging/whatsapp/status', {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then((response) => (response.ok ? response.json() : null))
                .then((json) => {
                    if (!cancelled && json) setWaLive(json);
                })
                .catch(() => {});
        }

        return () => {
            cancelled = true;
        };
    }, [telegram.has_bot_token, whatsapp.has_api_key]);

    const filteredIdentities = useMemo(
        () =>
            identities.filter((row) =>
                matchesSearch(
                    query,
                    row.channel,
                    row.external_id,
                    row.display_name,
                    row.username,
                    row.customer?.name,
                    row.customer?.username,
                    row.customer?.phone,
                ),
            ),
        [identities, query],
    );

    const save = (e) => {
        e.preventDefault();
        if (!canWrite) return;
        transform((form) => {
            const payload = { ...form };
            if (!payload.telegram_bot_token) delete payload.telegram_bot_token;
            if (!payload.whatsapp_api_key) delete payload.whatsapp_api_key;
            return payload;
        });
        post('/admin/messaging', keepPage);
    };

    const saveTemplates = (e) => {
        e.preventDefault();
        if (!canWrite) return;
        templates.post('/admin/messaging/templates', keepPage);
    };

    const copyText = async (key, value) => {
        try {
            await navigator.clipboard.writeText(value || '');
            setCopied(key);
            setTimeout(() => setCopied(''), 2000);
        } catch {
            window.prompt('Salin URL ini:', value);
        }
    };

    const testChannel = (channel, chatId) => {
        if (!canWrite) return;
        router.post('/admin/messaging/test', { channel, chat_id: chatId || '' }, keepPage);
    };

    const setWebhook = (channel) => {
        if (!canWrite) return;
        router.post('/admin/messaging/webhook', { channel }, keepPage);
    };

    const connectWhatsapp = async () => {
        if (!canWrite) return;
        setWaBusy(true);
        try {
            const response = await fetch('/admin/messaging/whatsapp/connect', {
                method: 'POST',
                headers: csrfHeaders(),
                credentials: 'same-origin',
            });
            const json = await response.json();
            setWaConnect(json);
        } catch {
            setWaConnect({ ok: false, message: 'Gagal menghubungi panel.' });
        } finally {
            setWaBusy(false);
        }
    };

    const unbind = (row) => {
        if (!canWrite) return;
        const label = row.customer?.username || row.external_id;
        if (!window.confirm(`Lepas ikatan ${row.channel} untuk ${label}?`)) return;
        router.delete(`/admin/messaging/identities/${row.id}`, keepPage);
    };

    const waState = waConnect?.state || waLive?.state;
    const waMessage = waConnect?.message || waLive?.message;
    const waQr = waConnect?.qr_base64 || null;

    return (
        <AdminLayout
            title="Notifikasi & Bot"
            subtitle="Telegram Bot API dan WhatsApp via Evolution API"
        >
            <Head title="Notifikasi & Bot" />

            <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <p className="max-w-2xl text-sm text-ink-soft">
                    Pelanggan mengikat chat, cek tagihan, dan bayar. Pengingat tagihan/isolir memakai
                    template di tab ini dan dikirim ke chat terikat atau nomor HP pelanggan (WhatsApp).
                </p>
                <div className="text-xs text-ink-soft">
                    Aktif:{' '}
                    {enabled_channels?.length
                        ? enabled_channels.map((c) => c.toUpperCase()).join(', ')
                        : 'belum ada'}
                    {' · '}
                    {stats.bound ?? 0} terikat
                    {' · '}
                    {stats.logs_today ?? 0} log hari ini
                </div>
            </div>

            <div className="mb-4 flex flex-wrap gap-1 border border-ink/10 bg-white p-1">
                {TABS.map((item) => (
                    <button
                        key={item.id}
                        type="button"
                        onClick={() => setTab(item.id)}
                        className={`px-3 py-1.5 text-sm font-medium ${
                            tab === item.id
                                ? 'bg-signal/15 text-signal-deep'
                                : 'text-ink-soft hover:bg-mist'
                        }`}
                    >
                        {item.label}
                    </button>
                ))}
            </div>

            {tab === 'kanal' && (
                <form onSubmit={save} className="space-y-5">
                    <div className="border border-ink/10 bg-white p-6">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="flex items-start gap-3">
                                <div className="flex h-10 w-10 items-center justify-center bg-signal/10 text-signal-deep">
                                    <Send className="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 className="text-sm font-semibold text-ink">Telegram Bot API</h2>
                                    <p className="mt-1 text-sm text-ink-soft">
                                        Resmi, gratis, webhook ke panel. Buat bot di BotFather, tempel token,
                                        lalu pasang webhook.
                                    </p>
                                    {telegram.bot_link && (
                                        <a
                                            href={telegram.bot_link}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="mt-2 inline-flex items-center text-sm font-semibold text-signal-deep hover:underline"
                                        >
                                            <ExternalLink className="mr-1.5 h-3.5 w-3.5" />
                                            Buka {telegram.bot_link}
                                        </a>
                                    )}
                                </div>
                            </div>
                            <label className="inline-flex items-center gap-2 text-sm font-medium text-ink">
                                <input
                                    type="checkbox"
                                    checked={Boolean(data.telegram_enabled)}
                                    onChange={(e) => setData('telegram_enabled', e.target.checked)}
                                    disabled={!canWrite}
                                    className="h-4 w-4 border-ink/30 text-signal focus:ring-signal"
                                />
                                Aktifkan
                            </label>
                        </div>

                        <div className="mt-5 grid gap-4 sm:grid-cols-2">
                            <label className="block text-sm font-medium text-ink">
                                Bot token
                                {telegram.has_bot_token ? (
                                    <span className="ml-2 text-xs font-normal text-emerald-700">
                                        sudah tersimpan
                                    </span>
                                ) : null}
                                <input
                                    type="password"
                                    value={data.telegram_bot_token}
                                    onChange={(e) => setData('telegram_bot_token', e.target.value)}
                                    disabled={!canWrite}
                                    className={fieldClass}
                                    placeholder={
                                        telegram.has_bot_token
                                            ? 'Kosongkan jika tidak diganti'
                                            : '123456:AAH… dari BotFather'
                                    }
                                    autoComplete="new-password"
                                />
                                {errors.telegram_bot_token && (
                                    <p className="mt-1 text-xs text-red-600">{errors.telegram_bot_token}</p>
                                )}
                            </label>
                            <label className="block text-sm font-medium text-ink">
                                Chat ID admin (tes kirim)
                                <input
                                    type="text"
                                    value={data.telegram_admin_chat_id}
                                    onChange={(e) => setData('telegram_admin_chat_id', e.target.value)}
                                    disabled={!canWrite}
                                    className={fieldClass}
                                    placeholder="Dari /start ke bot, lihat tab Log"
                                    autoComplete="off"
                                />
                            </label>
                        </div>

                        <div className="mt-5 flex flex-wrap items-center gap-2 border-t border-ink/10 pt-4">
                            <div className="min-w-0 flex-1">
                                <p className="text-xs tracking-wide text-ink-soft uppercase">
                                    Webhook URL
                                </p>
                                <p className="mt-1 truncate font-mono text-xs text-ink">
                                    {webhook_urls.telegram}
                                </p>
                                <p className="mt-1 text-xs text-ink-soft">
                                    {telegramLive?.message || webhook?.message || 'Belum diperiksa.'}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => copyText('telegram', webhook_urls.telegram)}
                                className="btn-action btn-action-xs btn-secondary"
                            >
                                {copied === 'telegram' ? (
                                    <CheckCircle2 className="mr-1.5 h-3.5 w-3.5 text-emerald-600" />
                                ) : (
                                    <Copy className="mr-1.5 h-3.5 w-3.5 text-slate-600" />
                                )}
                                Salin
                            </button>
                            {canWrite && (
                                <>
                                    <button
                                        type="button"
                                        onClick={() => setWebhook('telegram')}
                                        className="btn-action btn-action-xs btn-secondary"
                                    >
                                        <Link2 className="mr-1.5 h-3.5 w-3.5" />
                                        Pasang webhook
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            testChannel('telegram', data.telegram_admin_chat_id)
                                        }
                                        className="btn-action btn-action-xs btn-primary"
                                    >
                                        <Wifi className="mr-1.5 h-3.5 w-3.5" />
                                        Tes koneksi
                                    </button>
                                </>
                            )}
                        </div>
                    </div>

                    <div className="border border-ink/10 bg-white p-6">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="flex items-start gap-3">
                                <div className="flex h-10 w-10 items-center justify-center bg-signal/10 text-signal-deep">
                                    <MessageCircle className="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 className="text-sm font-semibold text-ink">
                                        WhatsApp — Evolution API
                                    </h2>
                                    <p className="mt-1 text-sm text-ink-soft">
                                        Sidecar HTTP (Baileys di Docker). Isi URL Evolution, API key, dan
                                        nama instance, lalu hubungkan dengan scan QR.
                                    </p>
                                </div>
                            </div>
                            <label className="inline-flex items-center gap-2 text-sm font-medium text-ink">
                                <input
                                    type="checkbox"
                                    checked={Boolean(data.whatsapp_enabled)}
                                    onChange={(e) => setData('whatsapp_enabled', e.target.checked)}
                                    disabled={!canWrite}
                                    className="h-4 w-4 border-ink/30 text-signal focus:ring-signal"
                                />
                                Aktifkan
                            </label>
                        </div>

                        <div className="mt-5 grid gap-4 sm:grid-cols-2">
                            <label className="block text-sm font-medium text-ink">
                                Base URL
                                <input
                                    type="text"
                                    value={data.whatsapp_base_url}
                                    onChange={(e) => setData('whatsapp_base_url', e.target.value)}
                                    disabled={!canWrite}
                                    className={fieldClass}
                                    placeholder="http://127.0.0.1:8080"
                                    autoComplete="off"
                                />
                            </label>
                            <label className="block text-sm font-medium text-ink">
                                Nama instance
                                <input
                                    type="text"
                                    value={data.whatsapp_instance}
                                    onChange={(e) => setData('whatsapp_instance', e.target.value)}
                                    disabled={!canWrite}
                                    className={fieldClass}
                                    placeholder="teslatech"
                                    autoComplete="off"
                                />
                            </label>
                            <label className="block text-sm font-medium text-ink">
                                API key
                                {whatsapp.has_api_key ? (
                                    <span className="ml-2 text-xs font-normal text-emerald-700">
                                        sudah tersimpan
                                    </span>
                                ) : null}
                                <input
                                    type="password"
                                    value={data.whatsapp_api_key}
                                    onChange={(e) => setData('whatsapp_api_key', e.target.value)}
                                    disabled={!canWrite}
                                    className={fieldClass}
                                    placeholder={
                                        whatsapp.has_api_key
                                            ? 'Kosongkan jika tidak diganti'
                                            : 'apikey global Evolution'
                                    }
                                    autoComplete="new-password"
                                />
                            </label>
                            <label className="block text-sm font-medium text-ink">
                                Nomor tes (62…)
                                <input
                                    type="text"
                                    value={data.whatsapp_test_number}
                                    onChange={(e) => setData('whatsapp_test_number', e.target.value)}
                                    disabled={!canWrite}
                                    className={fieldClass}
                                    placeholder="6281234567890"
                                    autoComplete="off"
                                />
                            </label>
                        </div>

                        <div className="mt-5 grid gap-4 border-t border-ink/10 pt-4 lg:grid-cols-[1fr_auto]">
                            <div>
                                <p className="text-xs tracking-wide text-ink-soft uppercase">
                                    Status & webhook
                                </p>
                                <p className="mt-1 text-sm text-ink">
                                    {waMessage || 'Belum diperiksa.'}
                                    {waState ? ` (${waState})` : ''}
                                </p>
                                <p className="mt-1 truncate font-mono text-xs text-ink">
                                    {webhook_urls.whatsapp}
                                </p>
                                {waConnect?.pairing_code && (
                                    <p className="mt-2 text-sm text-ink">
                                        Kode pairing: <strong>{waConnect.pairing_code}</strong>
                                    </p>
                                )}
                            </div>
                            <div className="flex flex-wrap items-start gap-2">
                                <button
                                    type="button"
                                    onClick={() => copyText('whatsapp', webhook_urls.whatsapp)}
                                    className="btn-action btn-action-xs btn-secondary"
                                >
                                    {copied === 'whatsapp' ? (
                                        <CheckCircle2 className="mr-1.5 h-3.5 w-3.5 text-emerald-600" />
                                    ) : (
                                        <Copy className="mr-1.5 h-3.5 w-3.5 text-slate-600" />
                                    )}
                                    Salin
                                </button>
                                {canWrite && (
                                    <>
                                        <button
                                            type="button"
                                            onClick={() => setWebhook('whatsapp')}
                                            className="btn-action btn-action-xs btn-secondary"
                                        >
                                            <Link2 className="mr-1.5 h-3.5 w-3.5" />
                                            Pasang webhook
                                        </button>
                                        <button
                                            type="button"
                                            onClick={connectWhatsapp}
                                            disabled={waBusy}
                                            className="btn-action btn-action-xs btn-secondary"
                                        >
                                            <QrCode className="mr-1.5 h-3.5 w-3.5" />
                                            {waBusy ? 'Menghubungkan…' : 'Hubungkan / QR'}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                testChannel('whatsapp', data.whatsapp_test_number)
                                            }
                                            className="btn-action btn-action-xs btn-primary"
                                        >
                                            <Wifi className="mr-1.5 h-3.5 w-3.5" />
                                            Tes koneksi
                                        </button>
                                    </>
                                )}
                            </div>
                        </div>

                        {waQr && (
                            <div className="mt-4 border border-ink/10 bg-mist/40 p-4">
                                <p className="text-xs text-ink-soft">
                                    Scan dengan WhatsApp → Perangkat tertaut. QR kadaluarsa ~60 detik;
                                    klik Hubungkan lagi jika perlu.
                                </p>
                                <img
                                    src={waQr}
                                    alt="QR WhatsApp Evolution"
                                    className="mt-3 h-48 w-48 bg-white object-contain"
                                />
                            </div>
                        )}
                    </div>

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
            )}

            {tab === 'template' && (
                <form onSubmit={saveTemplates} className="space-y-5">
                    <div className="border border-ink/10 bg-white p-6">
                        <h2 className="text-sm font-semibold text-ink">Pengiriman otomatis</h2>
                        <p className="mt-1 text-sm text-ink-soft">
                            Variabel: {'{{nama}} {{username}} {{nomor}} {{total}} {{jatuh_tempo}} {{paket}} {{perusahaan}}'}.
                            Dikirim ke chat terikat; WhatsApp juga ke nomor HP di data pelanggan.
                        </p>
                        <div className="mt-4 space-y-2">
                            <label className="flex items-start justify-between gap-4 border border-ink/10 px-4 py-3">
                                <span>
                                    <span className="block text-sm font-medium text-ink">
                                        Tagihan baru & pengingat jatuh tempo
                                    </span>
                                    <span className="mt-0.5 block text-xs text-ink-soft">
                                        Mengganti toggle stub di Pengaturan Aplikasi (app_notif_whatsapp).
                                    </span>
                                </span>
                                <input
                                    type="checkbox"
                                    checked={Boolean(templates.data.app_notif_whatsapp)}
                                    disabled={!canWrite}
                                    onChange={(e) =>
                                        templates.setData('app_notif_whatsapp', e.target.checked)
                                    }
                                    className="mt-1 h-4 w-4 accent-signal-deep"
                                />
                            </label>
                            <label className="flex items-start justify-between gap-4 border border-ink/10 px-4 py-3">
                                <span>
                                    <span className="block text-sm font-medium text-ink">
                                        Isolir & pemulihan layanan
                                    </span>
                                    <span className="mt-0.5 block text-xs text-ink-soft">
                                        Saat auto isolir atau restore profile berhasil.
                                    </span>
                                </span>
                                <input
                                    type="checkbox"
                                    checked={Boolean(templates.data.messaging_notify_isolir)}
                                    disabled={!canWrite}
                                    onChange={(e) =>
                                        templates.setData('messaging_notify_isolir', e.target.checked)
                                    }
                                    className="mt-1 h-4 w-4 accent-signal-deep"
                                />
                            </label>
                        </div>
                    </div>

                    {[
                        ['msg_tpl_invoice', 'Tagihan baru'],
                        ['msg_tpl_reminder', 'Pengingat jatuh tempo'],
                        ['msg_tpl_isolir', 'Isolir'],
                        ['msg_tpl_restore', 'Layanan aktif kembali'],
                    ].map(([name, label]) => (
                        <label key={name} className="block border border-ink/10 bg-white p-6 text-sm font-medium text-ink">
                            {label}
                            <textarea
                                rows={7}
                                value={templates.data[name] || ''}
                                onChange={(e) => templates.setData(name, e.target.value)}
                                disabled={!canWrite}
                                className={fieldClass}
                            />
                        </label>
                    ))}

                    {canWrite && (
                        <div className="flex justify-end">
                            <button
                                type="submit"
                                disabled={templates.processing}
                                className="btn-action btn-action-sm btn-primary"
                            >
                                {templates.processing ? 'Menyimpan...' : 'Simpan template'}
                            </button>
                        </div>
                    )}
                </form>
            )}

            {tab === 'binding' && (
                <div className="border border-ink/10 bg-white">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-ink/10 px-5 py-4">
                        <div>
                            <h2 className="text-sm font-semibold text-ink">Chat terikat</h2>
                            <p className="mt-0.5 text-xs text-ink-soft">
                                Telegram: /daftar + nomor HP. WhatsApp: otomatis jika nomor chat cocok,
                                atau ketik daftar &lt;username&gt;.
                            </p>
                        </div>
                        <input
                            type="search"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Cari nama, username, chat ID…"
                            className="w-full max-w-xs border border-ink/15 px-3 py-2 text-sm outline-none focus:border-signal"
                        />
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                                <tr>
                                    <th className="px-5 py-2.5 font-medium">Pelanggan</th>
                                    <th className="px-5 py-2.5 font-medium">Kanal</th>
                                    <th className="px-5 py-2.5 font-medium">Chat / nomor</th>
                                    <th className="px-5 py-2.5 font-medium">Terikat</th>
                                    <th className="px-5 py-2.5 font-medium" />
                                </tr>
                            </thead>
                            <tbody>
                                {filteredIdentities.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="px-5 py-8 text-center text-ink-soft">
                                            Belum ada chat yang terikat.
                                        </td>
                                    </tr>
                                )}
                                {filteredIdentities.map((row) => (
                                    <tr key={row.id} className="border-t border-ink/10">
                                        <td className="px-5 py-3">
                                            <div className="font-medium text-ink">
                                                {row.customer?.name || '—'}
                                            </div>
                                            <div className="text-xs text-ink-soft">
                                                {row.customer?.username || '—'}
                                                {row.customer?.phone ? ` · ${row.customer.phone}` : ''}
                                            </div>
                                        </td>
                                        <td className="px-5 py-3 text-xs uppercase text-ink-soft">
                                            {row.channel}
                                        </td>
                                        <td className="px-5 py-3 font-mono text-xs text-ink">
                                            {row.external_id}
                                            {row.username ? (
                                                <span className="mt-0.5 block text-ink-soft">
                                                    @{row.username}
                                                </span>
                                            ) : null}
                                        </td>
                                        <td className="px-5 py-3 text-xs text-ink-soft">
                                            {formatWhen(row.verified_at)}
                                        </td>
                                        <td className="px-5 py-3 text-right">
                                            {canWrite && (
                                                <button
                                                    type="button"
                                                    onClick={() => unbind(row)}
                                                    className="btn-action btn-action-xs btn-danger"
                                                >
                                                    <Unlink className="h-3.5 w-3.5" />
                                                    Lepas
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {tab === 'log' && (
                <div className="border border-ink/10 bg-white">
                    <div className="border-b border-ink/10 px-5 py-4">
                        <h2 className="text-sm font-semibold text-ink">Log 40 pesan terakhir</h2>
                        <p className="mt-0.5 text-xs text-ink-soft">
                            Nomor HP pada pendaftaran disamarkan. Chat ID / nomor di sini dipakai untuk tes
                            kirim.
                        </p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                                <tr>
                                    <th className="px-5 py-2.5 font-medium">Waktu</th>
                                    <th className="px-5 py-2.5 font-medium">Arah</th>
                                    <th className="px-5 py-2.5 font-medium">Chat</th>
                                    <th className="px-5 py-2.5 font-medium">Isi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {logs.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="px-5 py-8 text-center text-ink-soft">
                                            Belum ada log.
                                        </td>
                                    </tr>
                                )}
                                {logs.map((row) => (
                                    <tr key={row.id} className="border-t border-ink/10 align-top">
                                        <td className="px-5 py-3 whitespace-nowrap text-xs text-ink-soft">
                                            {formatWhen(row.created_at)}
                                        </td>
                                        <td className="px-5 py-3 text-xs">
                                            <span
                                                className={
                                                    row.direction === 'inbound'
                                                        ? 'text-ink'
                                                        : row.status === 'failed'
                                                          ? 'text-red-700'
                                                          : 'text-emerald-700'
                                                }
                                            >
                                                {row.channel} ·{' '}
                                                {row.direction === 'inbound' ? 'Masuk' : 'Keluar'}
                                                {row.command ? ` /${row.command}` : ''}
                                            </span>
                                        </td>
                                        <td className="px-5 py-3 font-mono text-xs text-ink">
                                            {row.external_id}
                                            {row.customer_username ? (
                                                <span className="mt-0.5 block font-sans text-ink-soft">
                                                    {row.customer_username}
                                                </span>
                                            ) : null}
                                        </td>
                                        <td className="px-5 py-3 text-xs whitespace-pre-wrap text-ink">
                                            {row.body || '—'}
                                            {row.error_message ? (
                                                <span className="mt-1 block text-red-700">
                                                    {row.error_message}
                                                </span>
                                            ) : null}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            <p className="mt-5 text-xs text-ink-soft">
                Toggle notifikasi di{' '}
                <Link href="/admin/system" className="font-semibold text-signal-deep hover:underline">
                    Pengaturan Aplikasi
                </Link>{' '}
                memakai flag yang sama dengan tab Template di halaman ini.
            </p>
        </AdminLayout>
    );
}
