import { Head, Link } from '@inertiajs/react';
import { Construction } from 'lucide-react';
import AdminLayout from '../../Layouts/AdminLayout';

export default function ComingSoon({ module }) {
    return (
        <AdminLayout title={module.title} subtitle={module.subtitle}>
            <Head title={module.title} />

            <div className="border border-ink/10 bg-white p-8 sm:p-10">
                <div className="inline-flex rounded-md bg-signal/10 p-3 text-signal-deep">
                    <Construction className="h-5 w-5" strokeWidth={1.75} />
                </div>
                <h2 className="font-display mt-5 text-2xl font-bold text-ink">Modul segera hadir</h2>
                <p className="mt-3 max-w-2xl text-sm leading-relaxed text-ink-soft">
                    {module.description}
                </p>
                <p className="mt-4 text-xs tracking-wide text-ink/40 uppercase">
                    Status: dalam pengembangan · key: {module.key}
                </p>

                <div className="mt-8 flex flex-wrap gap-3">
                    <Link
                        href="/admin"
                        className="rounded-md bg-signal-deep px-4 py-2.5 text-sm font-semibold text-white hover:bg-ink"
                    >
                        Kembali ke Dashboard
                    </Link>
                    <Link
                        href="/admin/website/sections"
                        className="rounded-md border border-ink/15 px-4 py-2.5 text-sm font-semibold text-ink-soft hover:bg-mist"
                    >
                        Kelola Website dulu
                    </Link>
                </div>
            </div>
        </AdminLayout>
    );
}
