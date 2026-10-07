import { Link, useForm } from '@inertiajs/react';
import Logo from '../../Icons/Logo';

const fieldClass =
    'mt-1.5 w-full rounded-xl border border-ink/15 bg-white px-3 py-2.5 text-sm outline-none focus:border-signal';

export default function Signup({ settings, packages, open }) {
    const form = useForm({
        name: '',
        phone: '',
        username: '',
        password: '',
        router_name: '',
        subscription_package_id: packages?.[0]?.id ? String(packages[0].id) : '',
    });
    const company = settings?.company_name || 'Tesla Tech';

    return (
        <main className="min-h-screen bg-paper">
            <div className="mx-auto grid min-h-screen max-w-6xl lg:grid-cols-2">
                <section className="relative hidden overflow-hidden bg-ink lg:block">
                    <img
                        src="/images/vpn/office.jpg"
                        alt="Meja kerja dengan router untuk layanan VPN"
                        className="h-full w-full object-cover opacity-80"
                    />
                    <div className="absolute inset-0 bg-gradient-to-t from-ink via-ink/55 to-ink/20" />
                    <div className="absolute bottom-0 left-0 right-0 p-10 text-white">
                        <p className="font-display text-sm font-semibold tracking-[0.2em] text-signal-bright uppercase">
                            Layanan VPN
                        </p>
                        <h1 className="font-display mt-3 text-4xl font-bold">Daftar, beri nama router, lalu bayar.</h1>
                        <p className="mt-3 max-w-md text-sm leading-relaxed text-white/75">
                            Router pertama mengikuti tagihan akun. Setelah pembayaran online lunas, secret VPN di server langsung diaktifkan.
                        </p>
                    </div>
                </section>

                <section className="px-5 py-10 lg:px-12">
                    <Link href="/" className="inline-flex items-center gap-3 text-ink">
                        <Logo className="h-9 w-9" markOnly />
                        <span className="font-display text-sm font-bold">{company}</span>
                    </Link>
                    <h2 className="font-display mt-8 text-3xl font-bold text-ink">Pendaftaran VPN</h2>
                    <p className="mt-2 text-sm leading-relaxed text-ink-soft">
                        Nama router menjadi username terowongan. Skrip baru muncul setelah tagihan pertama lunas.
                    </p>

                    {!open ? (
                        <p className="mt-8 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-950">
                            Pendaftaran VPN belum dibuka. Hubungi admin.
                        </p>
                    ) : (
                        <form
                            className="mt-8 space-y-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.post('/vpn/daftar');
                            }}
                        >
                            <label className="block text-sm font-medium text-ink">
                                Nama
                                <input
                                    className={fieldClass}
                                    value={form.data.name}
                                    onChange={(event) => form.setData('name', event.target.value)}
                                    required
                                />
                            </label>
                            <label className="block text-sm font-medium text-ink">
                                WhatsApp
                                <input
                                    className={fieldClass}
                                    value={form.data.phone}
                                    onChange={(event) => form.setData('phone', event.target.value)}
                                    required
                                />
                            </label>
                            <label className="block text-sm font-medium text-ink">
                                Username akun
                                <input
                                    className={fieldClass}
                                    value={form.data.username}
                                    onChange={(event) => form.setData('username', event.target.value)}
                                    placeholder="Untuk masuk portal"
                                    required
                                />
                                {form.errors.username && (
                                    <span className="mt-1 block text-xs text-red-600">{form.errors.username}</span>
                                )}
                            </label>
                            <label className="block text-sm font-medium text-ink">
                                Password VPN
                                <input
                                    type="password"
                                    className={fieldClass}
                                    value={form.data.password}
                                    onChange={(event) => form.setData('password', event.target.value)}
                                    required
                                />
                                {form.errors.password && (
                                    <span className="mt-1 block text-xs text-red-600">{form.errors.password}</span>
                                )}
                            </label>
                            <label className="block text-sm font-medium text-ink">
                                Nama router pertama
                                <input
                                    className={fieldClass}
                                    value={form.data.router_name}
                                    onChange={(event) => form.setData('router_name', event.target.value)}
                                    placeholder="Misalnya toko-pusat"
                                    required
                                />
                                {form.errors.router_name && (
                                    <span className="mt-1 block text-xs text-red-600">{form.errors.router_name}</span>
                                )}
                            </label>
                            <label className="block text-sm font-medium text-ink">
                                Paket
                                <select
                                    className={fieldClass}
                                    value={form.data.subscription_package_id}
                                    onChange={(event) => form.setData('subscription_package_id', event.target.value)}
                                    required
                                >
                                    {(packages || []).map((item) => (
                                        <option key={item.id} value={item.id}>
                                            {item.name} · {item.price_label}
                                        </option>
                                    ))}
                                </select>
                                {form.errors.subscription_package_id && (
                                    <span className="mt-1 block text-xs text-red-600">
                                        {form.errors.subscription_package_id}
                                    </span>
                                )}
                            </label>
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="w-full cursor-pointer rounded-xl bg-ink px-4 py-3 text-sm font-semibold text-white disabled:opacity-60"
                            >
                                {form.processing ? 'Mendaftarkan...' : 'Daftar dan bayar'}
                            </button>
                        </form>
                    )}
                </section>
            </div>
        </main>
    );
}
