import { Link, router, useForm } from '@inertiajs/react';
import { Check, Copy, CreditCard, Terminal } from 'lucide-react';
import { useState } from 'react';
import PortalBanner from '../../../Components/Portal/PortalBanner';
import PortalLayout from '../../../Layouts/PortalLayout';

const steps = [
    'Siapkan komputer yang bisa membuka Winbox, lalu login ke router MikroTik milik Anda. Bukan router pusat layanan.',
    'Router itu harus sudah punya internet, karena terowongan VPN dibuat lewat koneksi yang ada.',
    'Di Winbox, klik New Terminal.',
    'Salin seluruh skrip di halaman ini. Klik di dalam terminal, tempel (klik kanan lalu Paste), kemudian tekan Enter.',
    'Jangan simpan skrip sebagai file .rsc dan jangan pakai menu Import. Skrip ini hanya dijalankan di terminal Winbox.',
    'Buka menu Interfaces. Interface l2tp-vpn harus berstatus R (running).',
];

export default function Home({
    branding,
    token,
    customer,
    billing,
    banners,
    script,
    script_message,
    vpn,
}) {
    const [paying, setPaying] = useState(false);
    const [copied, setCopied] = useState(false);
    const extra = useForm({ name: '' });
    const routerCount = Number(vpn?.router_count || 1);
    const routerLimit = Number(vpn?.router_limit || 3);
    const nextWithoutInvoice = Boolean(vpn?.next_without_invoice);
    const unpaidCount = Number(billing?.unpaid_count || 0);
    const gatewayReady = Boolean(billing?.gateway_ready);
    const oldestUnpaidId = billing?.oldest_unpaid_id;
    const invoicesHref = `/portal/${token}/tagihan`;

    const payOnline = () => {
        if (paying) return;

        if (!oldestUnpaidId || unpaidCount > 1 || !gatewayReady) {
            router.visit(invoicesHref);
            return;
        }

        if (!window.confirm('Lanjut ke halaman pembayaran online?')) return;

        setPaying(true);
        router.post(
            `/portal/${token}/pay/${oldestUnpaidId}`,
            {},
            {
                onFinish: () => setPaying(false),
            },
        );
    };

    const copyScript = async () => {
        if (!script) return;

        try {
            await navigator.clipboard.writeText(script);
        } catch {
            const area = document.createElement('textarea');
            area.value = script;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.left = '-9999px';
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            document.body.removeChild(area);
        }

        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    };

    return (
        <PortalLayout
            branding={branding}
            customer={customer}
            token={token}
            title="Skrip VPN"
            active="home"
        >
            <div className="space-y-4">
                <PortalBanner banners={banners} />

                <section className="overflow-hidden rounded-2xl border border-ink/10 bg-white shadow-[0_16px_40px_-28px_rgba(16,24,32,0.55)]">
                    <div className="h-1 bg-gradient-to-r from-signal-deep via-signal to-signal-bright" />
                    <div className="p-4 sm:p-5">
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <p className="text-xs tracking-wide text-ink-soft uppercase">Tagihan</p>
                                <h2 className="mt-1 text-base font-semibold text-ink">
                                    {unpaidCount
                                        ? `${unpaidCount} tagihan belum bayar`
                                        : 'Tidak ada tagihan aktif'}
                                </h2>
                                <p className="mt-1 text-sm text-ink-soft">
                                    {unpaidCount
                                        ? `Total ${billing.unpaid_total_label}`
                                        : 'Semua tagihan sudah lunas.'}
                                </p>
                            </div>
                            <CreditCard className="h-5 w-5 shrink-0 text-signal-deep" />
                        </div>
                        <div className="mt-4 flex flex-col gap-2 sm:flex-row">
                            {unpaidCount > 0 && (
                                <button
                                    type="button"
                                    onClick={payOnline}
                                    disabled={paying}
                                    className="inline-flex cursor-pointer items-center justify-center rounded-xl bg-signal px-4 py-2.5 text-sm font-semibold text-white shadow-[0_10px_24px_-16px_rgba(26,110,255,0.9)] hover:bg-signal-deep disabled:cursor-wait disabled:opacity-60"
                                >
                                    {paying ? 'Menyiapkan pembayaran...' : 'Bayar online'}
                                </button>
                            )}
                            <Link
                                href={invoicesHref}
                                className={`inline-flex cursor-pointer items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold ${
                                    unpaidCount > 0
                                        ? 'border border-ink/15 text-ink hover:bg-mist'
                                        : 'bg-signal text-white shadow-[0_10px_24px_-16px_rgba(26,110,255,0.9)] hover:bg-signal-deep'
                                }`}
                            >
                                Lihat tagihan
                            </Link>
                        </div>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-ink/10 bg-white shadow-[0_16px_40px_-28px_rgba(16,24,32,0.55)]">
                    <div className="h-1 bg-gradient-to-r from-amber-line via-signal-bright to-signal" />
                    <div className="p-4 sm:p-5">
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <p className="text-xs tracking-wide text-ink-soft uppercase">
                                    Pemasangan VPN
                                </p>
                                <h2 className="mt-1 text-base font-semibold text-ink">
                                    Jalankan skrip di terminal Winbox
                                </h2>
                                <p className="mt-1 text-sm text-ink-soft">
                                    Skrip ini membuat klien L2TP{' '}
                                    <span className="font-mono text-ink">{vpn?.interface || 'l2tp-vpn'}</span>{' '}
                                    ke server layanan. Menjalankan ulang akan mengganti klien dengan nama yang sama.
                                </p>
                            </div>
                            <Terminal className="h-5 w-5 shrink-0 text-signal-deep" />
                        </div>

                        {(vpn?.ports || []).length > 0 && (
                            <div className="mt-4 overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="text-xs tracking-wide text-ink-soft uppercase">
                                        <tr>
                                            <th className="py-2 pr-3">Dari internet</th>
                                            <th className="py-2 pr-3">Ke router Anda</th>
                                            <th className="py-2">Kegunaan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {vpn.ports.map((port) => (
                                            <tr key={port.public_port} className="border-t border-ink/10">
                                                <td className="py-2 pr-3 font-mono">
                                                    {vpn.server}:{port.public_port}
                                                </td>
                                                <td className="py-2 pr-3 font-mono">{port.dst_port}</td>
                                                <td className="py-2">
                                                    {port.label}
                                                    {port.note ? (
                                                        <span className="mt-0.5 block text-xs text-ink-soft">
                                                            {port.note}
                                                        </span>
                                                    ) : null}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                <p className="mt-2 text-xs text-ink-soft">
                                    Port selain 8291, 8728, 80, dan 22 harus diminta ke admin.
                                </p>
                            </div>
                        )}

                        <ol className="mt-4 list-decimal space-y-2 pl-5 text-sm text-ink">
                            {steps.map((step) => (
                                <li key={step}>{step}</li>
                            ))}
                        </ol>

                        <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-950">
                            Skrip tidak mengganti route internet yang sudah ada. Ia hanya membuat terowongan dan NAT
                            untuk lalu lintas yang keluar lewat {vpn?.interface || 'l2tp-vpn'}. Skrip berisi password
                            VPN Anda — jangan diteruskan ke orang lain.
                        </div>

                        {script ? (
                            <div className="mt-4">
                                <div className="mb-2 flex flex-wrap items-center justify-between gap-2 text-xs text-ink-soft">
                                    <p>
                                        Server {vpn?.server || '—'} · username {vpn?.username || '—'}
                                    </p>
                                    <button
                                        type="button"
                                        onClick={copyScript}
                                        className="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-ink/15 px-3 py-1.5 text-xs font-semibold text-ink hover:bg-mist"
                                    >
                                        {copied ? (
                                            <Check className="h-3.5 w-3.5" />
                                        ) : (
                                            <Copy className="h-3.5 w-3.5" />
                                        )}
                                        {copied ? 'Tersalin' : 'Salin skrip'}
                                    </button>
                                </div>
                                <pre className="overflow-x-auto rounded-xl bg-ink px-3 py-3 font-mono text-xs leading-relaxed text-white">
                                    {script}
                                </pre>
                            </div>
                        ) : (vpn?.extra_routers || []).length === 0 ? (
                            <p className="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-950">
                                {vpn?.trial?.active
                                    ? `Belum ada router. Isi nama di bawah. Router pertama gratis sampai ${vpn.trial.ends_at}.`
                                    : 'Belum ada router. Isi nama di bawah. Nama bebas, paling banyak 3, dan semuanya bisa dihapus. Router pertama mengikuti tagihan akun.'}
                            </p>
                        ) : null}

                        {(vpn?.extra_routers || []).map((item) => (
                            <div key={item.id || item.name} className="mt-4 border-t border-ink/10 pt-4">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <h3 className="text-sm font-semibold text-ink">
                                        Router {item.name}
                                        {item.included ? ' · tagihan akun' : ''}
                                    </h3>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            if (
                                                !window.confirm(
                                                    `Hapus router ${item.name}? Secret dan port di CHR ikut dihapus. Tagihan tidak dibatalkan, jadi router pengganti tidak ditagih lagi.`,
                                                )
                                            ) {
                                                return;
                                            }
                                            router.delete(`/portal/${token}/vpn/routers/${item.id}`, {
                                                preserveScroll: true,
                                            });
                                        }}
                                        className="cursor-pointer rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50"
                                    >
                                        Hapus router ini
                                    </button>
                                </div>
                                {item.usable ? (
                                    <>
                                        <p className="mt-1 text-sm text-ink-soft">
                                            Aktif sampai {item.service_until}. Tempel skrip ini di Winbox router tersebut.
                                        </p>
                                        <ul className="mt-2 space-y-1 text-sm text-ink">
                                            {(item.ports || []).map((port) => (
                                                <li key={port.public_port} className="font-mono text-xs">
                                                    {vpn.server}:{port.public_port} → {port.dst_port} {port.label}
                                                </li>
                                            ))}
                                        </ul>
                                        {item.script && (
                                            <pre className="mt-3 overflow-x-auto rounded-xl bg-ink px-3 py-3 font-mono text-xs leading-relaxed text-white">
                                                {item.script}
                                            </pre>
                                        )}
                                    </>
                                ) : (
                                    <p className="mt-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-950">
                                        Router ini belum bisa dipakai. Bayar tagihannya dulu, lalu skrip akan muncul di halaman ini.
                                    </p>
                                )}
                            </div>
                        ))}

                        {routerCount < routerLimit && (
                            <form
                                className="mt-4 border-t border-ink/10 pt-4"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    extra.post(`/portal/${token}/vpn/routers`, {
                                        preserveScroll: true,
                                        onSuccess: () => extra.reset(),
                                    });
                                }}
                            >
                                <h3 className="text-sm font-semibold text-ink">RouterOS baru</h3>
                                <p className="mt-1 text-sm text-ink-soft">
                                    {vpn?.trial?.active && nextWithoutInvoice
                                        ? `Nama ini bebas. Router pertama gratis sampai ${vpn.trial.ends_at}. Setelah itu tagihan dikirim ke WhatsApp.`
                                        : nextWithoutInvoice
                                          ? 'Nama ini bebas. Router ini tidak membuat tagihan baru.'
                                          : 'Nama ini menjadi username VPN router tersebut. Tagihan muncul hari ini dan harus lunas dulu. Skrip baru muncul setelah lunas.'}
                                </p>
                                <label className="mt-3 block text-sm font-medium text-ink">
                                    Nama router
                                    <input
                                        type="text"
                                        value={extra.data.name}
                                        onChange={(event) => extra.setData('name', event.target.value)}
                                        className="mt-1.5 w-full rounded-xl border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal"
                                        placeholder="Misalnya toko-pusat"
                                        required
                                    />
                                </label>
                                {extra.errors.name && (
                                    <p className="mt-2 text-xs text-red-600">{extra.errors.name}</p>
                                )}
                                <button
                                    type="submit"
                                    disabled={extra.processing}
                                    className="mt-3 cursor-pointer rounded-xl bg-ink px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60"
                                >
                                    {extra.processing
                                        ? 'Menyimpan...'
                                        : nextWithoutInvoice
                                          ? 'Buat router tanpa tagihan baru'
                                          : 'Buat router'}
                                </button>
                            </form>
                        )}
                    </div>
                </section>
            </div>
        </PortalLayout>
    );
}
