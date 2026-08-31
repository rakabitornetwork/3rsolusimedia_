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
    { id: 'pppoe', label: 'PPPoE realtime' },
    { id: 'binding', label: 'Binding' },
    { id: 'log', label: 'Log' },
];

function ScriptCommand({ copyKey, copied, onCopy, label, hint, command }) {
    return (
        <div className="border border-ink/10 bg-mist/50 p-3">
            <div className="mb-2 flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="text-xs font-semibold text-ink">{label}</p>
                    {hint ? <p className="mt-0.5 text-xs font-normal text-ink-soft">{hint}</p> : null}
                </div>
                <button
                    type="button"
                    onClick={() => onCopy(copyKey, command)}
                    className="btn-action btn-action-xs btn-secondary shrink-0"
                >
                    {copied === copyKey ? (
                        <CheckCircle2 className="mr-1.5 h-3.5 w-3.5 text-emerald-600" />
                    ) : (
                        <Copy className="mr-1.5 h-3.5 w-3.5 text-slate-600" />
                    )}
                    Salin
                </button>
            </div>
            <pre className="overflow-x-auto whitespace-pre font-mono text-[11px] leading-relaxed text-ink">
                {command}
            </pre>
        </div>
    );
}

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
    pppoe_scripts = [],
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
        messaging_notify_welcome: config?.notify_welcome !== false,
        messaging_notify_pppoe_session: Boolean(config?.notify_pppoe_session),
        messaging_pppoe_session_debounce: Number(config?.pppoe_session_debounce ?? 3),
        whatsapp_send_delay_min: Number(config?.whatsapp_send_delay_min ?? 25),
        whatsapp_send_delay_max: Number(config?.whatsapp_send_delay_max ?? 50),
        whatsapp_send_batch: Number(config?.whatsapp_send_batch ?? 2),
        whatsapp_send_daily_limit: Number(config?.whatsapp_send_daily_limit ?? 80),
        msg_tpl_invoice: config?.templates?.invoice || '',
        msg_tpl_reminder: config?.templates?.reminder || '',
        msg_tpl_paid: config?.templates?.paid || '',
        msg_tpl_isolir: config?.templates?.isolir || '',
        msg_tpl_restore: config?.templates?.restore || '',
        msg_tpl_welcome: config?.templates?.welcome || '',
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

    const regeneratePppoeSecret = () => {
        if (!canWrite) return;
        if (
            !window.confirm(
                'Token baru membuat script lama di ketiga router berhenti bekerja. Salin ulang script setelah ini. Lanjutkan?',
            )
        ) {
            return;
        }
        router.post('/admin/messaging/pppoe-webhook-secret', {}, keepPage);
    };

    const waState = waConnect?.state || waLive?.state;
    const waMessage = waConnect?.message || waLive?.message;
    const waQr = waConnect?.qr_base64 || null;

    return (
        <AdminLayout
            title="Notifikasi & Bot"
            subtitle="Telegram, WhatsApp, dan script realtime RouterOS"
        >
            <Head title="Notifikasi & Bot" />

            <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <p className="max-w-2xl text-sm text-ink-soft">
                    Pelanggan mengikat chat, cek tagihan, dan bayar. Pengingat tagihan/isolir memakai
                    template di tab ini dan dikirim ke chat terikat atau nomor HP pelanggan (WhatsApp).
                    Perubahan sesi PPPoE connected/disconnected dikirim ke Chat ID admin Telegram
                    secara realtime (tab PPPoE realtime) dengan cron sebagai cadangan.
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
                    {stats.outbox_pending > 0 ? ` · ${stats.outbox_pending} antrian WA` : ''}
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
                                Chat ID admin / teknisi
                                <input
                                    type="text"
                                    value={data.telegram_admin_chat_id}
                                    onChange={(e) => setData('telegram_admin_chat_id', e.target.value)}
                                    disabled={!canWrite}
                                    className={fieldClass}
                                    placeholder="123456789, 987654321"
                                    autoComplete="off"
                                />
                                <p className="mt-1 text-xs font-normal text-ink-soft">
                                    Bisa beberapa ID dipisah koma. Chat ini boleh memakai /cari nama_pelanggan
                                    untuk profil, RX power, suhu, SSID, dan tagihan, serta menerima notifikasi
                                    sesi PPPoE. Pasang ulang webhook setelah menyimpan.
                                </p>
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
                            Variabel: {'{{nama}} {{username}} {{password}} {{phone}} {{alamat}} {{paket}} {{harga_paket}} {{tanggal_mulai}} {{hari_tagihan}} {{jatuh_tempo}} {{tagihan_pertama}} {{nomor}} {{total}} {{portal}} {{telepon_kantor}} {{perusahaan}}'}.
                            {'{{alamat}}'} memakai teks alamat, atau koordinat GPS (tanpa tautan peta) jika kosong.
                            Dikirim ke chat terikat; WhatsApp juga ke nomor HP di data pelanggan.
                        </p>
                        <div className="mt-4 space-y-2">
                            <label className="flex items-start justify-between gap-4 border border-ink/10 px-4 py-3">
                                <span>
                                    <span className="block text-sm font-medium text-ink">
                                        Tagihan baru, pengingat, & konfirmasi lunas
                                    </span>
                                    <span className="mt-0.5 block text-xs text-ink-soft">
                                        Tagihan baru dan pengingat masuk antrian, dikirim satu-satu
                                        dengan jeda acak. Konfirmasi lunas tetap langsung.
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
                            <label className="flex items-start justify-between gap-4 border border-ink/10 px-4 py-3">
                                <span>
                                    <span className="block text-sm font-medium text-ink">
                                        Selamat datang pelanggan baru
                                    </span>
                                    <span className="mt-0.5 block text-xs text-ink-soft">
                                        Dikirim ke WhatsApp saat pelanggan PPPoE baru disimpan, termasuk akun,
                                        paket, tagihan pertama, dan portal.
                                    </span>
                                </span>
                                <input
                                    type="checkbox"
                                    checked={Boolean(templates.data.messaging_notify_welcome)}
                                    disabled={!canWrite}
                                    onChange={(e) =>
                                        templates.setData('messaging_notify_welcome', e.target.checked)
                                    }
                                    className="mt-1 h-4 w-4 accent-signal-deep"
                                />
                            </label>
                            <label className="flex items-start justify-between gap-4 border border-ink/10 px-4 py-3">
                                <span>
                                    <span className="block text-sm font-medium text-ink">
                                        Sesi PPPoE connected & disconnected
                                    </span>
                                    <span className="mt-0.5 block text-xs text-ink-soft">
                                        Dikirim ke Chat ID admin Telegram. Utama: script on-up/on-down
                                        di RouterOS (tab PPPoE realtime). Cadangan: scheduler
                                        (`pppoe:watch-sessions` setiap menit) untuk jeda disconnect
                                        dan jika webhook router gagal. Reconnect singkat diabaikan.
                                        Banyak sesi sekaligus diringkas jadi satu pesan.
                                    </span>
                                </span>
                                <input
                                    type="checkbox"
                                    checked={Boolean(templates.data.messaging_notify_pppoe_session)}
                                    disabled={!canWrite}
                                    onChange={(e) =>
                                        templates.setData('messaging_notify_pppoe_session', e.target.checked)
                                    }
                                    className="mt-1 h-4 w-4 accent-signal-deep"
                                />
                            </label>
                            <label className="block border border-ink/10 px-4 py-3 text-sm font-medium text-ink">
                                Jeda disconnect (menit)
                                <input
                                    type="number"
                                    min={0}
                                    max={30}
                                    value={templates.data.messaging_pppoe_session_debounce}
                                    disabled={!canWrite}
                                    onChange={(e) =>
                                        templates.setData(
                                            'messaging_pppoe_session_debounce',
                                            e.target.value === '' ? 0 : Number(e.target.value),
                                        )
                                    }
                                    className={fieldClass}
                                />
                                <span className="mt-1 block text-xs font-normal text-ink-soft">
                                    0 = kirim langsung. Nilai 3–5 menghindari banjir saat modem restart.
                                </span>
                            </label>
                            <div className="border border-ink/10 px-4 py-3">
                                <p className="text-sm font-medium text-ink">Jeda WhatsApp tagihan (anti-spam)</p>
                                <p className="mt-0.5 text-xs font-normal text-ink-soft">
                                    Evolution API memakai sesi WhatsApp tidak resmi. Kirim banyak tagihan
                                    beruntun mudah ditandai spam dan nomor bisa diblokir. Tagihan baru,
                                    pengingat, dan kirim massal masuk antrian: jeda acak antar pesan,
                                    plus jeda mengetik 0,9–2,8 detik di Evolution. Cron harus jalan
                                    setiap menit (`php artisan schedule:run`). Nomor baru: mulai dari
                                    batas harian rendah (30–50), naik pelan setelah 1–2 minggu.
                                </p>
                                <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    <label className="block text-xs font-medium text-ink">
                                        Jeda min (detik)
                                        <input
                                            type="number"
                                            min={8}
                                            max={180}
                                            value={templates.data.whatsapp_send_delay_min}
                                            disabled={!canWrite}
                                            onChange={(e) =>
                                                templates.setData(
                                                    'whatsapp_send_delay_min',
                                                    e.target.value === '' ? 8 : Number(e.target.value),
                                                )
                                            }
                                            className={fieldClass}
                                        />
                                    </label>
                                    <label className="block text-xs font-medium text-ink">
                                        Jeda max (detik)
                                        <input
                                            type="number"
                                            min={8}
                                            max={300}
                                            value={templates.data.whatsapp_send_delay_max}
                                            disabled={!canWrite}
                                            onChange={(e) =>
                                                templates.setData(
                                                    'whatsapp_send_delay_max',
                                                    e.target.value === '' ? 8 : Number(e.target.value),
                                                )
                                            }
                                            className={fieldClass}
                                        />
                                    </label>
                                    <label className="block text-xs font-medium text-ink">
                                        Maks per menit
                                        <input
                                            type="number"
                                            min={1}
                                            max={10}
                                            value={templates.data.whatsapp_send_batch}
                                            disabled={!canWrite}
                                            onChange={(e) =>
                                                templates.setData(
                                                    'whatsapp_send_batch',
                                                    e.target.value === '' ? 1 : Number(e.target.value),
                                                )
                                            }
                                            className={fieldClass}
                                        />
                                    </label>
                                    <label className="block text-xs font-medium text-ink">
                                        Batas harian
                                        <input
                                            type="number"
                                            min={0}
                                            max={500}
                                            value={templates.data.whatsapp_send_daily_limit}
                                            disabled={!canWrite}
                                            onChange={(e) =>
                                                templates.setData(
                                                    'whatsapp_send_daily_limit',
                                                    e.target.value === '' ? 0 : Number(e.target.value),
                                                )
                                            }
                                            className={fieldClass}
                                        />
                                        <span className="mt-1 block font-normal text-ink-soft">
                                            0 = tanpa batas. Default 80.
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    {[
                        ['msg_tpl_welcome', '🎉 Selamat datang pelanggan baru'],
                        ['msg_tpl_invoice', '🧾 Tagihan baru'],
                        ['msg_tpl_reminder', '⏰ Pengingat jatuh tempo'],
                        ['msg_tpl_paid', '✅ Pembayaran diterima (lunas)'],
                        ['msg_tpl_isolir', '⛔ Isolir'],
                        ['msg_tpl_restore', '✅ Layanan aktif kembali'],
                    ].map(([name, label]) => (
                        <label key={name} className="block border border-ink/10 bg-white p-6 text-sm font-medium text-ink">
                            {label}
                            <textarea
                                rows={name === 'msg_tpl_welcome' ? 16 : 7}
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

            {tab === 'pppoe' && (
                <div className="space-y-5">
                    <div className="border border-ink/10 bg-white p-6">
                        <div className="flex items-start gap-3">
                            <div className="flex h-10 w-10 items-center justify-center bg-signal/10 text-signal-deep">
                                <Wifi className="h-5 w-5" />
                            </div>
                            <div>
                                <h2 className="text-sm font-semibold text-ink">
                                    PPPoE connected / disconnected realtime
                                </h2>
                                <p className="mt-1 text-sm text-ink-soft">
                                    RouterOS memanggil panel saat sesi naik atau turun. Panel mengirim Telegram
                                    ke Chat ID admin. Pasang script yang sama polanya di ketiga router — bedanya
                                    hanya parameter <span className="font-mono">router=ID</span>.
                                </p>
                            </div>
                        </div>

                        <ol className="mt-5 list-decimal space-y-3 pl-5 text-sm text-ink">
                            <li>
                                Tab Template: nyalakan <strong>Sesi PPPoE connected & disconnected</strong>,
                                atur jeda disconnect (disarankan 3 menit), lalu simpan. Telegram harus aktif
                                dan Chat ID admin terisi.
                            </li>
                            <li>
                                Cron tetap jalan:{' '}
                                <span className="font-mono text-xs">
                                    * * * * * cd /home/teslatech/public_html && php artisan schedule:run
                                </span>
                                . Scheduler mengirim disconnect yang ditunda dan jadi cadangan jika fetch
                                router gagal.
                            </li>
                            <li>
                                Di Winbox/Terminal setiap router, pastikan router bisa buka{' '}
                                <span className="font-mono text-xs">{webhook_urls.pppoe || '/webhooks/pppoe'}</span>{' '}
                                (HTTPS ke panel publik).
                            </li>
                            <li>
                                Terminal: tempel <strong>satu perintah, lalu Enter</strong> (jangan Enter di
                                tengah URL). Jangan bungkus dengan {'{ }'} — RouterOS menolak{' '}
                                <span className="font-mono text-xs">on-up={'{...}'}</span>. Winbox: PPP →
                                Profiles → Scripts → On Up / On Down, tempel hanya baris{' '}
                                <span className="font-mono text-xs">/tool fetch</span>.
                            </li>
                            <li>
                                Tes dulu perintah ping. Lalu cabut/pasang satu pelanggan: Telegram connected
                                harus segera muncul; disconnected menunggu jeda (kecuali jeda = 0).
                            </li>
                        </ol>

                        <p className="mt-4 text-xs text-ink-soft">
                            <span className="font-mono">keep-result=no</span> agar file fetch tidak menumpuk.{' '}
                            <span className="font-mono">check-certificate=no</span> agar HTTPS tetap jalan
                            jika jam router atau CA belum lengkap. Perintah 4 dan 5 di Terminal memasang ke
                            semua PPP profile. Pakai tombol Salin — jangan mengetik ulang URL yang terbungkus.
                        </p>

                        <div className="mt-4 flex flex-wrap items-center gap-2 border-t border-ink/10 pt-4">
                            <div className="min-w-0 flex-1">
                                <p className="text-xs tracking-wide text-ink-soft uppercase">Webhook URL</p>
                                <p className="mt-1 truncate font-mono text-xs text-ink">
                                    {webhook_urls.pppoe}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => copyText('pppoe-url', webhook_urls.pppoe)}
                                className="btn-action btn-action-xs btn-secondary"
                            >
                                {copied === 'pppoe-url' ? (
                                    <CheckCircle2 className="mr-1.5 h-3.5 w-3.5 text-emerald-600" />
                                ) : (
                                    <Copy className="mr-1.5 h-3.5 w-3.5 text-slate-600" />
                                )}
                                Salin URL
                            </button>
                            {canWrite && (
                                <button
                                    type="button"
                                    onClick={regeneratePppoeSecret}
                                    className="btn-action btn-action-xs btn-secondary"
                                >
                                    Ganti token
                                </button>
                            )}
                        </div>
                    </div>

                    {pppoe_scripts.length === 0 ? (
                        <div className="border border-ink/10 bg-white p-6 text-sm text-ink-soft">
                            Belum ada router aktif. Tambah dan aktifkan di Network → RouterOS, lalu buka
                            ulang halaman ini. Script akan muncul per router.
                        </div>
                    ) : (
                        pppoe_scripts.map((item, index) => (
                            <div key={item.router_id} className="border border-ink/10 bg-white p-6">
                                <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="text-xs tracking-wide text-ink-soft uppercase">
                                            Router {index + 1} dari {pppoe_scripts.length}
                                        </p>
                                        <h3 className="mt-1 text-sm font-semibold text-ink">
                                            {item.router_name}
                                            <span className="ml-2 font-mono text-xs font-normal text-ink-soft">
                                                id={item.router_id}
                                            </span>
                                        </h3>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => copyText(`all-${item.router_id}`, item.all)}
                                        className="btn-action btn-action-xs btn-primary"
                                    >
                                        {copied === `all-${item.router_id}` ? (
                                            <CheckCircle2 className="mr-1.5 h-3.5 w-3.5" />
                                        ) : (
                                            <Copy className="mr-1.5 h-3.5 w-3.5" />
                                        )}
                                        Salin 3 perintah Terminal
                                    </button>
                                </div>

                                <div className="space-y-3">
                                    <ScriptCommand
                                        copyKey={`ping-${item.router_id}`}
                                        copied={copied}
                                        onCopy={copyText}
                                        label="1. Tes webhook (Terminal)"
                                        hint="Jalankan sekali. Panel membalas OK jika token dan URL benar."
                                        command={item.ping}
                                    />
                                    <ScriptCommand
                                        copyKey={`up-${item.router_id}`}
                                        copied={copied}
                                        onCopy={copyText}
                                        label="2. On-up (Winbox saja — Scripts → On Up)"
                                        hint="Jangan tempel ini di Terminal. Jangan tambah kurung kurawal."
                                        command={item.on_up}
                                    />
                                    <ScriptCommand
                                        copyKey={`down-${item.router_id}`}
                                        copied={copied}
                                        onCopy={copyText}
                                        label="3. On-down (Winbox saja — Scripts → On Down)"
                                        hint="Jangan tempel ini di Terminal. Username ikut dikirim saat putus."
                                        command={item.on_down}
                                    />
                                    <ScriptCommand
                                        copyKey={`apply-up-${item.router_id}`}
                                        copied={copied}
                                        onCopy={copyText}
                                        label="4. Pasang on-up ke semua PPP profile (Terminal)"
                                        hint="Pakai tombol Salin, lalu tempel sekali dan Enter. Menimpa on-up di semua profile."
                                        command={item.apply_up}
                                    />
                                    <ScriptCommand
                                        copyKey={`apply-down-${item.router_id}`}
                                        copied={copied}
                                        onCopy={copyText}
                                        label="5. Pasang on-down ke semua PPP profile (Terminal)"
                                        hint="Pakai tombol Salin, lalu tempel sekali dan Enter. Menimpa on-down di semua profile."
                                        command={item.apply_down}
                                    />
                                </div>
                            </div>
                        ))
                    )}
                </div>
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
