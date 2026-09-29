import { Head, useForm, usePage } from '@inertiajs/react';
import Logo from '../../Icons/Logo';

const fieldClass =
    'mt-1.5 w-full rounded-xl border border-ink/10 bg-white/70 px-3.5 py-3 text-sm outline-none transition focus:border-signal focus:bg-white focus:ring-4 focus:ring-signal/10';

export default function Login() {
    const companyName = usePage().props.app?.company_name || 'Perusahaan';
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: true,
    });

    const submit = (e) => {
        e.preventDefault();
        post('/admin/login');
    };

    return (
        <div className="relative min-h-screen text-ink">
            <div className="pointer-events-none fixed inset-0">
                <img
                    src="/images/admin/login-bg.png"
                    alt=""
                    className="h-full w-full object-cover"
                />
                <div className="absolute inset-0 bg-ink/40" />
            </div>
            <Head title="Login Admin" />

            <div className="relative mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-10">
                <div className="overflow-hidden rounded-3xl border border-white/55 bg-white/50 shadow-[0_24px_60px_-32px_rgba(10,45,130,0.65)] backdrop-blur-[2px]">
                    <div className="h-1.5 bg-gradient-to-r from-signal-deep via-signal to-signal-bright" />
                    <div className="px-5 py-6 sm:px-7 sm:py-8">
                        <Logo className="h-9 w-auto text-ink" alt={companyName} />
                        <h1 className="font-display mt-5 text-3xl font-bold tracking-tight text-ink">
                            Panel Admin
                        </h1>
                        <p className="mt-2 text-sm leading-relaxed text-ink-soft">
                            Masuk untuk mengelola {companyName}.
                        </p>

                        <form onSubmit={submit} className="mt-6 space-y-4">
                            <label className="block text-sm font-medium text-ink">
                                Email
                                <input
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    className={fieldClass}
                                    autoComplete="username"
                                    required
                                />
                                {errors.email && (
                                    <span className="mt-1 block text-xs text-red-600">{errors.email}</span>
                                )}
                            </label>

                            <label className="block text-sm font-medium text-ink">
                                Password
                                <input
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    className={fieldClass}
                                    autoComplete="current-password"
                                    required
                                />
                            </label>

                            <label className="flex items-center gap-2 text-sm text-ink-soft">
                                <input
                                    type="checkbox"
                                    checked={data.remember}
                                    onChange={(e) => setData('remember', e.target.checked)}
                                />
                                Ingat saya
                            </label>

                            <button
                                type="submit"
                                disabled={processing}
                                className="mt-2 w-full cursor-pointer rounded-xl bg-signal px-4 py-3 text-sm font-semibold text-white shadow-[0_12px_28px_-16px_rgba(26,110,255,0.95)] hover:bg-signal-deep disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {processing ? 'Masuk...' : 'Masuk'}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    );
}
