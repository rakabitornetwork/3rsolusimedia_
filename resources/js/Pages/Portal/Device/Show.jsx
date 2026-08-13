import { router, useForm } from '@inertiajs/react';
import {
    Radio,
    RefreshCw,
    RotateCcw,
    Thermometer,
    Users,
    Wifi,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import PortalLayout from '../../../Layouts/PortalLayout';
import {
    onlineTone,
    redamanTone,
    rxPowerTone,
    temperatureTone,
} from '../../../Utils/genieacsMetrics';

const fieldClass =
    'mt-1.5 w-full border border-ink/15 bg-white px-3 py-2.5 text-sm outline-none focus:border-signal';

function MetricCard({ icon: Icon, label, value, tone }) {
    return (
        <div className={`border p-4 ${tone.card}`}>
            <div className="flex items-center gap-2">
                <Icon className={`h-4 w-4 ${tone.icon}`} />
                <p className="text-xs font-semibold tracking-wide text-ink-soft uppercase">
                    {label}
                </p>
            </div>
            <p className={`mt-2 text-base font-semibold ${tone.text}`}>{value || '—'}</p>
        </div>
    );
}

export default function Show({
    branding,
    token,
    customer,
    device,
    device_available,
    device_message,
}) {
    const [busy, setBusy] = useState('');
    const { data, setData, post, processing, errors, reset } = useForm({
        ssid: device?.ssid || '',
        password: '',
    });

    useEffect(() => {
        setData('ssid', device?.ssid || '');
    }, [device?.ssid]);

    const online = onlineTone(device?.online);
    const rx = rxPowerTone(device?.rx_power);
    const redaman = redamanTone(device?.redaman);
    const temp = temperatureTone(device?.temperature);

    const refresh = () => {
        if (busy) return;
        if (!window.confirm('Muat ulang data perangkat dari ONU?')) return;
        setBusy('refresh');
        router.post(
            `/bayar/${token}/perangkat/refresh`,
            {},
            { preserveScroll: true, onFinish: () => setBusy('') },
        );
    };

    const reboot = () => {
        if (busy) return;
        if (
            !window.confirm(
                'Restart ONU sekarang? Koneksi internet dan WiFi akan terputus sementara.',
            )
        ) {
            return;
        }
        setBusy('reboot');
        router.post(
            `/bayar/${token}/perangkat/reboot`,
            {},
            { preserveScroll: true, onFinish: () => setBusy('') },
        );
    };

    const saveWifi = (e) => {
        e.preventDefault();
        if (!window.confirm('Simpan perubahan WiFi ke ONU?')) return;
        post(`/bayar/${token}/perangkat/wifi`, {
            preserveScroll: true,
            onSuccess: () => reset('password'),
        });
    };

    return (
        <PortalLayout
            branding={branding}
            customer={customer}
            token={token}
            title="Perangkat"
            active="device"
        >
            {!device_available ? (
                <div className="border border-amber-200 bg-amber-50 px-4 py-5 text-sm text-amber-900">
                    {device_message ||
                        'Perangkat belum terhubung ke sistem monitoring. Hubungi admin.'}
                </div>
            ) : (
                <div className="space-y-4">
                    <section className="border border-ink/10 bg-white p-5">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="text-xs tracking-wide text-ink-soft uppercase">
                                    Status ONU
                                </p>
                                <h2 className={`mt-1 text-lg font-semibold ${online.text}`}>
                                    {device.online ? 'Online' : 'Offline / belum inform'}
                                </h2>
                                <p className="mt-1 text-sm text-ink-soft">
                                    {[device.manufacturer, device.model].filter(Boolean).join(' · ') ||
                                        'ONU'}
                                    {device.serial ? ` · SN ${device.serial}` : ''}
                                </p>
                                <p className="mt-1 text-xs text-ink-soft">
                                    Inform terakhir: {device.last_inform_label || '—'}
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    onClick={refresh}
                                    disabled={Boolean(busy)}
                                    className="inline-flex items-center gap-1.5 border border-ink/15 px-3 py-2 text-xs font-semibold text-ink hover:bg-mist disabled:opacity-50"
                                >
                                    <RefreshCw
                                        className={`h-3.5 w-3.5 ${busy === 'refresh' ? 'animate-spin' : ''}`}
                                    />
                                    Muat ulang
                                </button>
                                <button
                                    type="button"
                                    onClick={reboot}
                                    disabled={Boolean(busy)}
                                    className="inline-flex items-center gap-1.5 border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100 disabled:opacity-50"
                                >
                                    <RotateCcw className="h-3.5 w-3.5" />
                                    Restart ONU
                                </button>
                            </div>
                        </div>

                        <div className="mt-4 grid grid-cols-2 gap-3">
                            <MetricCard
                                icon={Radio}
                                label="RX Power"
                                value={device.rx_power_label}
                                tone={rx}
                            />
                            <MetricCard
                                icon={Radio}
                                label="Redaman"
                                value={device.redaman_label}
                                tone={redaman}
                            />
                            <MetricCard
                                icon={Thermometer}
                                label="Suhu"
                                value={device.temperature_label}
                                tone={temp}
                            />
                            <MetricCard
                                icon={Wifi}
                                label="SSID"
                                value={device.ssid || '—'}
                                tone={online}
                            />
                        </div>
                    </section>

                    <section className="border border-ink/10 bg-white p-5">
                        <h3 className="text-sm font-semibold text-ink">Ubah WiFi</h3>
                        <p className="mt-1 text-sm text-ink-soft">
                            Isi SSID, password, atau keduanya. Password saat ini tidak ditampilkan.
                        </p>
                        <form onSubmit={saveWifi} className="mt-4 space-y-3">
                            <label className="block text-sm font-medium text-ink">
                                Nama WiFi (SSID)
                                <input
                                    type="text"
                                    value={data.ssid}
                                    onChange={(e) => setData('ssid', e.target.value)}
                                    className={fieldClass}
                                    maxLength={32}
                                    placeholder={device.ssid || 'SSID baru'}
                                />
                                {errors.ssid && (
                                    <p className="mt-1 text-xs text-red-600">{errors.ssid}</p>
                                )}
                            </label>
                            <label className="block text-sm font-medium text-ink">
                                Password WiFi baru
                                <input
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    className={fieldClass}
                                    minLength={8}
                                    maxLength={63}
                                    placeholder="Minimal 8 karakter (opsional)"
                                    autoComplete="new-password"
                                />
                                {errors.password && (
                                    <p className="mt-1 text-xs text-red-600">{errors.password}</p>
                                )}
                            </label>
                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full bg-signal px-4 py-2.5 text-sm font-semibold text-white hover:bg-signal-deep disabled:opacity-60"
                            >
                                {processing ? 'Menyimpan...' : 'Simpan WiFi'}
                            </button>
                        </form>
                    </section>

                    <section className="border border-ink/10 bg-white p-5">
                        <div className="flex items-center justify-between gap-3">
                            <h3 className="text-sm font-semibold text-ink">
                                Perangkat terhubung
                            </h3>
                            <span className="inline-flex items-center gap-1 text-xs font-semibold text-ink-soft">
                                <Users className="h-3.5 w-3.5" />
                                {device.connected_count ?? 0}
                            </span>
                        </div>

                        {device.connected_clients?.length ? (
                            <ul className="mt-3 divide-y divide-ink/5">
                                {device.connected_clients.map((client, index) => (
                                    <li key={`${client.mac || client.ip || index}`} className="py-3">
                                        <p className="text-sm font-medium text-ink">
                                            {client.hostname || client.name || 'Perangkat'}
                                        </p>
                                        <p className="text-xs text-ink-soft">
                                            {[client.ip, client.mac, client.ssid]
                                                .filter(Boolean)
                                                .join(' · ') || '—'}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="mt-3 text-sm text-ink-soft">
                                Belum ada data klien. Coba Muat ulang setelah ONU online.
                            </p>
                        )}
                    </section>
                </div>
            )}
        </PortalLayout>
    );
}
