import { Head, Link, useForm } from '@inertiajs/react';
import { ImagePlus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import UserAvatar, { getInitials } from '../../../Components/UserAvatar';
import AdminLayout from '../../../Layouts/AdminLayout';

const fieldClass =
    'mt-1.5 w-full border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal';

export default function Form({ user, role_options }) {
    const editing = Boolean(user);
    const [preview, setPreview] = useState(user?.avatar_url || null);

    const { data, setData, post, processing, errors, transform } = useForm({
        name: user?.name || '',
        email: user?.email || '',
        role: user?.role || role_options[0]?.value || 'admin',
        password: '',
        password_confirmation: '',
        avatar: null,
        remove_avatar: false,
        ...(editing ? { _method: 'put' } : {}),
    });

    useEffect(() => {
        if (!data.avatar) return undefined;

        const url = URL.createObjectURL(data.avatar);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [data.avatar]);

    const initials = useMemo(() => getInitials(data.name), [data.name]);

    const submit = (e) => {
        e.preventDefault();

        transform((form) => {
            const payload = { ...form };

            if (!payload.password) {
                delete payload.password;
                delete payload.password_confirmation;
            }

            if (!(payload.avatar instanceof File)) {
                delete payload.avatar;
            }

            if (!payload.remove_avatar) {
                delete payload.remove_avatar;
            }

            return payload;
        });

        const url = editing ? `/admin/users/${user.id}` : '/admin/users';
        const hasFile = data.avatar instanceof File;

        post(url, {
            forceFormData: hasFile || Boolean(data.remove_avatar),
        });
    };

    const onAvatarChange = (e) => {
        const file = e.target.files?.[0] || null;
        setData('avatar', file);
        setData('remove_avatar', false);
        if (!file) {
            setPreview(user?.avatar_url || null);
        }
    };

    const clearAvatar = () => {
        setData('avatar', null);
        setData('remove_avatar', true);
        setPreview(null);
    };

    return (
        <AdminLayout
            title={editing ? 'Edit User' : 'Tambah User'}
            subtitle="Atur avatar, nama, email, password, dan role akses"
        >
            <Head title={editing ? 'Edit User' : 'Tambah User'} />

            <form
                onSubmit={submit}
                className="max-w-2xl space-y-4 border border-ink/10 bg-white p-6 sm:p-8"
            >
                <div className="flex flex-wrap items-center gap-4 border border-ink/10 bg-mist/40 p-4">
                    <UserAvatar
                        name={data.name}
                        role={data.role}
                        src={preview}
                        initials={initials}
                        size="xl"
                    />
                    <div className="min-w-0 flex-1">
                        <p className="text-sm font-semibold text-ink">Avatar pengguna</p>
                        <p className="mt-0.5 text-xs text-ink-soft">
                            Upload foto (JPG/PNG, maks. 2MB). Jika kosong, inisial nama dipakai otomatis.
                        </p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            <label className="btn-action btn-action-xs btn-secondary">
                                <ImagePlus className="h-3.5 w-3.5" />
                                Pilih foto
                                <input
                                    type="file"
                                    accept="image/*"
                                    className="hidden"
                                    onChange={onAvatarChange}
                                />
                            </label>
                            {(preview || user?.avatar_url) && !data.remove_avatar && (
                                <button
                                    type="button"
                                    onClick={clearAvatar}
                                    className="btn-action btn-action-xs btn-danger"
                                >
                                    <Trash2 className="h-3.5 w-3.5" />
                                    Hapus avatar
                                </button>
                            )}
                        </div>
                        {errors.avatar && (
                            <span className="mt-2 block text-xs text-red-600">{errors.avatar}</span>
                        )}
                    </div>
                </div>

                <label className="block text-sm font-medium text-ink">
                    Nama
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        className={fieldClass}
                        required
                    />
                    {errors.name && (
                        <span className="mt-1 block text-xs text-red-600">{errors.name}</span>
                    )}
                </label>

                <label className="block text-sm font-medium text-ink">
                    Email
                    <input
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        className={fieldClass}
                        required
                    />
                    {errors.email && (
                        <span className="mt-1 block text-xs text-red-600">{errors.email}</span>
                    )}
                </label>

                <fieldset>
                    <legend className="text-sm font-medium text-ink">Role</legend>
                    <div className="mt-2 space-y-2">
                        {role_options.map((role) => (
                            <label
                                key={role.value}
                                className={`flex cursor-pointer gap-3 border px-3 py-3 text-sm transition ${
                                    data.role === role.value
                                        ? 'border-signal bg-signal/5'
                                        : 'border-ink/10 hover:bg-mist/60'
                                }`}
                            >
                                <input
                                    type="radio"
                                    name="role"
                                    value={role.value}
                                    checked={data.role === role.value}
                                    onChange={() => setData('role', role.value)}
                                    className="mt-1"
                                />
                                <span>
                                    <span className="font-semibold text-ink">{role.label}</span>
                                    <span className="mt-0.5 block text-xs text-ink-soft">
                                        {role.description}
                                    </span>
                                </span>
                            </label>
                        ))}
                    </div>
                    {errors.role && (
                        <span className="mt-1 block text-xs text-red-600">{errors.role}</span>
                    )}
                </fieldset>

                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm font-medium text-ink">
                        Password {editing && <span className="font-normal text-ink-soft">(opsional)</span>}
                        <input
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            className={fieldClass}
                            autoComplete="new-password"
                            required={!editing}
                        />
                        {errors.password && (
                            <span className="mt-1 block text-xs text-red-600">{errors.password}</span>
                        )}
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        Konfirmasi password
                        <input
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            className={fieldClass}
                            autoComplete="new-password"
                            required={!editing || Boolean(data.password)}
                        />
                    </label>
                </div>

                <div className="flex flex-wrap gap-3 pt-2">
                    <button
                        type="submit"
                        disabled={processing}
                        className="btn-action btn-action-sm btn-primary"
                    >
                        {processing ? 'Menyimpan...' : editing ? 'Simpan Perubahan' : 'Tambah User'}
                    </button>
                    <Link
                        href="/admin/users"
                        className="btn-action btn-action-sm btn-secondary"
                    >
                        Batal
                    </Link>
                </div>
            </form>
        </AdminLayout>
    );
}
