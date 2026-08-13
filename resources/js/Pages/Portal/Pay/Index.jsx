import { Head, useForm, usePage } from '@inertiajs/react';
import { Wifi } from 'lucide-react';

const fieldClass =
    'mt-1.5 w-full border border-ink/15 bg-white px-3 py-2.5 text-sm outline-none focus:border-signal';

export default function Index({ branding, gateway_ready }) {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({
        username: '',
        phone: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/portal/lookup');
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

                <form onSubmit={submit} className="border border-ink/10 bg-white p-6 shadow-sm">
                    <label className="block text-sm font-medium text-ink">
                        Username PPPoE
                        <input
                            type="text"
                            value={data.username}
                            onChange={(e) => setData('username', e.target.value)}
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
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
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
                        disabled={processing}
                        className="mt-6 w-full bg-signal px-4 py-3 text-sm font-semibold text-white hover:bg-signal-deep disabled:opacity-60"
                    >
                        {processing ? 'Memeriksa...' : 'Masuk portal'}
                    </button>
                </form>

                <p className="mt-6 text-center text-xs text-ink-soft">
                    Password PPPoE tidak diminta. Data hanya dipakai untuk menampilkan akun Anda.
                </p>
            </div>
        </div>
    );
}
