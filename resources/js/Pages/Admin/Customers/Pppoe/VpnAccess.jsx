import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const fieldClass =
    'mt-1.5 w-full border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal';

function CopyButton({ text, label }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        if (!text) return;
        try {
            await navigator.clipboard.writeText(text);
        } catch {
            const area = document.createElement('textarea');
            area.value = text;
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
        <button
            type="button"
            onClick={copy}
            className="btn-action btn-action-xs btn-secondary"
        >
            {copied ? 'Tersalin' : label}
        </button>
    );
}

export default function VpnAccess({ customerId, access }) {
    const custom = useForm({ dst_port: '', note: '' });
    const [pushing, setPushing] = useState(false);

    if (!access) return null;

    const push = () => {
        setPushing(true);
        router.post(`/admin/customers/pppoe/${customerId}/vpn/push`, {}, {
            preserveScroll: true,
            onFinish: () => setPushing(false),
        });
    };

    const removePort = (id) => {
        if (!window.confirm('Hapus port khusus ini dari CHR?')) return;
        router.delete(`/admin/customers/pppoe/${customerId}/vpn/ports/${id}`, {
            preserveScroll: true,
        });
    };

    return (
        <section className="mt-6 space-y-4 border border-ink/10 bg-white p-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-sm font-semibold text-ink">Akses VPN dan port forward</h2>
                    <p className="mt-1 text-sm text-ink-soft">
                        Alamat VPN {access.address || '—'} · seri port {access.series || '—'} · server{' '}
                        {access.server || '—'}. Port bawaan 8291, 8728, 80, dan 22. Port 22 di sisi
                        pelanggan boleh diteruskan ke perangkat mana pun. Port lain hanya lewat admin.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={push}
                    disabled={pushing || !access.chr_ready}
                    className="btn-action btn-action-sm btn-primary"
                >
                    {pushing ? 'Mengirim...' : 'Push ke CHR'}
                </button>
            </div>
            {!access.chr_ready && (
                <p className="text-sm text-amber-800">
                    Kredensial SSH CHR belum tersimpan, jadi tombol push belum bisa dipakai. Skrip tetap bisa disalin.
                </p>
            )}

            <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                    <thead className="text-xs tracking-wide text-ink-soft uppercase">
                        <tr>
                            <th className="py-2 pr-3">Port publik</th>
                            <th className="py-2 pr-3">Port pelanggan</th>
                            <th className="py-2 pr-3">Kegunaan</th>
                            <th className="py-2 pr-3">CHR</th>
                            <th className="py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {(access.ports || []).map((port) => (
                            <tr key={port.id} className="border-t border-ink/10">
                                <td className="py-2 pr-3 font-mono">{access.server}:{port.public_port}</td>
                                <td className="py-2 pr-3 font-mono">{port.dst_port}</td>
                                <td className="py-2 pr-3">
                                    {port.label}
                                    {port.note ? (
                                        <span className="mt-0.5 block text-xs text-ink-soft">{port.note}</span>
                                    ) : null}
                                </td>
                                <td className="py-2 pr-3">{port.pushed_at ? 'Sudah' : 'Belum'}</td>
                                <td className="py-2 text-right">
                                    {port.kind === 'custom' && (
                                        <button
                                            type="button"
                                            onClick={() => removePort(port.id)}
                                            className="text-xs font-semibold text-red-700 hover:underline"
                                        >
                                            Hapus
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <form
                className="grid gap-3 border border-ink/10 bg-mist/40 p-4 sm:grid-cols-[8rem_1fr_auto] sm:items-end"
                onSubmit={(event) => {
                    event.preventDefault();
                    custom.post(`/admin/customers/pppoe/${customerId}/vpn/ports`, {
                        preserveScroll: true,
                        onSuccess: () => custom.reset(),
                    });
                }}
            >
                <label className="block text-sm font-medium text-ink">
                    Port tujuan
                    <input
                        type="number"
                        min="1"
                        max="65535"
                        value={custom.data.dst_port}
                        onChange={(event) => custom.setData('dst_port', event.target.value)}
                        className={fieldClass}
                        required
                    />
                </label>
                <label className="block text-sm font-medium text-ink">
                    Catatan
                    <input
                        type="text"
                        value={custom.data.note}
                        onChange={(event) => custom.setData('note', event.target.value)}
                        className={fieldClass}
                        placeholder="Misalnya CCTV pelanggan"
                    />
                </label>
                <button
                    type="submit"
                    disabled={custom.processing}
                    className="btn-action btn-action-sm btn-secondary"
                >
                    {custom.processing ? 'Menyimpan...' : 'Buat & push'}
                </button>
                {custom.errors.dst_port && (
                    <p className="text-xs text-red-600 sm:col-span-3">{custom.errors.dst_port}</p>
                )}
            </form>

            <div className="grid gap-4 lg:grid-cols-2">
                <div>
                    <div className="mb-2 flex items-center justify-between gap-2">
                        <h3 className="text-sm font-semibold text-ink">Skrip server</h3>
                        <CopyButton text={access.server_script} label="Salin skrip server" />
                    </div>
                    <pre className="max-h-80 overflow-auto bg-ink p-3 font-mono text-xs leading-relaxed text-white">
                        {access.server_script}
                    </pre>
                </div>
                <div>
                    <div className="mb-2 flex items-center justify-between gap-2">
                        <h3 className="text-sm font-semibold text-ink">Skrip klien</h3>
                        <CopyButton text={access.client_script} label="Salin skrip klien" />
                    </div>
                    <pre className="max-h-80 overflow-auto bg-ink p-3 font-mono text-xs leading-relaxed text-white">
                        {access.client_script}
                    </pre>
                </div>
            </div>
        </section>
    );
}
