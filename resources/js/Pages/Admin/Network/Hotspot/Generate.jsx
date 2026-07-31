import { Head, Link, router, useForm } from '@inertiajs/react';
import AdminLayout from '../../../../Layouts/AdminLayout';

const fieldClass =
    'mt-1.5 w-full border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal';

export default function Generate({ routers, selected_router_id, profiles, servers }) {

    const { data, setData, post, processing, errors } = useForm({
        router_id: selected_router_id || routers[0]?.id || '',
        profile: profiles[0]?.name || '',
        server: 'all',
        quantity: 10,
        prefix: 'VC',
        code_length: 6,
        password_mode: 'same',
        limit_uptime: '1d',
        limit_bytes_mb: '',
        comment: 'voucher-app',
    });

    const submit = (e) => {
        e.preventDefault();
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
                className="max-w-2xl space-y-4 border border-ink/10 bg-white p-6 sm:p-8"
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

                <div className="grid gap-4 sm:grid-cols-3">
                    <label className="block text-sm font-medium text-ink">
                        Jumlah
                        <input
                            type="number"
                            min={1}
                            max={100}
                            value={data.quantity}
                            onChange={(e) => setData('quantity', Number(e.target.value))}
                            className={fieldClass}
                            required
                        />
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        Prefix
                        <input
                            type="text"
                            value={data.prefix}
                            onChange={(e) => setData('prefix', e.target.value)}
                            className={fieldClass}
                            placeholder="VC"
                        />
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        Panjang kode
                        <input
                            type="number"
                            min={4}
                            max={12}
                            value={data.code_length}
                            onChange={(e) => setData('code_length', Number(e.target.value))}
                            className={fieldClass}
                            required
                        />
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
                            type="number"
                            min={1}
                            value={data.limit_bytes_mb}
                            onChange={(e) => setData('limit_bytes_mb', e.target.value)}
                            className={fieldClass}
                            placeholder="Kosongkan jika tanpa kuota"
                        />
                    </label>
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
                    Contoh username: <strong>{data.prefix || ''}XXXXXX</strong>. Voucher dibuat
                    langsung di `/ip/hotspot/user` pada router terpilih.
                </div>

                <div className="flex flex-wrap gap-3 pt-2">
                    <button
                        type="submit"
                        disabled={processing || profiles.length === 0}
                        className="bg-signal-deep px-5 py-3 text-sm font-bold text-white hover:bg-ink disabled:opacity-60"
                    >
                        {processing ? 'Membuat...' : 'Generate Voucher'}
                    </button>
                    <Link
                        href={`/admin/network/hotspot${
                            selected_router_id ? `?router_id=${selected_router_id}` : ''
                        }`}
                        className="border border-ink/15 px-5 py-3 text-sm font-semibold text-ink-soft hover:bg-mist"
                    >
                        Batal
                    </Link>
                </div>
            </form>
        </AdminLayout>
    );
}
