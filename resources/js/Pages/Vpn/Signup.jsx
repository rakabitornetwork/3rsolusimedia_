import { Link, useForm, usePage } from '@inertiajs/react';
import Logo from '../../Icons/Logo';

const fieldClass =
    'mt-1.5 w-full rounded-xl border border-ink/15 bg-white px-3 py-2.5 text-sm outline-none focus:border-signal';

export default function Signup({ settings, open }) {
    const { flash } = usePage().props;
    const form = useForm({
        name: '',
        email: '',
        phone: '',
    });
    const company = settings?.company_name || 'Tesla Tech';

    return (
        <main className="min-h-screen bg-paper">
            <div className="mx-auto grid min-h-screen max-w-6xl lg:grid-cols-2">
                <section className="relative hidden overflow-hidden bg-ink lg:block">
                    <img
                        src="/images/vpn/office.jpg"
                        alt="Meja kerja dengan router untuk layanan VPN Tunnel"
                        className="h-full w-full object-cover opacity-80"
                    />
                    <div className="absolute inset-0 bg-gradient-to-t from-ink via-ink/55 to-ink/20" />
                    <div className="absolute bottom-0 left-0 right-0 p-10 text-white">
                        <p className="font-display text-sm font-semibold tracking-[0.2em] text-signal-bright uppercase">
                            Layanan VPN Tunnel
                        </p>
                        <h1 className="font-display mt-3 text-4xl font-bold">Daftar, lalu buat router pertama.</h1>
                        <p className="mt-3 max-w-md text-sm leading-relaxed text-white/75">
                            Masuk portal hanya dengan kode OTP WhatsApp. Router pertama gratis 3 hari dan bisa dipakai untuk mengalihkan trafik Speedtest dari ISP utama ke VPN Tunnel.
                        </p>
                    </div>
                </section>

                <section className="px-5 py-10 lg:px-12">
                    <Link href="/" className="inline-flex items-center gap-3 text-ink">
                        <Logo className="h-9 w-9" markOnly />
                        <span className="font-display text-sm font-bold">{company}</span>
                    </Link>
                    <h2 className="font-display mt-8 text-3xl font-bold text-ink">Pendaftaran VPN Tunnel</h2>
                    <p className="mt-2 text-sm leading-relaxed text-ink-soft">
                        Setelah daftar, masuk portal dengan nomor WhatsApp ini. Kode OTP dikirim ke WhatsApp yang terdaftar. Router pertama bisa dibuat gratis selama 3 hari.
                    </p>

                    {flash?.error && (
                        <p className="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {flash.error}
                        </p>
                    )}

                    {!open ? (
                        <p className="mt-8 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-950">
                            Pendaftaran VPN Tunnel belum dibuka. Hubungi admin.
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
                                {form.errors.name && (
                                    <span className="mt-1 block text-xs text-red-600">{form.errors.name}</span>
                                )}
                            </label>
                            <label className="block text-sm font-medium text-ink">
                                E-mail
                                <input
                                    type="email"
                                    className={fieldClass}
                                    value={form.data.email}
                                    onChange={(event) => form.setData('email', event.target.value)}
                                    required
                                />
                                {form.errors.email && (
                                    <span className="mt-1 block text-xs text-red-600">{form.errors.email}</span>
                                )}
                            </label>
                            <label className="block text-sm font-medium text-ink">
                                WhatsApp
                                <input
                                    type="tel"
                                    className={fieldClass}
                                    value={form.data.phone}
                                    onChange={(event) => form.setData('phone', event.target.value)}
                                    placeholder="08xxxxxxxxxx"
                                    required
                                />
                                {form.errors.phone && (
                                    <span className="mt-1 block text-xs text-red-600">{form.errors.phone}</span>
                                )}
                            </label>
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="w-full cursor-pointer rounded-xl bg-ink px-4 py-3 text-sm font-semibold text-white disabled:opacity-60"
                            >
                                {form.processing ? 'Mendaftarkan...' : 'Daftar'}
                            </button>
                        </form>
                    )}
                </section>
            </div>
        </main>
    );
}
