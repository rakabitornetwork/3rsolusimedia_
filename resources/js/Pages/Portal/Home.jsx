import { Link } from '@inertiajs/react';
import { CreditCard, Radio, Router, Thermometer, Users } from 'lucide-react';
import PortalLiveTraffic from '../../Components/Portal/LiveTrafficCard';
import PortalLayout from '../../Layouts/PortalLayout';
import {
    onlineTone,
    rxPowerTone,
    temperatureTone,
} from '../../Utils/genieacsMetrics';

function MetricCard({ icon: Icon, label, value, tone }) {
    return (
        <div className={`border p-4 ${tone.card}`}>
            <div className="flex items-center gap-2">
                <Icon className={`h-4 w-4 ${tone.icon}`} />
                <p className="text-xs font-semibold tracking-wide text-ink-soft uppercase">
                    {label}
                </p>
            </div>
            <p className={`mt-2 text-lg font-semibold ${tone.text}`}>{value || '—'}</p>
        </div>
    );
}

export default function Home({
    branding,
    token,
    customer,
    billing,
    device,
    device_available,
    device_message,
}) {
    const online = onlineTone(device?.online);
    const rx = rxPowerTone(device?.rx_power);
    const temp = temperatureTone(device?.temperature);

    return (
        <PortalLayout
            branding={branding}
            customer={customer}
            token={token}
            title="Beranda"
            active="home"
        >
            <div className="space-y-4">
                <section className="border border-ink/10 bg-white p-5">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <p className="text-xs tracking-wide text-ink-soft uppercase">Tagihan</p>
                            <h2 className="mt-1 text-base font-semibold text-ink">
                                {billing?.unpaid_count
                                    ? `${billing.unpaid_count} tagihan belum bayar`
                                    : 'Tidak ada tagihan aktif'}
                            </h2>
                            <p className="mt-1 text-sm text-ink-soft">
                                {billing?.unpaid_count
                                    ? `Total ${billing.unpaid_total_label}`
                                    : 'Semua tagihan sudah lunas.'}
                            </p>
                        </div>
                        <CreditCard className="h-5 w-5 text-signal-deep" />
                    </div>
                    <Link
                        href={`/bayar/${token}/tagihan`}
                        className="mt-4 inline-flex bg-signal px-4 py-2.5 text-sm font-semibold text-white hover:bg-signal-deep"
                    >
                        Lihat tagihan
                    </Link>
                </section>

                <section className="border border-ink/10 bg-white p-5">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <p className="text-xs tracking-wide text-ink-soft uppercase">
                                Perangkat ONU
                            </p>
                            <h2 className="mt-1 text-base font-semibold text-ink">
                                {device_available
                                    ? device?.online
                                        ? 'Online'
                                        : 'Offline / belum inform'
                                    : 'Belum terhubung'}
                            </h2>
                            <p className="mt-1 text-sm text-ink-soft">
                                {device_available
                                    ? `${device?.model || 'ONU'} · ${device?.ssid || 'SSID —'}`
                                    : device_message}
                            </p>
                        </div>
                        <Router className="h-5 w-5 text-signal-deep" />
                    </div>

                    {device_available && (
                        <div className="mt-4 grid grid-cols-3 gap-3">
                            <MetricCard
                                icon={Radio}
                                label="RX Power"
                                value={device.rx_power_label}
                                tone={rx}
                            />
                            <MetricCard
                                icon={Thermometer}
                                label="Suhu"
                                value={device.temperature_label}
                                tone={temp}
                            />
                            <MetricCard
                                icon={Users}
                                label="Perangkat WiFi"
                                value={String(device.connected_count ?? 0)}
                                tone={online}
                            />
                        </div>
                    )}

                    <Link
                        href={`/bayar/${token}/perangkat`}
                        className="mt-4 inline-flex border border-ink/15 px-4 py-2.5 text-sm font-semibold text-ink hover:bg-mist"
                    >
                        Kelola perangkat & WiFi
                    </Link>
                </section>

                <PortalLiveTraffic token={token} />
            </div>
        </PortalLayout>
    );
}
