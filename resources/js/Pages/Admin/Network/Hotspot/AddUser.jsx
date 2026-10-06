import { Head, Link, router, useForm } from '@inertiajs/react';
import AdminLayout from '../../../../Layouts/AdminLayout';

const fieldClass =
    'mt-1.5 w-full border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal';

export default function AddUser({
    routers,
    selected_router_id,
    profiles = [],
    servers = [],
}) {
    const { data, setData, post, processing, errors, transform } = useForm({
        router_id: selected_router_id || routers[0]?.id || '',
        name: '',
        password: '',
        profile: profiles[0]?.name || '',
        server: 'all',
        limit_uptime: '',
        limit_bytes_mb: '',
        comment: '',
    });

    transform((payload) => ({
        ...payload,
        password: payload.password || payload.name,
        limit_uptime: payload.limit_uptime || null,
        limit_bytes_mb: payload.limit_bytes_mb === '' ? null : payload.limit_bytes_mb,
        server: payload.server || 'all',
    }));

    const submit = (e) => {
        e.preventDefault();
        post('/admin/network/hotspot/users');
    };

    const passwordHint =
        !data.password || data.password === data.name
            ? 'Comment akan diawali vc- (username = password).'
            : 'Comment akan diawali up- (username & password berbeda).';

    return (
        <AdminLayout
            title="Tambah User Hotspot"
            subtitle="Buat satu user di /ip/hotspot/user, seperti Add User di Mikhmon"
        >
            <Head title="Tambah User Hotspot" />

            <form onSubmit={submit} className="max-w-2xl space-y-4 border border-ink/10 bg-white p-4 sm:p-5">
                <label className="block text-sm font-medium text-ink">
                    Router
                    <select
                        value={data.router_id}
                        onChange={(e) => {
                            const routerId = e.target.value;
                            setData('router_id', routerId);
                            router.get('/admin/network/hotspot/users/create', {
                                router_id: routerId,
                            });
                        }}
                        className={fieldClass}
                        required
                    >
                        {routers.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name} ({item.host})
                            </option>
                        ))}
                    </select>
                </label>

                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm font-medium text-ink">
                        Username
                        <input
                            type="text"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className={fieldClass}
                            required
                            autoComplete="off"
                        />
                        {errors.name && (
                            <span className="mt-1 block text-xs text-red-600">{errors.name}</span>
                        )}
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        Password
                        <input
                            type="text"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            className={fieldClass}
                            placeholder="Kosongkan = sama dengan username"
                            autoComplete="off"
                        />
                    </label>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm font-medium text-ink">
                        Profile
                        <select
                            value={data.profile}
                            onChange={(e) => setData('profile', e.target.value)}
                            className={fieldClass}
                            required
                        >
                            {profiles.length === 0 && (
                                <option value="">Tidak ada profile</option>
                            )}
                            {profiles.map((profile) => (
                                <option key={profile.id || profile.name} value={profile.name}>
                                    {profile.name}
                                </option>
                            ))}
                        </select>
                        {errors.profile && (
                            <span className="mt-1 block text-xs text-red-600">
                                {errors.profile}
                            </span>
                        )}
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        Server
                        <select
                            value={data.server}
                            onChange={(e) => setData('server', e.target.value)}
                            className={fieldClass}
                        >
                            <option value="all">all</option>
                            {servers.map((server) => (
                                <option key={server.name} value={server.name}>
                                    {server.name}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm font-medium text-ink">
                        Limit uptime
                        <input
                            type="text"
                            value={data.limit_uptime}
                            onChange={(e) => setData('limit_uptime', e.target.value)}
                            className={fieldClass}
                            placeholder="1d / 2h / kosong"
                        />
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        Limit data (MB)
                        <input
                            type="text"
                            inputMode="numeric"
                            value={data.limit_bytes_mb}
                            onChange={(e) => {
                                const value = e.target.value;
                                if (value === '' || /^\d+$/.test(value)) {
                                    setData('limit_bytes_mb', value);
                                }
                            }}
                            className={fieldClass}
                            placeholder="Kosong = unlimited"
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
                        placeholder="catatan / tanggal"
                    />
                    <span className="mt-1 block text-xs text-ink-soft">{passwordHint}</span>
                </label>

                <div className="flex flex-wrap gap-3 pt-2">
                    <button
                        type="submit"
                        disabled={processing || profiles.length === 0}
                        className="btn-action btn-action-sm btn-primary"
                    >
                        {processing ? 'Menyimpan...' : 'Simpan user'}
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
