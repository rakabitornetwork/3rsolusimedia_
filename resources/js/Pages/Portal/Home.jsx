import { Link } from '@inertiajs/react';
import { CreditCard, Radio, Router, Thermometer, Users } from 'lucide-react';
import DeviceMetricCard, {
    splitMetricLabel,
} from '../../Components/Portal/DeviceMetricCard';
import PortalLiveTraffic from '../../Components/Portal/LiveTrafficCard';
import PortalLayout from '../../Layouts/PortalLayout';
import {
    onlineTone,
    rxPowerTone,
    temperatureTone,
} from '../../Utils/genieacsMetrics';

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
    const rxParts = splitMetricLabel(device?.rx_power_label);
    const tempParts = splitMetricLabel(device?.temperature_label);

    return (
        <PortalLayout
            branding={branding}
            customer={customer}
            token={token}
            title="Beranda"
            active="home"
        >
            <div className="space-y-4">
                <section className="border border-ink/10 bg-white p-4 sm:p-5">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
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
                        <CreditCard className="h-5 w-5 shrink-0 text-signal-deep" />
                    </div>
                    <Link
                        href={`/bayar/${token}/tagihan`}
                        className="mt-4 inline-flex bg-signal px-4 py-2.5 text-sm font-semibold text-white hover:bg-signal-deep"
                    >
                        Lihat tagihan
                    </Link>
                </section>

                <section className="border border-ink/10 bg-white p-4 sm:p-5">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            <p className="text-xs tracking-wide text-ink-soft uppercase">
                                Perangkat ONU
                            </p>
                            <h2 className="mt-1 text-base font-semibold text-ink">
                                {device_available
                                    ? device?.online
                                        ? 'Online'
                                        : 'Offline'
                                    : 'Belum terhubung'}
                            </h2>
                            <p className="mt-1 truncate text-sm text-ink-soft">
                                {device_available
                                    ? [device?.model || 'ONU', device?.ssid]
                                          .filter(Boolean)
                                          .join(' · ')
                                    : device_message}
                            </p>
                        </div>
                        <Router className="h-5 w-5 shrink-0 text-signal-deep" />
                    </div>

                    {device_available && (
                        <div className="mt-3 grid grid-cols-3 gap-2 sm:mt-4 sm:gap-3">
                            <DeviceMetricCard
                                icon={Radio}
                                label="RX Power"
                                shortLabel="RX"
                                value={rxParts.value}
                                unit={rxParts.unit}
                                tone={rx}
                                title="RX Power optik"
                            />
                            <DeviceMetricCard
                                icon={Thermometer}
                                label="Suhu"
                                shortLabel="Suhu"
                                value={tempParts.value}
                                unit={tempParts.unit}
                                tone={temp}
                                title="Suhu ONU"
                            />
                            <DeviceMetricCard
                                icon={Users}
                                label="Perangkat WiFi"
                                shortLabel="Klien"
                                value={String(device.connected_count ?? 0)}
                                tone={online}
                                title="Jumlah perangkat WiFi terhubung"
                            />
                        </div>
                    )}

                    <Link
                        href={`/bayar/${token}/perangkat`}
                        className="mt-4 inline-flex w-full items-center justify-center border border-ink/15 px-4 py-2.5 text-sm font-semibold text-ink hover:bg-mist sm:w-auto"
                    >
                        Kelola perangkat & WiFi
                    </Link>
                </section>

                <PortalLiveTraffic token={token} />
            </div>
        </PortalLayout>
    );
}
