import { Head, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Wifi } from 'lucide-react';

const fieldClass =
    'mt-1.5 w-full border border-ink/15 bg-white px-3 py-2.5 text-sm outline-none focus:border-signal';

export default function Index({ branding, gateway_ready, whatsapp_login }) {
    const { flash, errors } = usePage().props;
    const login = whatsapp_login || { enabled: false, pending: false, phone: '', phone_mask: '' };
    const [mode, setMode] = useState(login.pending || errors?.whatsapp || errors?.code ? 'whatsapp' : 'username');

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

    return (
        <div className="min-h-screen bg-gradient-to-b from-mist via-white to-mist text-ink">
            <Head title={`Portal Pelanggan · ${branding?.company_name || 'Portal'}`} />

            <div className="mx-auto flex min-h-screen max-w-lg flex-col justify-center px-4 py-10">
                <div className="mb-8 text-center">
                    {branding?.logo_mark ? (
                        <img
                            src={branding.logo_mark}
                            alt={branding.company_name || 'Logo'}
                            className="mx-auto h-14 w-auto object-contain"
                        />
                    ) : (
                        <div className="mx-auto flex h-14 w-14 items-center justify-center bg-signal/15 text-signal-deep">
                            <Wifi className="h-7 w-7" />
                        </div>
                    )}
                    <h1 className="mt-4 text-2xl font-semibold tracking-tight text-ink">
                        Portal Pelanggan
                    </h1>
                    <p className="mt-2 text-sm text-ink-soft">
                        Cek tagihan, bayar online, kelola WiFi, pantau redaman/suhu ONU, dan restart
                        perangkat secara mandiri.
                    </p>
                </div>

                {(flash?.error || flash?.success) && (
                    <div
                        className={`mb-4 border px-4 py-3 text-sm ${
                            flash.error
                                ? 'border-red-200 bg-red-50 text-red-700'
                                : 'border-emerald-200 bg-emerald-50 text-emerald-800'
                        }`}
                    >
                        {flash.error || flash.success}
                    </div>
                )}

                {!gateway_ready && (
                    <div className="mb-4 border border-ink/10 bg-white px-4 py-3 text-sm text-ink-soft">
                        Pembayaran online mungkin belum aktif. Fitur perangkat & WiFi tetap dapat
                        digunakan jika ONU terpantau.
                    </div>
                )}

                <div className="border border-ink/10 bg-white p-6 shadow-sm">
                    <div className="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            onClick={() => setMode('username')}
                            className={`px-3 py-2 text-sm font-medium ${
                                mode === 'username'
                                    ? 'bg-signal text-white'
                                    : 'border border-ink/15 text-ink'
                            }`}
                        >
                            Username
                        </button>
                        <button
                            type="button"
                            onClick={() => setMode('whatsapp')}
                            className={`px-3 py-2 text-sm font-medium ${
                                mode === 'whatsapp'
                                    ? 'bg-signal text-white'
                                    : 'border border-ink/15 text-ink'
                            }`}
                        >
                            WhatsApp
                        </button>
                    </div>

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
                                className="mt-6 w-full bg-signal px-4 py-3 text-sm font-semibold text-white hover:bg-signal-deep disabled:opacity-60"
                            >
                                {lookup.processing ? 'Memeriksa...' : 'Masuk portal'}
                            </button>
                        </form>
                    ) : (
                        <div>
                            {!login.enabled && (
                                <p className="mt-5 text-sm text-ink-soft">
                                    Login WhatsApp belum aktif. Gunakan username PPPoE dan nomor
                                    telepon.
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
                                        className="mt-6 w-full bg-signal px-4 py-3 text-sm font-semibold text-white hover:bg-signal-deep disabled:opacity-60"
                                    >
                                        {otp.processing ? 'Memeriksa...' : 'Masuk portal'}
                                    </button>
                                    <div className="mt-3 grid grid-cols-2 gap-2">
                                        <button
                                            type="button"
                                            onClick={submitOtpRequest}
                                            disabled={otp.processing}
                                            className="border border-ink/15 px-3 py-2 text-sm text-ink hover:border-signal disabled:opacity-60"
                                        >
                                            Kirim ulang
                                        </button>
                                        <button
                                            type="button"
                                            onClick={cancelOtp}
                                            disabled={otp.processing}
                                            className="border border-ink/15 px-3 py-2 text-sm text-ink hover:border-signal disabled:opacity-60"
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
                                        className="mt-6 w-full bg-signal px-4 py-3 text-sm font-semibold text-white hover:bg-signal-deep disabled:opacity-60"
                                    >
                                        {otp.processing ? 'Mengirim...' : 'Kirim kode WhatsApp'}
                                    </button>
                                </form>
                            )}
                        </div>
                    )}
                </div>

                <p className="mt-6 text-center text-xs text-ink-soft">
                    Password PPPoE tidak diminta. Kode WhatsApp hanya dikirim ke nomor yang tersimpan
                    di data pelanggan.
                </p>
            </div>
        </div>
    );
}
