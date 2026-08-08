import { Head, router } from '@inertiajs/react';
import {
    Activity,
    ArrowDownToLine,
    ArrowUpFromLine,
    Gauge,
    List,
    LoaderCircle,
    Map as MapIcon,
    MapPin,
    Radio,
    Search,
    Signal,
    Thermometer,
    WifiOff,
    X,
} from 'lucide-react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import 'leaflet/dist/leaflet.css';
import AdminLayout from '../../../Layouts/AdminLayout';
import useDebouncedCallback from '../../../hooks/useDebouncedCallback';
import {
    onlineTone,
    redamanTone,
    rxPowerTone,
    temperatureTone,
} from '../../../Utils/genieacsMetrics';

const DEFAULT_CENTER = [-2.5489, 118.0149];
const DEFAULT_ZOOM = 5;
const POLL_SECONDS = 3;
const SPARK_POINTS = 24;

const STATUS_OPTIONS = [
    { value: 'all', label: 'Semua status' },
    { value: 'active', label: 'Aktif' },
    { value: 'isolated', label: 'Isolir' },
    { value: 'disabled', label: 'Nonaktif' },
    { value: 'grace', label: 'Grace' },
    { value: 'overdue', label: 'Lewat tempo' },
];

const STATUS_LABEL = {
    active: 'Aktif',
    isolated: 'Isolir',
    disabled: 'Nonaktif',
};

function formatBitrate(bps) {
    if (bps == null || Number.isNaN(Number(bps))) return '0 bps';
    let n = Number(bps);
    const units = ['bps', 'Kbps', 'Mbps', 'Gbps'];
    let i = 0;
    while (n >= 1000 && i < units.length - 1) {
        n /= 1000;
        i += 1;
    }
    return `${n.toFixed(i === 0 ? 0 : 2)} ${units[i]}`;
}

function markerColor(customer) {
    if (customer.status === 'isolated') return '#e11d48';
    if (customer.status === 'disabled') return '#64748b';
    if (customer.session_online) return '#059669';
    const rx = customer.optical?.rx_power;
    if (rx != null) {
        const tone = rxPowerTone(rx).key;
        if (tone === 'bad') return '#e11d48';
        if (tone === 'warn') return '#d97706';
        return '#0d9488';
    }
    return '#2563eb';
}

function statusBadgeClass(status) {
    if (status === 'isolated') return 'bg-rose-50 text-rose-700';
    if (status === 'disabled') return 'bg-slate-100 text-slate-600';
    return 'bg-emerald-50 text-emerald-700';
}

function MetricBox({ icon: Icon, label, value, tone }) {
    return (
        <div className={`border px-3 py-2.5 ${tone?.card || 'border-ink/10 bg-mist/40'}`}>
            <div className="flex items-center gap-1.5 text-[11px] font-semibold tracking-wide text-ink-soft uppercase">
                <Icon className={`h-3.5 w-3.5 ${tone?.icon || 'text-ink-soft'}`} strokeWidth={2} />
                {label}
            </div>
            <p className={`mt-1 text-sm font-semibold ${tone?.text || 'text-ink'}`}>{value}</p>
        </div>
    );
}

function Sparkline({ values, stroke = '#0d9488' }) {
    const points = values?.length ? values : [0];
    const max = Math.max(...points, 1);
    const w = 120;
    const h = 36;
    const step = points.length > 1 ? w / (points.length - 1) : w;
    const path = points
        .map((v, i) => {
            const x = i * step;
            const y = h - (v / max) * (h - 4) - 2;
            return `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(' ');

    return (
        <svg viewBox={`0 0 ${w} ${h}`} className="h-9 w-full" preserveAspectRatio="none">
            <path d={path} fill="none" stroke={stroke} strokeWidth="1.75" />
        </svg>
    );
}

function useCustomerTrafficPoll(customerId) {
    const [traffic, setTraffic] = useState(null);
    const [history, setHistory] = useState({ rx: [], tx: [] });
    const [error, setError] = useState('');
    const [online, setOnline] = useState(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!customerId) {
            setTraffic(null);
            setHistory({ rx: [], tx: [] });
            setError('');
            setOnline(null);
            setLoading(false);
            return undefined;
        }

        let cancelled = false;
        let busy = false;

        const fetchTraffic = async () => {
            if (busy || cancelled) return;
            busy = true;
            setLoading(true);

            try {
                const response = await fetch(`/admin/network/map/customers/${customerId}/traffic`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                const payload = await response.json();
                if (cancelled) return;

                setOnline(Boolean(payload.online));

                if (!payload.ok) {
                    setError(payload.message || 'Traffic tidak tersedia');
                    setTraffic(null);
                    return;
                }

                setError('');
                setTraffic(payload.data);
                setHistory((prev) => ({
                    rx: [...prev.rx, payload.data.rx_bps].slice(-SPARK_POINTS),
                    tx: [...prev.tx, payload.data.tx_bps].slice(-SPARK_POINTS),
                }));
            } catch {
                if (!cancelled) {
                    setError('Tidak bisa mengambil live traffic');
                    setOnline(null);
                }
            } finally {
                busy = false;
                if (!cancelled) setLoading(false);
            }
        };

        setHistory({ rx: [], tx: [] });
        setTraffic(null);
        setError('');
        fetchTraffic();
        const timer = window.setInterval(fetchTraffic, POLL_SECONDS * 1000);

        return () => {
            cancelled = true;
            window.clearInterval(timer);
        };
    }, [customerId]);

    return { traffic, history, error, online, loading };
}

function NetworkMapView({ customers, selectedId, onSelect }) {
    const mapId = useId().replace(/:/g, '');
    const mapRef = useRef(null);
    const markersRef = useRef(new Map());
    const leafletRef = useRef(null);
    const onSelectRef = useRef(onSelect);
    const [mapReady, setMapReady] = useState(false);
    const boundsKeyRef = useRef('');

    useEffect(() => {
        onSelectRef.current = onSelect;
    }, [onSelect]);

    useEffect(() => {
        let cancelled = false;

        (async () => {
            const leafletModule = await import('leaflet');
            const L = leafletModule.default || leafletModule;
            if (cancelled) return;

            leafletRef.current = L;
            delete L.Icon.Default.prototype._getIconUrl;
            L.Icon.Default.mergeOptions({
                iconRetinaUrl: markerIcon2x,
                iconUrl: markerIcon,
                shadowUrl: markerShadow,
            });

            if (mapRef.current) {
                setMapReady(true);
                return;
            }

            const map = L.map(mapId, {
                center: DEFAULT_CENTER,
                zoom: DEFAULT_ZOOM,
                scrollWheelZoom: true,
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap',
                maxZoom: 19,
            }).addTo(map);

            mapRef.current = map;
            setMapReady(true);
        })();

        return () => {
            cancelled = true;
            if (mapRef.current) {
                mapRef.current.remove();
                mapRef.current = null;
                markersRef.current = new Map();
            }
            setMapReady(false);
        };
    }, [mapId]);

    useEffect(() => {
        const map = mapRef.current;
        const L = leafletRef.current;
        if (!mapReady || !map || !L) return;

        const nextIds = new Set();
        const bounds = [];

        customers.forEach((customer) => {
            if (!customer.on_map) return;
            nextIds.add(customer.id);
            bounds.push([customer.latitude, customer.longitude]);

            const color = markerColor(customer);
            const selected = customer.id === selectedId;
            const html = `<span style="
                display:block;width:${selected ? 18 : 14}px;height:${selected ? 18 : 14}px;
                border-radius:9999px;background:${color};
                border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.35);
                transform:translate(-50%,-50%);
            "></span>`;

            let marker = markersRef.current.get(customer.id);
            if (!marker) {
                marker = L.marker([customer.latitude, customer.longitude], {
                    icon: L.divIcon({
                        className: '',
                        html,
                        iconSize: [0, 0],
                        iconAnchor: [0, 0],
                    }),
                }).addTo(map);

                marker.on('click', () => onSelectRef.current?.(customer.id));
                markersRef.current.set(customer.id, marker);
            } else {
                marker.setLatLng([customer.latitude, customer.longitude]);
                marker.setIcon(
                    L.divIcon({
                        className: '',
                        html,
                        iconSize: [0, 0],
                        iconAnchor: [0, 0],
                    }),
                );
            }

            marker.bindTooltip(
                `<strong>${customer.name}</strong><br/><span style="opacity:.8">${customer.username}</span>`,
                { direction: 'top', offset: [0, -10] },
            );
        });

        for (const [id, marker] of markersRef.current.entries()) {
            if (!nextIds.has(id)) {
                map.removeLayer(marker);
                markersRef.current.delete(id);
            }
        }

        const nextBoundsKey = bounds.map((b) => b.join(',')).join('|');
        if (nextBoundsKey !== boundsKeyRef.current) {
            boundsKeyRef.current = nextBoundsKey;
            if (bounds.length === 1) {
                map.setView(bounds[0], 16);
            } else if (bounds.length > 1) {
                map.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 });
            }
        }

        window.setTimeout(() => map.invalidateSize(), 50);
    }, [customers, selectedId, mapReady]);

    useEffect(() => {
        const map = mapRef.current;
        if (!mapReady || !map || !selectedId) return;
        const marker = markersRef.current.get(selectedId);
        if (marker) {
            map.panTo(marker.getLatLng(), { animate: true });
        }
    }, [selectedId, mapReady]);

    return <div id={mapId} className="h-full min-h-[320px] w-full bg-mist" />;
}

function DetailPanel({ customer, onClose, mobileSheet = false }) {
    const poll = useCustomerTrafficPoll(customer?.id);
    const optical = customer?.optical;
    const tempTone = temperatureTone(optical?.temperature);
    const rxTone = rxPowerTone(optical?.rx_power);
    const redTone = redamanTone(optical?.redaman);
    const ontTone = onlineTone(optical?.online_ont);

    if (!customer) return null;

    const panel = (
        <>
            <div className="flex items-start justify-between gap-3 border-b border-ink/10 px-4 py-3">
                <div className="min-w-0">
                    <p className="truncate font-display text-base font-bold text-ink">{customer.name}</p>
                    <p className="mt-0.5 font-mono text-xs text-ink-soft">{customer.username}</p>
                </div>
                <button
                    type="button"
                    onClick={onClose}
                    className="rounded-md p-1.5 text-ink-soft hover:bg-mist hover:text-ink"
                    aria-label="Tutup detail"
                >
                    <X className="h-4 w-4" />
                </button>
            </div>

            <div className="flex-1 space-y-4 overflow-y-auto px-4 py-4">
                <div className="flex flex-wrap gap-2">
                    <span className={`px-2 py-1 text-[11px] font-semibold ${statusBadgeClass(customer.status)}`}>
                        {STATUS_LABEL[customer.status] || customer.status}
                    </span>
                    <span
                        className={`px-2 py-1 text-[11px] font-semibold ${
                            customer.session_online || poll.online
                                ? 'bg-emerald-50 text-emerald-700'
                                : 'bg-slate-100 text-slate-600'
                        }`}
                    >
                        {customer.session_online || poll.online ? 'PPPoE online' : 'PPPoE offline'}
                    </span>
                    {!customer.on_map && (
                        <span className="bg-amber-50 px-2 py-1 text-[11px] font-semibold text-amber-700">
                            Tanpa GPS
                        </span>
                    )}
                </div>

                <dl className="grid grid-cols-1 gap-2 text-sm">
                    <div className="flex justify-between gap-3 border-b border-ink/5 py-1.5">
                        <dt className="text-ink-soft">Paket</dt>
                        <dd className="text-right font-medium text-ink">{customer.package?.name || '—'}</dd>
                    </div>
                    <div className="flex justify-between gap-3 border-b border-ink/5 py-1.5">
                        <dt className="text-ink-soft">Router</dt>
                        <dd className="text-right font-medium text-ink">{customer.router?.name || '—'}</dd>
                    </div>
                    <div className="flex justify-between gap-3 border-b border-ink/5 py-1.5">
                        <dt className="text-ink-soft">Telepon</dt>
                        <dd className="text-right font-medium text-ink">{customer.phone || '—'}</dd>
                    </div>
                    <div className="flex justify-between gap-3 py-1.5">
                        <dt className="text-ink-soft">Alamat</dt>
                        <dd className="max-w-[60%] text-right font-medium text-ink">
                            {customer.address || '—'}
                        </dd>
                    </div>
                </dl>

                <div>
                    <h3 className="text-xs font-semibold tracking-wide text-ink-soft uppercase">
                        Optik ONT
                    </h3>
                    {optical?.matched ? (
                        <div className="mt-2 grid grid-cols-2 gap-2">
                            <MetricBox
                                icon={Thermometer}
                                label="Suhu"
                                value={optical.temperature_label || '—'}
                                tone={tempTone}
                            />
                            <MetricBox
                                icon={Signal}
                                label="RX Power"
                                value={optical.rx_power_label || '—'}
                                tone={rxTone}
                            />
                            <MetricBox
                                icon={Radio}
                                label="TX Power"
                                value={optical.tx_power_label || '—'}
                                tone={{
                                    text: 'text-ink',
                                    icon: 'text-sky-600',
                                    card: 'border-sky-100 bg-sky-50/60',
                                }}
                            />
                            <MetricBox
                                icon={Gauge}
                                label="Redaman"
                                value={optical.redaman_label || '—'}
                                tone={redTone}
                            />
                            <div className="col-span-2 flex items-center justify-between border border-ink/10 px-3 py-2 text-xs">
                                <span className="text-ink-soft">Serial / ONT</span>
                                <span className={`font-semibold ${ontTone.text}`}>
                                    {optical.serial || '—'}
                                    {optical.online_ont ? ' · online' : ' · offline'}
                                </span>
                            </div>
                        </div>
                    ) : (
                        <p className="mt-2 border border-dashed border-ink/15 bg-mist/50 px-3 py-3 text-xs leading-relaxed text-ink-soft">
                            ONT tidak cocok / username PPPoE tidak ditemukan di GenieACS.
                        </p>
                    )}
                </div>

                <div>
                    <div className="flex items-center justify-between gap-2">
                        <h3 className="text-xs font-semibold tracking-wide text-ink-soft uppercase">
                            Live trafik
                        </h3>
                        {poll.loading && (
                            <LoaderCircle className="h-3.5 w-3.5 animate-spin text-ink-soft" />
                        )}
                    </div>

                    {poll.error && !poll.traffic ? (
                        <p className="mt-2 flex items-start gap-2 border border-ink/10 bg-mist/40 px-3 py-3 text-xs text-ink-soft">
                            <WifiOff className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                            {poll.error}
                        </p>
                    ) : (
                        <div className="mt-2 space-y-3 border border-ink/10 p-3">
                            <div>
                                <div className="flex items-center justify-between text-xs">
                                    <span className="inline-flex items-center gap-1 font-semibold text-teal-700">
                                        <ArrowDownToLine className="h-3.5 w-3.5" />
                                        RX
                                    </span>
                                    <span className="font-mono font-semibold text-ink">
                                        {formatBitrate(poll.traffic?.rx_bps)}
                                    </span>
                                </div>
                                <Sparkline values={poll.history.rx} stroke="#0d9488" />
                            </div>
                            <div>
                                <div className="flex items-center justify-between text-xs">
                                    <span className="inline-flex items-center gap-1 font-semibold text-sky-700">
                                        <ArrowUpFromLine className="h-3.5 w-3.5" />
                                        TX
                                    </span>
                                    <span className="font-mono font-semibold text-ink">
                                        {formatBitrate(poll.traffic?.tx_bps)}
                                    </span>
                                </div>
                                <Sparkline values={poll.history.tx} stroke="#0284c7" />
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </>
    );

    if (mobileSheet) {
        return (
            <div className="absolute inset-x-0 bottom-0 z-[500] flex max-h-[70%] flex-col border-t border-ink/10 bg-white shadow-[0_-8px_30px_rgba(0,0,0,.12)] lg:hidden">
                {panel}
            </div>
        );
    }

    return (
        <aside className="hidden w-full flex-col border-t border-ink/10 bg-white lg:flex lg:w-[340px] lg:border-t-0 lg:border-l">
            {panel}
        </aside>
    );
}

export default function MapPage({
    customers = [],
    filters = {},
    stats = {},
    optical_meta: opticalMeta = {},
}) {
    const [q, setQ] = useState(filters.q || '');
    const [status, setStatus] = useState(filters.status || 'all');
    const [selectedId, setSelectedId] = useState(null);
    const [mobileTab, setMobileTab] = useState('map'); // map | list

    const selected = useMemo(
        () => customers.find((item) => item.id === selectedId) || null,
        [customers, selectedId],
    );

    const mapCustomers = useMemo(
        () => customers.filter((item) => item.on_map),
        [customers],
    );

    const applyFilters = useDebouncedCallback((nextQ, nextStatus) => {
        router.get(
            '/admin/network/map',
            {
                q: nextQ || undefined,
                status: nextStatus && nextStatus !== 'all' ? nextStatus : undefined,
            },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    }, 350);

    useEffect(() => {
        setQ(filters.q || '');
        setStatus(filters.status || 'all');
    }, [filters.q, filters.status]);

    const selectCustomer = (id) => {
        setSelectedId(id);
        const customer = customers.find((item) => item.id === id);
        if (customer?.on_map) {
            setMobileTab('map');
        }
    };

    const listPanel = (
        <aside className="flex h-full min-h-0 w-full flex-col lg:w-[340px] lg:shrink-0 lg:border-r lg:border-ink/10">
            <div className="space-y-2 border-b border-ink/10 p-3">
                <label className="relative block">
                    <Search className="pointer-events-none absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-ink-soft" />
                    <input
                        type="search"
                        value={q}
                        onChange={(e) => {
                            const value = e.target.value;
                            setQ(value);
                            applyFilters(value, status);
                        }}
                        placeholder="Cari nama / username / telepon"
                        className="w-full border border-ink/15 bg-white py-2 pr-3 pl-8 text-sm outline-none focus:border-signal"
                    />
                </label>
                <select
                    value={status}
                    onChange={(e) => {
                        const value = e.target.value;
                        setStatus(value);
                        applyFilters(q, value);
                    }}
                    className="w-full border border-ink/15 bg-white px-3 py-2 text-sm outline-none focus:border-signal"
                >
                    {STATUS_OPTIONS.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            </div>

            <ul className="min-h-0 flex-1 overflow-y-auto">
                {customers.length === 0 && (
                    <li className="px-4 py-8 text-center text-sm text-ink-soft">
                        Tidak ada pelanggan.
                    </li>
                )}
                {customers.map((customer) => {
                    const active = customer.id === selectedId;
                    return (
                        <li key={customer.id}>
                            <button
                                type="button"
                                onClick={() => selectCustomer(customer.id)}
                                className={`flex w-full items-start gap-3 border-b border-ink/5 px-3 py-3 text-left transition ${
                                    active ? 'bg-signal/10' : 'hover:bg-mist/70'
                                }`}
                            >
                                <span
                                    className="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full"
                                    style={{ background: markerColor(customer) }}
                                />
                                <span className="min-w-0 flex-1">
                                    <span className="flex items-center gap-2">
                                        <span className="truncate text-sm font-semibold text-ink">
                                            {customer.name}
                                        </span>
                                        {!customer.on_map && (
                                            <MapPin className="h-3 w-3 shrink-0 text-amber-600" />
                                        )}
                                    </span>
                                    <span className="mt-0.5 block truncate font-mono text-[11px] text-ink-soft">
                                        {customer.username}
                                    </span>
                                    <span className="mt-1 flex flex-wrap gap-1.5">
                                        <span
                                            className={`px-1.5 py-0.5 text-[10px] font-semibold ${statusBadgeClass(customer.status)}`}
                                        >
                                            {STATUS_LABEL[customer.status] || customer.status}
                                        </span>
                                        {customer.optical?.rx_power_label && (
                                            <span className="bg-ink/5 px-1.5 py-0.5 text-[10px] font-semibold text-ink-soft">
                                                RX {customer.optical.rx_power_label}
                                            </span>
                                        )}
                                        {customer.session_online && (
                                            <span className="inline-flex items-center gap-0.5 bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700">
                                                <Activity className="h-2.5 w-2.5" />
                                                online
                                            </span>
                                        )}
                                    </span>
                                </span>
                            </button>
                        </li>
                    );
                })}
            </ul>
        </aside>
    );

    const mapPanel = (
        <div className="relative min-h-0 min-w-0 flex-1">
            <NetworkMapView
                customers={mapCustomers}
                selectedId={selectedId}
                onSelect={selectCustomer}
            />
            {mapCustomers.length === 0 && (
                <div className="pointer-events-none absolute inset-0 flex items-center justify-center bg-white/50 p-6">
                    <p className="max-w-sm border border-ink/10 bg-white px-4 py-3 text-center text-sm text-ink-soft shadow-sm">
                        Belum ada pelanggan dengan koordinat GPS pada filter ini.
                    </p>
                </div>
            )}
        </div>
    );

    return (
        <AdminLayout title="Peta Jaringan" subtitle="Sebaran pelanggan, optik ONT, dan live trafik">
            <Head title="Peta Jaringan" />

            <div className="-mx-4 -my-6 flex h-[calc(100dvh-7.5rem)] min-h-[480px] flex-col overflow-hidden border-y border-ink/10 bg-white sm:-mx-6 lg:-mx-8">
                <div className="flex flex-wrap items-center gap-3 border-b border-ink/10 bg-mist/40 px-4 py-2.5 text-xs text-ink-soft sm:px-5">
                    <span>
                        <strong className="text-ink">{stats.total ?? 0}</strong> pelanggan
                    </span>
                    <span className="text-ink/20">·</span>
                    <span>
                        <strong className="text-ink">{stats.on_map ?? 0}</strong> di peta
                    </span>
                    <span className="text-ink/20">·</span>
                    <span>
                        <strong className="text-ink">{stats.optical_matched ?? 0}</strong> optik cocok
                    </span>
                    <span className="text-ink/20">·</span>
                    <span>
                        <strong className="text-ink">{stats.session_online ?? 0}</strong> sesi online
                    </span>
                    {!opticalMeta.enabled && (
                        <>
                            <span className="text-ink/20">·</span>
                            <span className="text-amber-700">
                                {opticalMeta.message || 'GenieACS belum dikonfigurasi'}
                            </span>
                        </>
                    )}
                    {opticalMeta.enabled && opticalMeta.ok === false && opticalMeta.message && (
                        <>
                            <span className="text-ink/20">·</span>
                            <span className="text-rose-700">{opticalMeta.message}</span>
                        </>
                    )}
                </div>

                {/* Mobile tabs: Peta | Daftar */}
                <div className="grid grid-cols-2 border-b border-ink/10 lg:hidden">
                    <button
                        type="button"
                        onClick={() => setMobileTab('map')}
                        className={`inline-flex items-center justify-center gap-2 px-3 py-2.5 text-sm font-semibold transition ${
                            mobileTab === 'map'
                                ? 'border-b-2 border-signal text-signal-deep'
                                : 'text-ink-soft'
                        }`}
                    >
                        <MapIcon className="h-4 w-4" />
                        Peta
                    </button>
                    <button
                        type="button"
                        onClick={() => setMobileTab('list')}
                        className={`inline-flex items-center justify-center gap-2 px-3 py-2.5 text-sm font-semibold transition ${
                            mobileTab === 'list'
                                ? 'border-b-2 border-signal text-signal-deep'
                                : 'text-ink-soft'
                        }`}
                    >
                        <List className="h-4 w-4" />
                        Daftar
                    </button>
                </div>

                {/* Mobile body */}
                <div className="relative flex min-h-0 flex-1 flex-col lg:hidden">
                    {mobileTab === 'map' ? mapPanel : listPanel}
                    {selected && (
                        <DetailPanel
                            customer={selected}
                            onClose={() => setSelectedId(null)}
                            mobileSheet
                        />
                    )}
                </div>

                {/* Desktop body */}
                <div className="hidden min-h-0 flex-1 lg:flex">
                    {listPanel}
                    <div className="relative min-h-0 min-w-0 flex-1">
                        <NetworkMapView
                            customers={mapCustomers}
                            selectedId={selectedId}
                            onSelect={selectCustomer}
                        />
                        {mapCustomers.length === 0 && (
                            <div className="pointer-events-none absolute inset-0 flex items-center justify-center bg-white/50 p-6">
                                <p className="max-w-sm border border-ink/10 bg-white px-4 py-3 text-center text-sm text-ink-soft shadow-sm">
                                    Belum ada pelanggan dengan koordinat GPS pada filter ini.
                                </p>
                            </div>
                        )}
                    </div>
                    {selected && (
                        <DetailPanel customer={selected} onClose={() => setSelectedId(null)} />
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
