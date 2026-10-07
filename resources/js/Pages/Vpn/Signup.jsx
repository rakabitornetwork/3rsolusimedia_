import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { CreditCard, Router, Shield } from 'lucide-react';
import Logo from '../../Icons/Logo';

const fieldClass =
    'mt-1.5 w-full rounded-lg border border-ink/10 bg-mist/40 px-3 py-2 text-sm outline-none transition focus:border-signal focus:bg-white focus:ring-4 focus:ring-signal/10 sm:rounded-xl sm:px-3.5 sm:py-3';

const primaryButton =
    'mt-1 w-full cursor-pointer rounded-lg bg-signal px-4 py-2.5 text-sm font-semibold text-white shadow-[0_12px_28px_-16px_rgba(26,110,255,0.95)] hover:bg-signal-deep disabled:cursor-not-allowed disabled:opacity-60 sm:mt-2 sm:rounded-xl sm:py-3';

export default function Signup({ settings, open }) {
    const { flash } = usePage().props;
    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        phone: '',
    });
    const company = settings?.company_name || 'Tesla Tech';

    return (
        <div className="relative min-h-screen text-ink">
            <div className="pointer-events-none fixed inset-0">
                <img
                    src="/images/portal/login-bg-huawei.png"
                    alt=""
                    className="h-full w-full object-cover"
                />
                <div className="absolute inset-0 bg-ink/40" />
            </div>
            <Head title={`Daftar VPN Tunnel · ${company}`} />

            <div className="relative mx-auto flex min-h-screen w-full flex-col items-center justify-center px-8 py-8 sm:max-w-md sm:px-4 sm:py-10">
                <div className="w-full max-w-[18.75rem] overflow-hidden rounded-2xl border border-white/55 bg-white/50 shadow-[0_24px_60px_-32px_rgba(10,45,130,0.65)] backdrop-blur-[2px] sm:max-w-md sm:rounded-3xl">
                    <div className="h-1 bg-gradient-to-r from-signal-deep via-signal to-signal-bright sm:h-1.5" />
                    <div className="px-4 py-5 sm:px-7 sm:py-8">
                        <div className="text-center">
                            <Logo className="mx-auto h-10 w-auto sm:h-12" markOnly alt={company} />
                            <p className="mt-3 text-[10px] font-semibold tracking-[0.16em] text-signal-deep uppercase sm:mt-4 sm:text-[11px]">
                                {company}
                            </p>
                            <h1 className="font-display mt-1 text-2xl leading-tight font-bold tracking-tight text-ink sm:text-3xl">
                                Daftar VPN Tunnel
                            </h1>
                            <p className="mx-auto mt-1.5 max-w-xs text-xs leading-relaxed text-ink-soft sm:mt-3 sm:text-sm">
                                Semua isian wajib diisi. Setelah daftar, masuk dengan email dan password. Akun VPN dibuat di dalam portal dan gratis 3 hari.
                            </p>
                        </div>

                        <div className="mt-4 grid grid-cols-3 gap-1.5 sm:mt-5 sm:gap-2">
                            {[
                                [Shield, 'Akun VPN'],
                                [CreditCard, 'Tagihan'],
                                [Router, 'CHR'],
                            ].map(([Icon, label]) => (
                                <div
                                    key={label}
                                    className="flex items-center justify-center gap-1.5 rounded-xl bg-mist/70 px-2 py-2 text-[11px] font-semibold text-ink-soft"
                                >
                                    <Icon className="h-3.5 w-3.5 text-signal-deep" />
                                    {label}
                                </div>
                            ))}
                        </div>

                        {flash?.error && (
                            <p className="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                {flash.error}
                            </p>
                        )}

                        {!open ? (
                            <p className="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                                Pendaftaran VPN Tunnel belum dibuka. Hubungi admin.
                            </p>
                        ) : (
                            <form
                                className="mt-5 space-y-3 sm:space-y-4"
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
                                        autoComplete="email"
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
                                    Password
                                    <input
                                        type="password"
                                        autoComplete="new-password"
                                        className={fieldClass}
                                        value={form.data.password}
                                        onChange={(event) => form.setData('password', event.target.value)}
                                        required
                                        minLength={8}
                                    />
                                    {form.errors.password && (
                                        <span className="mt-1 block text-xs text-red-600">{form.errors.password}</span>
                                    )}
                                </label>
                                <label className="block text-sm font-medium text-ink">
                                    Konfirmasi Password
                                    <input
                                        type="password"
                                        autoComplete="new-password"
                                        className={fieldClass}
                                        value={form.data.password_confirmation}
                                        onChange={(event) =>
                                            form.setData('password_confirmation', event.target.value)
                                        }
                                        required
                                        minLength={8}
                                    />
                                    {form.errors.password_confirmation && (
                                        <span className="mt-1 block text-xs text-red-600">
                                            {form.errors.password_confirmation}
                                        </span>
                                    )}
                                </label>
                                <label className="block text-sm font-medium text-ink">
                                    WhatsApp
                                    <input
                                        type="tel"
                                        autoComplete="tel"
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
                                <button type="submit" disabled={form.processing} className={primaryButton}>
                                    {form.processing ? 'Mendaftarkan...' : 'Daftar'}
                                </button>
                                <p className="text-center text-sm text-ink-soft">
                                    Sudah punya akun?{' '}
                                    <Link href="/vpn/masuk" className="font-semibold text-signal-deep hover:underline">
                                        Masuk
                                    </Link>
                                </p>
                            </form>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
