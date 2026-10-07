import { Head, useForm, usePage } from '@inertiajs/react';
import { CreditCard, Router, Wifi } from 'lucide-react';
import { useEffect } from 'react';

const fieldClass =
    'mt-1.5 w-full rounded-lg border border-ink/10 bg-mist/40 px-3 py-2 text-sm outline-none transition focus:border-signal focus:bg-white focus:ring-4 focus:ring-signal/10 disabled:cursor-not-allowed disabled:opacity-60 sm:rounded-xl sm:px-3.5 sm:py-3';

const primaryButton =
    'mt-4 w-full cursor-pointer rounded-lg bg-signal px-4 py-2.5 text-sm font-semibold text-white shadow-[0_12px_28px_-16px_rgba(26,110,255,0.95)] hover:bg-signal-deep disabled:cursor-not-allowed disabled:opacity-60 sm:mt-6 sm:rounded-xl sm:py-3';

const quietButton =
    'cursor-pointer rounded-xl border border-ink/10 bg-white px-3 py-2.5 text-sm font-medium text-ink hover:bg-mist disabled:cursor-not-allowed disabled:opacity-60';

export default function Index({ branding, gateway_ready, whatsapp_login }) {
    const { flash, errors } = usePage().props;
    const login = whatsapp_login || { enabled: false, pending: false, phone: '', phone_mask: '' };

    const otp = useForm({
        phone: login.phone || '',
        code: '',
    });

    useEffect(() => {
        if (login.phone && otp.data.phone !== login.phone) {
            otp.setData('phone', login.phone);
        }
    }, [login.phone]);

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
        <div className="relative min-h-screen text-ink">
            <div className="pointer-events-none fixed inset-0">
                <img
                    src="/images/portal/login-bg-huawei.png"
                    alt=""
                    className="h-full w-full object-cover"
                />
                <div className="absolute inset-0 bg-ink/40" />
            </div>
            <Head title={`Portal Pelanggan · ${company}`} />

            <div className="relative mx-auto flex min-h-screen w-full flex-col items-center justify-center px-8 py-8 sm:max-w-md sm:px-4 sm:py-10">
                <div className="w-full max-w-[18.75rem] overflow-hidden rounded-2xl border border-white/55 bg-white/50 shadow-[0_24px_60px_-32px_rgba(10,45,130,0.65)] backdrop-blur-[2px] sm:max-w-md sm:rounded-3xl">
                    <div className="h-1 bg-gradient-to-r from-signal-deep via-signal to-signal-bright sm:h-1.5" />
                    <div className="px-4 py-5 sm:px-7 sm:py-8">
                        <div className="text-center">
                            {branding?.logo_mark ? (
                                <img
                                    src={branding.logo_mark}
                                    alt={company}
                                    className="mx-auto h-10 w-auto object-contain sm:h-12"
                                />
                            ) : (
                                <div className="mx-auto flex h-10 w-10 items-center justify-center rounded-xl bg-signal/15 text-signal-deep sm:h-12 sm:w-12 sm:rounded-2xl">
                                    <Wifi className="h-5 w-5 sm:h-6 sm:w-6" />
                                </div>
                            )}
                            <p className="mt-3 text-[10px] font-semibold tracking-[0.16em] text-signal-deep uppercase sm:mt-4 sm:text-[11px]">
                                {company}
                            </p>
                            <h1 className="font-display mt-1 text-2xl leading-tight font-bold tracking-tight text-ink sm:text-3xl">
                                Portal Pelanggan
                            </h1>
                            <p className="mx-auto mt-1.5 max-w-xs text-xs leading-relaxed text-ink-soft sm:mt-3 sm:text-sm">
                                Masuk dengan nomor WhatsApp yang terdaftar. Kode OTP dikirim ke WhatsApp itu.
                            </p>
                        </div>

                        <div className="mt-4 grid grid-cols-3 gap-1.5 sm:mt-5 sm:gap-2">
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
                    </div>
                </div>
            </div>
        </div>
    );
}
