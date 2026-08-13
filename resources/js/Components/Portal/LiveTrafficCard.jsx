import {
    ArrowDownToLine,
    ArrowUpFromLine,
    LoaderCircle,
    WifiOff,
} from 'lucide-react';
import { useEffect, useState } from 'react';

const POLL_SECONDS = 3;
const SPARK_POINTS = 24;

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

export default function PortalLiveTraffic({ token }) {
    const [traffic, setTraffic] = useState(null);
    const [history, setHistory] = useState({ rx: [], tx: [] });
    const [error, setError] = useState('');
    const [online, setOnline] = useState(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!token) return undefined;

        let cancelled = false;
        let busy = false;

        const fetchTraffic = async () => {
            if (busy || cancelled) return;
            busy = true;
            setLoading(true);

            try {
                const response = await fetch(`/bayar/${token}/trafik`, {
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
    }, [token]);

    return (
        <section className="border border-ink/10 bg-white p-5">
            <div className="flex items-center justify-between gap-2">
                <div>
                    <p className="text-xs tracking-wide text-ink-soft uppercase">Live trafik</p>
                    <h3 className="mt-1 text-sm font-semibold text-ink">
                        {online === null
                            ? 'Memeriksa sesi...'
                            : online
                              ? 'Sesi PPPoE online'
                              : 'Sesi PPPoE offline'}
                    </h3>
                </div>
                {loading && <LoaderCircle className="h-4 w-4 animate-spin text-ink-soft" />}
            </div>

            {error && !traffic ? (
                <p className="mt-3 flex items-start gap-2 border border-ink/10 bg-mist/40 px-3 py-3 text-xs text-ink-soft">
                    <WifiOff className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                    {error}
                </p>
            ) : (
                <div className="mt-3 space-y-3 border border-ink/10 p-3">
                    <div>
                        <div className="flex items-center justify-between text-xs">
                            <span className="inline-flex items-center gap-1 font-semibold text-teal-700">
                                <ArrowDownToLine className="h-3.5 w-3.5" />
                                Download (RX)
                            </span>
                            <span className="font-mono font-semibold text-ink">
                                {formatBitrate(traffic?.rx_bps)}
                            </span>
                        </div>
                        <Sparkline values={history.rx} stroke="#0d9488" />
                    </div>
                    <div>
                        <div className="flex items-center justify-between text-xs">
                            <span className="inline-flex items-center gap-1 font-semibold text-sky-700">
                                <ArrowUpFromLine className="h-3.5 w-3.5" />
                                Upload (TX)
                            </span>
                            <span className="font-mono font-semibold text-ink">
                                {formatBitrate(traffic?.tx_bps)}
                            </span>
                        </div>
                        <Sparkline values={history.tx} stroke="#0284c7" />
                    </div>
                </div>
            )}
        </section>
    );
}
