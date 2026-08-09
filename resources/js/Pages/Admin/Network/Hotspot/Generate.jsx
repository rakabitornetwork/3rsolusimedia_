import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import AdminLayout from '../../../../Layouts/AdminLayout';

const fieldClass =
    'mt-1.5 w-full border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal';

function parseDigits(raw) {
    if (raw === '') return '';
    if (!/^\d+$/.test(raw)) return null;
    return raw.replace(/^0+(?=\d)/, '');
}

function formatRp(value) {
    const n = Number(value || 0);
    return `Rp ${n.toLocaleString('id-ID')}`;
}

export default function Generate({
    routers,
    selected_router_id,
    profiles,
    servers,
    agents = [],
    code_formats = [],
    scenarios = [],
}) {
    const { data, setData, post, processing, errors, transform } = useForm({
        router_id: selected_router_id || routers[0]?.id || '',
        profile: profiles[0]?.name || '',
        server: 'all',
        scenario: 'custom',
        quantity: '10',
        prefix: 'VC',
        code_length: '6',
        code_format: 'alphanumeric',
        password_mode: 'same',
        limit_uptime: '1d',
        limit_bytes_mb: '',
        comment: 'voucher-app',
        agent_id: '',
        base_price: '',
        commission: '',
    });

    const selectedAgent = useMemo(
        () => agents.find((item) => String(item.id) === String(data.agent_id)),
        [agents, data.agent_id],
    );

    const sellPrice = useMemo(() => {
        const base = Number(data.base_price || 0);
        const commission = Number(
            data.commission === '' || data.commission == null
                ? selectedAgent?.voucher_commission || 0
                : data.commission,
        );
        return base + commission;
    }, [data.base_price, data.commission, selectedAgent]);

    const formatExample = useMemo(() => {
        const option = code_formats.find((item) => item.value === data.code_format);
        const sample = option?.example || 'XXXXXX';
        const length = Math.max(4, Math.min(12, Number(data.code_length) || 6));
        const code = sample.slice(0, length).padEnd(length, sample[0] || 'X');
        return `${data.prefix || ''}${code}`;
    }, [code_formats, data.code_format, data.code_length, data.prefix]);

    const applyScenario = (value) => {
        setData((current) => {
            const scenario = scenarios.find((item) => item.value === value);
            const next = { ...current, scenario: value };
            if (!scenario?.defaults) return next;
            return { ...next, ...scenario.defaults };
        });
    };

    const setAgent = (agentId) => {
        const agent = agents.find((item) => String(item.id) === String(agentId));
        setData((current) => ({
            ...current,
            agent_id: agentId,
            commission: agent ? String(agent.voucher_commission ?? 0) : '',
        }));
    };

    const submit = (e) => {
        e.preventDefault();

        transform((form) => ({
            ...form,
            quantity: Number(form.quantity || 0),
            code_length: Number(form.code_length || 0),
            base_price: form.base_price === '' ? 0 : Number(form.base_price),
            commission:
                form.commission === '' || form.commission == null
                    ? selectedAgent?.voucher_commission || 0
                    : Number(form.commission),
            agent_id: form.agent_id || null,
            limit_bytes_mb: form.limit_bytes_mb === '' ? null : Number(form.limit_bytes_mb),
        }));

        post('/admin/network/hotspot');
    };

    return (
        <AdminLayout
            title="Generate Voucher Hotspot"
            subtitle="Buat user hotspot di RouterOS untuk dibagikan sebagai voucher"
        >
            <Head title="Generate Voucher Hotspot" />

            <form
                onSubmit={submit}
                className="max-w-3xl space-y-4 border border-ink/10 bg-white p-6 sm:p-8"
            >
                <label className="block text-sm font-medium text-ink">
                    Router
                    <select
                        value={data.router_id || ''}
                        onChange={(e) =>
                            router.get('/admin/network/hotspot/generate', {
                                router_id: e.target.value,
                            })
                        }
                        className={fieldClass}
                        required
                    >
                        <option value="">Pilih router</option>
                        {routers.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name} ({item.host})
                            </option>
                        ))}
                    </select>
                    {errors.router_id && (
                        <span className="mt-1 block text-xs text-red-600">{errors.router_id}</span>
                    )}
                </label>

                <label className="block text-sm font-medium text-ink">
                    Skenario format voucher
                    <select
                        value={data.scenario}
                        onChange={(e) => applyScenario(e.target.value)}
                        className={fieldClass}
                    >
                        {scenarios.map((item) => (
                            <option key={item.value} value={item.value}>
                                {item.label}
                            </option>
                        ))}
                    </select>
                    <span className="mt-1 block text-xs text-ink-soft">
                        {scenarios.find((item) => item.value === data.scenario)?.description ||
                            'Pilih skenario siap pakai atau kustom.'}
                    </span>
                </label>

                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm font-medium text-ink">
                        Profile hotspot
                        <select
                            value={data.profile || ''}
                            onChange={(e) => setData('profile', e.target.value)}
                            className={fieldClass}
                            required
                        >
                            <option value="">Pilih profile</option>
                            {profiles.map((profile) => (
                                <option key={profile.name} value={profile.name}>
                                    {profile.name}
                                    {profile.rate_limit ? ` (${profile.rate_limit})` : ''}
                                </option>
                            ))}
                        </select>
                        {errors.profile && (
                            <span className="mt-1 block text-xs text-red-600">{errors.profile}</span>
                        )}
                        {profiles.length === 0 && (
                            <span className="mt-1 block text-xs text-amber-700">
                                Belum ada hotspot user profile di router. Buat dulu di MikroTik.
                            </span>
                        )}
                    </label>

                    <label className="block text-sm font-medium text-ink">
                        Hotspot server
                        <select
                            value={data.server || 'all'}
                            onChange={(e) => setData('server', e.target.value)}
                            className={fieldClass}
                        >
                            <option value="all">Semua server</option>
                            {servers.map((server) => (
                                <option key={server.name} value={server.name}>
                                    {server.name}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>

                <div className="grid gap-4 sm:grid-cols-4">
                    <label className="block text-sm font-medium text-ink">
                        Jumlah
                        <input
                            type="text"
                            inputMode="numeric"
                            value={data.quantity}
                            onChange={(e) => {
                                const next = parseDigits(e.target.value);
                                if (next === null) return;
                                setData('quantity', next);
                            }}
                            className={fieldClass}
                            required
                        />
                        {errors.quantity && (
                            <span className="mt-1 block text-xs text-red-600">{errors.quantity}</span>
                        )}
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        Prefix
                        <input
                            type="text"
                            value={data.prefix}
                            onChange={(e) => setData('prefix', e.target.value)}
                            className={fieldClass}
                            placeholder="VC / kosong"
                        />
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        Panjang kode
                        <input
                            type="text"
                            inputMode="numeric"
                            value={data.code_length}
                            onChange={(e) => {
                                const next = parseDigits(e.target.value);
                                if (next === null) return;
                                setData('code_length', next);
                            }}
                            className={fieldClass}
                            required
                        />
                        {errors.code_length && (
                            <span className="mt-1 block text-xs text-red-600">
                                {errors.code_length}
                            </span>
                        )}
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        Format kode
                        <select
                            value={data.code_format}
                            onChange={(e) => {
                                setData((current) => ({
                                    ...current,
                                    code_format: e.target.value,
                                    scenario: 'custom',
                                }));
                            }}
                            className={fieldClass}
                        >
                            {code_formats.map((item) => (
                                <option key={item.value} value={item.value}>
                                    {item.label}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>

                <label className="block text-sm font-medium text-ink">
                    Mode password
                    <select
                        value={data.password_mode}
                        onChange={(e) => setData('password_mode', e.target.value)}
                        className={fieldClass}
                    >
                        <option value="same">Sama dengan username</option>
                        <option value="random">Password acak terpisah</option>
                    </select>
                </label>

                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm font-medium text-ink">
                        Limit uptime
                        <input
                            type="text"
                            value={data.limit_uptime}
                            onChange={(e) => setData('limit_uptime', e.target.value)}
                            className={fieldClass}
                            placeholder="1h / 1d / 2d"
                        />
                        <span className="mt-1 block text-xs text-ink-soft">
                            Format MikroTik, contoh: 1h, 12h, 1d
                        </span>
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        Limit kuota (MB)
                        <input
                            type="text"
                            inputMode="numeric"
                            value={data.limit_bytes_mb}
                            onChange={(e) => {
                                const next = parseDigits(e.target.value);
                                if (next === null) return;
                                setData('limit_bytes_mb', next);
                            }}
                            className={fieldClass}
                            placeholder="Kosongkan jika tanpa kuota"
                        />
                    </label>
                </div>

                <div className="border border-ink/10 bg-mist/30 p-4">
                    <p className="text-sm font-semibold text-ink">Agen & harga kartu</p>
                    <p className="mt-1 text-xs text-ink-soft">
                        Harga di kartu = harga dasar + komisi agen. Contoh: dasar 1500 + komisi
                        Andi 500 = 2000.
                    </p>

                    <div className="mt-3 grid gap-4 sm:grid-cols-3">
                        <label className="block text-sm font-medium text-ink">
                            Agen penjual
                            <select
                                value={data.agent_id || ''}
                                onChange={(e) => setAgent(e.target.value)}
                                className={fieldClass}
                            >
                                <option value="">Tanpa agen</option>
                                {agents.map((agent) => (
                                    <option key={agent.id} value={agent.id}>
                                        {agent.name} (komisi {formatRp(agent.voucher_commission)})
                                    </option>
                                ))}
                            </select>
                            {errors.agent_id && (
                                <span className="mt-1 block text-xs text-red-600">
                                    {errors.agent_id}
                                </span>
                            )}
                        </label>
                        <label className="block text-sm font-medium text-ink">
                            Harga dasar (Rp)
                            <input
                                type="text"
                                inputMode="numeric"
                                value={data.base_price}
                                onChange={(e) => {
                                    const next = parseDigits(e.target.value);
                                    if (next === null) return;
                                    setData('base_price', next);
                                }}
                                className={fieldClass}
                                placeholder="1500"
                            />
                            {errors.base_price && (
                                <span className="mt-1 block text-xs text-red-600">
                                    {errors.base_price}
                                </span>
                            )}
                        </label>
                        <label className="block text-sm font-medium text-ink">
                            Komisi agen (Rp)
                            <input
                                type="text"
                                inputMode="numeric"
                                value={data.commission}
                                onChange={(e) => {
                                    const next = parseDigits(e.target.value);
                                    if (next === null) return;
                                    setData('commission', next);
                                }}
                                className={fieldClass}
                                placeholder="500"
                                disabled={!data.agent_id}
                            />
                            {errors.commission && (
                                <span className="mt-1 block text-xs text-red-600">
                                    {errors.commission}
                                </span>
                            )}
                        </label>
                    </div>

                    <div className="mt-3 flex flex-wrap items-center justify-between gap-2 border border-ink/10 bg-white px-3 py-2 text-sm">
                        <span className="text-ink-soft">
                            {selectedAgent
                                ? `Agen: ${selectedAgent.name}`
                                : 'Penjualan langsung (tanpa agen)'}
                        </span>
                        <span className="font-semibold text-ink">
                            Harga kartu: {formatRp(sellPrice)}
                        </span>
                    </div>
                </div>

                <label className="block text-sm font-medium text-ink">
                    Comment
                    <input
                        type="text"
                        value={data.comment}
                        onChange={(e) => setData('comment', e.target.value)}
                        className={fieldClass}
                        placeholder="voucher-app"
                    />
                </label>

                <div className="border border-ink/10 bg-mist/40 px-4 py-3 text-xs text-ink-soft">
                    Contoh username: <strong className="font-mono text-ink">{formatExample}</strong>.
                    Voucher dibuat di `/ip/hotspot/user` dan disimpan di aplikasi untuk laporan &
                    kartu.
                </div>

                <div className="flex flex-wrap gap-3 pt-2">
                    <button
                        type="submit"
                        disabled={processing || profiles.length === 0}
                        className="btn-action btn-action-sm btn-primary"
                    >
                        {processing ? 'Membuat...' : 'Generate Voucher'}
                    </button>
                    <Link
                        href={`/admin/network/hotspot${
                            selected_router_id ? `?router_id=${selected_router_id}` : ''
                        }`}
                        className="btn-action btn-action-sm btn-secondary"
                    >
                        Batal
                    </Link>
                </div>
            </form>
        </AdminLayout>
    );
}
