import { Head, useForm, usePage } from '@inertiajs/react';
import Logo from '../../Icons/Logo';

const fieldClass =
    'mt-1.5 w-full rounded-lg border border-ink/10 bg-white/70 px-3 py-2 text-sm outline-none transition focus:border-signal focus:bg-white focus:ring-4 focus:ring-signal/10 sm:rounded-xl sm:px-3.5 sm:py-3';

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

            <div className="relative mx-auto flex min-h-screen w-full flex-col items-center justify-center px-8 py-8 sm:max-w-md sm:px-4 sm:py-10">
                <div className="w-full max-w-[18.75rem] overflow-hidden rounded-2xl border border-white/55 bg-white/50 shadow-[0_24px_60px_-32px_rgba(10,45,130,0.65)] backdrop-blur-[2px] sm:max-w-md sm:rounded-3xl">
                    <div className="h-1 bg-gradient-to-r from-signal-deep via-signal to-signal-bright sm:h-1.5" />
                    <div className="px-4 py-5 sm:px-7 sm:py-8">
                        <Logo className="h-8 w-auto text-ink sm:h-9" alt={companyName} />
                        <h1 className="font-display mt-3 text-2xl font-bold tracking-tight text-ink sm:mt-5 sm:text-3xl">
                            Panel Admin
                        </h1>
                        <p className="mt-1.5 text-xs leading-relaxed text-ink-soft sm:mt-2 sm:text-sm">
                            Masuk untuk mengelola {companyName}.
                        </p>

                        <form onSubmit={submit} className="mt-4 space-y-3 sm:mt-6 sm:space-y-4">
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
                                className="mt-1 w-full cursor-pointer rounded-lg bg-signal px-4 py-2.5 text-sm font-semibold text-white shadow-[0_12px_28px_-16px_rgba(26,110,255,0.95)] hover:bg-signal-deep disabled:cursor-not-allowed disabled:opacity-60 sm:mt-2 sm:rounded-xl sm:py-3"
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
