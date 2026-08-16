import { Head, useForm, usePage } from '@inertiajs/react';
import Logo from '../../Icons/Logo';

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
        <>
            <Head title="Login Admin" />
            <div className="flex min-h-screen items-center justify-center bg-mist px-5">
                <div className="w-full max-w-md border border-ink/10 bg-white p-8 shadow-sm">
                    <Logo className="h-9 w-auto text-ink" alt={companyName} />
                    <h1 className="font-display mt-6 text-2xl font-bold text-ink">Panel Admin</h1>
                    <p className="mt-2 text-sm text-ink-soft">
                        Masuk untuk mengelola {companyName}.
                    </p>

                    <form onSubmit={submit} className="mt-8 space-y-4">
                        <label className="block text-sm font-medium text-ink">
                            Email
                            <input
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="mt-2 w-full border border-ink/15 px-4 py-3 outline-none focus:border-signal"
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
                                className="mt-2 w-full border border-ink/15 px-4 py-3 outline-none focus:border-signal"
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
                            className="w-full rounded-md bg-signal-deep px-4 py-3 text-sm font-bold text-white hover:bg-ink disabled:opacity-60"
                        >
                            {processing ? 'Masuk...' : 'Masuk'}
                        </button>
                    </form>
                </div>
            </div>
        </>
    );
}
