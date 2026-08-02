import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '../../../../Layouts/AdminLayout';

const fieldClass =
    'mt-1.5 w-full border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal';

export default function Form({ router }) {
    const editing = Boolean(router);

    const { data, setData, post, put, processing, errors } = useForm({
        name: router?.name || '',
        host: router?.host || '',
        port: router?.port || 8728,
        username: router?.username || 'admin',
        password: '',
        use_ssl: router?.use_ssl || false,
        is_active: router?.is_active ?? true,
        notes: router?.notes || '',
    });

    const submit = (e) => {
        e.preventDefault();
        if (editing) {
            put(`/admin/network/routeros/${router.id}`);
        } else {
            post('/admin/network/routeros');
        }
    };

    return (
        <AdminLayout
            title={editing ? 'Edit Router' : 'Tambah Router'}
            subtitle="Koneksi API RouterOS (default port 8728)"
        >
            <Head title={editing ? 'Edit Router' : 'Tambah Router'} />

            <form
                onSubmit={submit}
                className="max-w-2xl space-y-4 border border-ink/10 bg-white p-6 sm:p-8"
            >
                <label className="block text-sm font-medium text-ink">
                    Nama router
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        className={fieldClass}
                        placeholder="Router Utama RT"
                        required
                    />
                    {errors.name && <span className="mt-1 block text-xs text-red-600">{errors.name}</span>}
                </label>

                <div className="grid gap-4 sm:grid-cols-3">
                    <label className="block text-sm font-medium text-ink sm:col-span-2">
                        Host / IP
                        <input
                            type="text"
                            value={data.host}
                            onChange={(e) => setData('host', e.target.value)}
                            className={fieldClass}
                            placeholder="192.168.88.1"
                            required
                        />
                        {errors.host && (
                            <span className="mt-1 block text-xs text-red-600">{errors.host}</span>
                        )}
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        Port API
                        <input
                            type="number"
                            value={data.port}
                            onChange={(e) => setData('port', Number(e.target.value))}
                            className={fieldClass}
                            min={1}
                            max={65535}
                            required
                        />
                        {errors.port && (
                            <span className="mt-1 block text-xs text-red-600">{errors.port}</span>
                        )}
                    </label>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm font-medium text-ink">
                        Username
                        <input
                            type="text"
                            value={data.username}
                            onChange={(e) => setData('username', e.target.value)}
                            className={fieldClass}
                            required
                        />
                        {errors.username && (
                            <span className="mt-1 block text-xs text-red-600">{errors.username}</span>
                        )}
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        Password
                        <input
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            className={fieldClass}
                            placeholder={editing ? 'Kosongkan jika tidak diganti' : ''}
                            required={!editing}
                        />
                        {errors.password && (
                            <span className="mt-1 block text-xs text-red-600">{errors.password}</span>
                        )}
                    </label>
                </div>

                <label className="block text-sm font-medium text-ink">
                    Catatan
                    <textarea
                        rows={3}
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        className={fieldClass}
                        placeholder="Lokasi OLT / keterangan singkat"
                    />
                </label>

                <div className="flex flex-wrap gap-5 text-sm text-ink">
                    <label className="inline-flex items-center gap-2">
                        <input
                            type="checkbox"
                            checked={data.use_ssl}
                            onChange={(e) => setData('use_ssl', e.target.checked)}
                        />
                        Gunakan API-SSL
                    </label>
                    <label className="inline-flex items-center gap-2">
                        <input
                            type="checkbox"
                            checked={data.is_active}
                            onChange={(e) => setData('is_active', e.target.checked)}
                        />
                        Aktif
                    </label>
                </div>

                <div className="rounded-sm border border-ink/10 bg-mist/40 px-4 py-3 text-xs leading-relaxed text-ink-soft">
                    Tip: di Winbox buka <strong>IP → Services</strong>, aktifkan <strong>api</strong>{' '}
                    (port 8728). Kalau dari luar jaringan, pastikan firewall mengizinkan port tersebut.
                </div>

                <div className="flex flex-wrap gap-3 pt-2">
                    <button
                        type="submit"
                        disabled={processing}
                        className="btn-action btn-action-sm btn-primary"
                    >
                        {processing ? 'Menyimpan...' : editing ? 'Simpan Perubahan' : 'Simpan Router'}
                    </button>
                    <Link
                        href="/admin/network/routeros"
                        className="btn-action btn-action-sm btn-secondary"
                    >
                        Batal
                    </Link>
                </div>
            </form>
        </AdminLayout>
    );
}
