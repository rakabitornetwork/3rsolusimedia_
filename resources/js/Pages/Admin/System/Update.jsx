import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowDownToLine,
    CheckCircle2,
    CloudUpload,
    ExternalLink,
    GitBranch,
    RefreshCw,
    Server,
    TriangleAlert,
} from 'lucide-react';
import AdminLayout from '../../../Layouts/AdminLayout';

function StatusBadge({ status, label }) {
    const tones = {
        up_to_date: 'bg-signal/15 text-signal-deep',
        behind: 'bg-amber-100 text-amber-800',
        ahead: 'bg-sky-100 text-sky-800',
        diverged: 'bg-red-100 text-red-800',
        unknown: 'bg-ink/10 text-ink-soft',
    };

    return (
        <span className={`px-2.5 py-1 text-xs font-semibold ${tones[status] || tones.unknown}`}>
            {label}
        </span>
    );
}

function InfoRow({ label, value, mono = false }) {
    return (
        <div className="grid gap-1 border-b border-ink/5 py-3 last:border-0 sm:grid-cols-[180px_1fr] sm:gap-4">
            <dt className="text-xs font-semibold tracking-wide text-ink-soft uppercase">{label}</dt>
            <dd className={`text-sm text-ink break-all ${mono ? 'font-mono text-xs sm:text-sm' : ''}`}>
                {value || '—'}
            </dd>
        </div>
    );
}

export default function Update({ repo }) {
    const { auth } = usePage().props;
    const canWrite = auth?.user?.can_write !== false;

    const checkUpdate = () => {
        router.post('/admin/system/update/check');
    };

    return (
        <AdminLayout
            title="Update"
            subtitle="Status repositori GitHub dan panduan sync ke VPS"
        >
            <Head title="Update" />

            <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
                <p className="max-w-2xl text-sm text-ink-soft">
                    Pantau koneksi ke GitHub dan status sync. Build frontend dilakukan di lokal
                    (Laragon), lalu di-push bersama folder <code className="text-ink">public/build</code>.
                    VPS cukup <code className="text-ink">git pull</code> — tanpa npm.
                </p>
                <div className="flex flex-wrap gap-2">
                    {repo?.github_url && (
                        <a
                            href={repo.github_url}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-2 border border-ink/15 bg-white px-3 py-2 text-xs font-semibold text-ink hover:bg-mist"
                        >
                            <ExternalLink className="h-3.5 w-3.5" />
                            Buka GitHub
                        </a>
                    )}
                    {canWrite && (
                        <button
                            type="button"
                            onClick={checkUpdate}
                            className="inline-flex items-center gap-2 bg-signal-deep px-3 py-2 text-xs font-semibold text-white hover:bg-ink"
                        >
                            <RefreshCw className="h-3.5 w-3.5" />
                            Cek update
                        </button>
                    )}
                </div>
            </div>

            <div className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <div className="border border-ink/10 bg-white p-4">
                    <p className="text-[11px] tracking-wide text-ink-soft uppercase">Status sync</p>
                    <div className="mt-3">
                        <StatusBadge status={repo?.sync_status} label={repo?.sync_label || '—'} />
                    </div>
                    <p className="mt-3 text-xs text-ink-soft">{repo?.message}</p>
                </div>
                <div className="border border-ink/10 bg-white p-4">
                    <p className="text-[11px] tracking-wide text-ink-soft uppercase">Commit lokal</p>
                    <p className="font-display mt-2 text-xl font-bold text-ink">
                        {repo?.local_commit_short || '—'}
                    </p>
                    <p className="mt-1 text-xs text-ink-soft">
                        Branch: {repo?.branch || '—'}
                        {repo?.dirty ? ' · Ada perubahan belum di-commit' : ''}
                    </p>
                </div>
                <div className="border border-ink/10 bg-white p-4 sm:col-span-2 xl:col-span-1">
                    <p className="text-[11px] tracking-wide text-ink-soft uppercase">Commit remote</p>
                    <p className="font-display mt-2 text-xl font-bold text-ink">
                        {repo?.remote_commit_short || '—'}
                    </p>
                    <p className="mt-1 text-xs text-ink-soft">
                        Ahead {repo?.ahead ?? 0} · Behind {repo?.behind ?? 0}
                    </p>
                </div>
            </div>

            {!repo?.available && (
                <div className="mb-5 flex items-start gap-3 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0" />
                    <p>
                        {repo?.message ||
                            'Status Git tidak tersedia. Pastikan aplikasi dijalankan dari clone Git.'}
                    </p>
                </div>
            )}

            <div className="mb-5 border border-ink/10 bg-white p-5 sm:p-6">
                <div className="mb-3 flex items-center gap-2">
                    <GitBranch className="h-4 w-4 text-signal-deep" />
                    <h2 className="text-sm font-semibold text-ink">Detail repositori</h2>
                </div>
                <dl>
                    <InfoRow label="Remote URL" value={repo?.remote_url} mono />
                    <InfoRow label="Branch" value={repo?.branch} />
                    <InfoRow label="Local HEAD" value={repo?.local_commit} mono />
                    <InfoRow label="Remote HEAD" value={repo?.remote_commit} mono />
                    <InfoRow
                        label="Working tree"
                        value={repo?.dirty ? 'Ada perubahan lokal belum di-commit' : 'Bersih'}
                    />
                    <InfoRow
                        label="Dicek pada"
                        value={
                            repo?.checked_at
                                ? new Date(repo.checked_at).toLocaleString('id-ID')
                                : null
                        }
                    />
                </dl>
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <section className="border border-ink/10 bg-white p-5 sm:p-6">
                    <div className="mb-3 flex items-center gap-2">
                        <CloudUpload className="h-4 w-4 text-signal-deep" />
                        <h2 className="text-sm font-semibold text-ink">Upload dari lokal (Laragon)</h2>
                    </div>
                    <ol className="list-decimal space-y-2 pl-4 text-sm text-ink-soft">
                        <li>
                            Setelah ubah frontend, jalankan{' '}
                            <code className="text-ink">npm run build</code>
                        </li>
                        <li>
                            Commit kode + folder{' '}
                            <code className="text-ink">public/build</code> (manifest &amp; aset)
                        </li>
                        <li>
                            Push ke GitHub:{' '}
                            <code className="text-ink">git push origin main</code>
                        </li>
                    </ol>
                    <p className="mt-3 text-xs text-ink-soft">
                        VPS tidak perlu Node/npm karena aset Vite sudah ikut di repositori.
                    </p>
                </section>

                <section className="border border-ink/10 bg-white p-5 sm:p-6">
                    <div className="mb-3 flex items-center gap-2">
                        <Server className="h-4 w-4 text-signal-deep" />
                        <h2 className="text-sm font-semibold text-ink">Update di VPS</h2>
                    </div>
                    <ol className="list-decimal space-y-2 pl-4 text-sm text-ink-soft">
                        <li>
                            <code className="text-ink">git pull origin main</code>
                        </li>
                        <li>
                            Jika ada dependency PHP baru:{' '}
                            <code className="text-ink">composer install --no-dev</code>
                        </li>
                        <li>
                            Jika ada migrasi:{' '}
                            <code className="text-ink">php artisan migrate --force</code>
                        </li>
                    </ol>
                    <p className="mt-3 inline-flex items-start gap-2 text-xs text-ink-soft">
                        <ArrowDownToLine className="mt-0.5 h-3.5 w-3.5 shrink-0 text-signal-deep" />
                        Jangan jalankan <code className="text-ink">npm run build</code> di VPS.
                    </p>
                </section>
            </div>

            {repo?.sync_status === 'up_to_date' && (
                <div className="mt-5 flex items-start gap-3 border border-signal/20 bg-signal/5 px-4 py-3 text-sm text-signal-deep">
                    <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" />
                    <p>Lokal dan GitHub sudah sinkron pada commit yang sama.</p>
                </div>
            )}

            <div className="mt-5">
                <Link
                    href="/admin/system"
                    className="text-sm font-semibold text-signal-deep hover:text-ink"
                >
                    ← Kembali ke Pengaturan Aplikasi
                </Link>
            </div>
        </AdminLayout>
    );
}
