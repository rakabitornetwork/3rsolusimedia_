import { Head, useForm, usePage } from '@inertiajs/react';
import { CreditCard, Router, Wifi } from 'lucide-react';
import { useEffect, useState } from 'react';

const fieldClass =
    'mt-1.5 w-full rounded-xl border border-ink/10 bg-mist/40 px-3.5 py-3 text-sm outline-none transition focus:border-signal focus:bg-white focus:ring-4 focus:ring-signal/10 disabled:cursor-not-allowed disabled:opacity-60';

const primaryButton =
    'mt-6 w-full cursor-pointer rounded-xl bg-signal px-4 py-3 text-sm font-semibold text-white shadow-[0_12px_28px_-16px_rgba(26,110,255,0.95)] hover:bg-signal-deep disabled:cursor-not-allowed disabled:opacity-60';

const quietButton =
    'cursor-pointer rounded-xl border border-ink/10 bg-white px-3 py-2.5 text-sm font-medium text-ink hover:bg-mist disabled:cursor-not-allowed disabled:opacity-60';

export default function Index({ branding, gateway_ready, whatsapp_login }) {
    const { flash, errors } = usePage().props;
    const login = whatsapp_login || { enabled: false, pending: false, phone: '', phone_mask: '' };
    const [mode, setMode] = useState(errors?.username || errors?.phone ? 'username' : 'whatsapp');

    const lookup = useForm({
        username: '',
        phone: '',
    });
    const otp = useForm({
        phone: login.phone || '',
        code: '',
    });

    useEffect(() => {
        if (login.phone && otp.data.phone !== login.phone) {
            otp.setData('phone', login.phone);
        }
    }, [login.phone]);

    const submitLookup = (e) => {
        e.preventDefault();
        lookup.post('/portal/lookup');
    };

    const submitOtpRequest = (e) => {
        e.preventDefault();
        const phone = login.pending ? login.phone : otp.data.phone;
        otp.transform((data) => ({ phone: phone || data.phone }));
        otp.post('/portal/otp', {
            preserveScroll: true,
            onFinish: () => otp.transform((data) => data),
        });
    };

    const submitOtpVerify = (e) => {
        e.preventDefault();
        otp.transform((data) => ({
            phone: login.phone || data.phone,
            code: data.code,
        }));
        otp.post('/portal/otp/verify', {
            preserveScroll: true,
            onFinish: () => otp.transform((data) => data),
        });
    };

    const cancelOtp = (e) => {
        e.preventDefault();
        otp.post('/portal/otp/cancel', { preserveScroll: true });
    };

    const company = branding?.company_name || 'Portal';

    return (
        <div className="min-h-screen bg-paper text-ink">
            <div className="pointer-events-none fixed inset-0 -z-10 bg-[radial-gradient(ellipse_at_top,rgba(26,110,255,0.18),transparent_48%),linear-gradient(180deg,#e8eef2_0%,#f5f8fa_46%,#e8eef2_100%)]" />
            <Head title={`Portal Pelanggan · ${company}`} />

            <div className="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-10">
                <div className="overflow-hidden rounded-3xl border border-white/80 bg-white/85 shadow-[0_24px_60px_-32px_rgba(10,45,130,0.65)] backdrop-blur-md">
                    <div className="h-1.5 bg-gradient-to-r from-signal-deep via-signal to-signal-bright" />
                    <div className="px-5 py-6 sm:px-7 sm:py-8">
                        <div className="text-center">
                            {branding?.logo_mark ? (
                                <img
                                    src={branding.logo_mark}
                                    alt={company}
                                    className="mx-auto h-14 w-auto object-contain"
                                />
                            ) : (
                                <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-signal/15 text-signal-deep">
                                    <Wifi className="h-7 w-7" />
                                </div>
                            )}
                            <p className="mt-4 text-[11px] font-semibold tracking-[0.16em] text-signal-deep uppercase">
                                {company}
                            </p>
                            <h1 className="font-display mt-1 text-3xl leading-tight font-bold tracking-tight text-ink sm:text-4xl">
                                Portal Pelanggan
                            </h1>
                            <p className="mx-auto mt-3 max-w-xs text-sm leading-relaxed text-ink-soft">
                                Cek tagihan, bayar online, kelola WiFi, dan pantau perangkat dari satu tempat.
                            </p>
                        </div>

                        <div className="mt-5 grid grid-cols-3 gap-2">
                            {[
                                [CreditCard, 'Tagihan'],
                                [Wifi, 'WiFi'],
                                [Router, 'ONU'],
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

                        {(flash?.error || flash?.success) && (
                            <div
                                className={`mt-5 rounded-xl border px-4 py-3 text-sm ${
                                    flash.error
                                        ? 'border-red-200 bg-red-50 text-red-700'
                                        : 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                }`}
                            >
                                {flash.error || flash.success}
                            </div>
                        )}

                        {!gateway_ready && (
                            <div className="mt-5 rounded-xl border border-amber-line/40 bg-amber-50/80 px-4 py-3 text-sm text-ink-soft">
                                Pembayaran online mungkin belum aktif. Fitur perangkat dan WiFi tetap dapat digunakan jika ONU terpantau.
                            </div>
                        )}

                    {mode === 'username' ? (
                        <form onSubmit={submitLookup}>
                            <label className="mt-5 block text-sm font-medium text-ink">
                                Username PPPoE
                                <input
                                    type="text"
                                    value={lookup.data.username}
                                    onChange={(e) => lookup.setData('username', e.target.value)}
                                    className={fieldClass}
                                    placeholder="contoh: user01"
                                    autoComplete="username"
                                    required
                                />
                                {errors.username && (
                                    <p className="mt-1 text-xs text-red-600">{errors.username}</p>
                                )}
                            </label>

                            <label className="mt-4 block text-sm font-medium text-ink">
                                Nomor telepon
                                <input
                                    type="tel"
                                    value={lookup.data.phone}
                                    onChange={(e) => lookup.setData('phone', e.target.value)}
                                    className={fieldClass}
                                    placeholder="08xxxxxxxxxx"
                                    autoComplete="tel"
                                    required
                                />
                                {errors.phone && (
                                    <p className="mt-1 text-xs text-red-600">{errors.phone}</p>
                                )}
                            </label>

                            <button
                                type="submit"
                                disabled={lookup.processing}
                                className={primaryButton}
                            >
                                {lookup.processing ? 'Memeriksa...' : 'Masuk portal'}
                            </button>
                        </form>
                    ) : (
                        <div>
                            {!login.enabled && (
                                <p className="mt-5 text-sm text-ink-soft">
                                    Login WhatsApp belum aktif.
                                </p>
                            )}

                            {login.enabled && login.pending ? (
                                <form onSubmit={submitOtpVerify}>
                                    <p className="mt-5 text-sm text-ink-soft">
                                        Masukkan 6 digit kode yang dikirim ke WhatsApp{' '}
                                        <span className="font-medium text-ink">{login.phone_mask}</span>.
                                        Kode berlaku 5 menit.
                                    </p>
                                    <label className="mt-4 block text-sm font-medium text-ink">
                                        Kode WhatsApp
                                        <input
                                            type="text"
                                            inputMode="numeric"
                                            autoComplete="one-time-code"
                                            maxLength={6}
                                            value={otp.data.code}
                                            onChange={(e) =>
                                                otp.setData('code', e.target.value.replace(/\D/g, '').slice(0, 6))
                                            }
                                            className={fieldClass}
                                            placeholder="6 digit"
                                            required
                                        />
                                        {errors.code && (
                                            <p className="mt-1 text-xs text-red-600">{errors.code}</p>
                                        )}
                                    </label>
                                    {errors.whatsapp && (
                                        <p className="mt-3 text-xs text-red-600">{errors.whatsapp}</p>
                                    )}
                                    <button
                                        type="submit"
                                        disabled={otp.processing || otp.data.code.length !== 6}
                                        className={primaryButton}
                                    >
                                        {otp.processing ? 'Memeriksa...' : 'Masuk portal'}
                                    </button>
                                    <div className="mt-3 grid grid-cols-2 gap-2">
                                        <button
                                            type="button"
                                            onClick={submitOtpRequest}
                                            disabled={otp.processing}
                                            className={quietButton}
                                        >
                                            Kirim ulang
                                        </button>
                                        <button
                                            type="button"
                                            onClick={cancelOtp}
                                            disabled={otp.processing}
                                            className={quietButton}
                                        >
                                            Ganti nomor
                                        </button>
                                    </div>
                                </form>
                            ) : (
                                <form onSubmit={submitOtpRequest}>
                                    <label className="mt-5 block text-sm font-medium text-ink">
                                        Nomor WhatsApp terdaftar
                                        <input
                                            type="tel"
                                            value={otp.data.phone}
                                            onChange={(e) => otp.setData('phone', e.target.value)}
                                            className={fieldClass}
                                            placeholder="08xxxxxxxxxx"
                                            autoComplete="tel"
                                            required={login.enabled}
                                            disabled={!login.enabled}
                                        />
                                        {errors.whatsapp && (
                                            <p className="mt-1 text-xs text-red-600">{errors.whatsapp}</p>
                                        )}
                                    </label>
                                    <button
                                        type="submit"
                                        disabled={otp.processing || !login.enabled}
                                        className={primaryButton}
                                    >
                                        {otp.processing ? 'Mengirim...' : 'Kirim kode WhatsApp'}
                                    </button>
                                </form>
                            )}
                        </div>
                    )}

                        <button
                            type="button"
                            onClick={() => setMode(mode === 'whatsapp' ? 'username' : 'whatsapp')}
                            className="mt-5 w-full cursor-pointer text-center text-sm font-semibold text-signal-deep hover:underline"
                        >
                            {mode === 'whatsapp' ? 'Masuk dengan username' : 'Masuk dengan WhatsApp'}
                        </button>
                    </div>
                </div>

                <p className="mt-5 text-center text-xs leading-relaxed text-ink-soft">
                    Password PPPoE tidak diminta. Kode WhatsApp hanya dikirim ke nomor yang tersimpan di data pelanggan.
                </p>
            </div>
        </div>
    );
}
