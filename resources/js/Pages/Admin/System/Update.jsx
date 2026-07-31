import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowDownToLine,
    CheckCircle2,
    GitBranch,
    RefreshCw,
    Tag,
    TriangleAlert,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import UpdateTerminal from '../../../Components/Admin/UpdateTerminal';
import AdminLayout from '../../../Layouts/AdminLayout';

function StatusBadge({ status, label }) {
    const tones = {
        up_to_date: 'bg-white/15 text-white',
        behind: 'bg-amber-300/25 text-amber-50',
        ahead: 'bg-white/15 text-white',
        diverged: 'bg-red-300/25 text-red-50',
        unknown: 'bg-white/10 text-white/80',
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

const TERMINAL_SESSION_KEY = 'update-terminal-session';
const SUPPRESS_TOAST_KEY = 'update-suppress-toast';

function flashResultFromFlash(flash) {
    if (flash?.success) {
        return { type: 'success', message: String(flash.success) };
    }
    if (flash?.error) {
        return { type: 'error', message: String(flash.error) };
    }
    return null;
}

function flashResultFromPage(page) {
    return (
        flashResultFromFlash(page?.props?.flash) || {
            type: 'success',
            message: 'Operasi selesai.',
        }
    );
}

export default function Update({ repo }) {
    const { auth, flash } = usePage().props;
    const canWrite = auth?.user?.can_write !== false;
    const canPull = Boolean(canWrite && repo?.can_pull);
    const [terminalOpen, setTerminalOpen] = useState(false);
    const [terminalResult, setTerminalResult] = useState(null);
    const [checking, setChecking] = useState(false);
    const seenFlashRef = useRef('');
    const busy = terminalOpen && !terminalResult;

    const closeTerminal = () => {
        setTerminalOpen(false);
        setTerminalResult(null);
        try {
            sessionStorage.removeItem(TERMINAL_SESSION_KEY);
        } catch {
            // ignore
        }
    };

    const rememberPullSession = () => {
        try {
            sessionStorage.setItem(
                TERMINAL_SESSION_KEY,
                JSON.stringify({ mode: 'pull', at: Date.now() }),
            );
            sessionStorage.setItem(SUPPRESS_TOAST_KEY, '1');
        } catch {
            // ignore
        }
    };

    // Flash hasil Pull saja yang masuk ke terminal (bukan Cek update).
    useEffect(() => {
        const result = flashResultFromFlash(flash);
        if (!result) return;

        let isPullSession = false;
        try {
            const raw = sessionStorage.getItem(TERMINAL_SESSION_KEY);
            if (raw) {
                const parsed = JSON.parse(raw);
                isPullSession = parsed?.mode === 'pull';
                if (isPullSession) {
                    sessionStorage.removeItem(TERMINAL_SESSION_KEY);
                }
            }
        } catch {
            // ignore
        }

        if (!isPullSession) return;

        const signature = `${result.type}:${result.message}`;
        if (seenFlashRef.current === signature) return;
        seenFlashRef.current = signature;

        setTerminalResult(result);
        setTerminalOpen(true);
    }, [flash?.success, flash?.error]);

    const checkUpdate = () => {
        if (checking || busy) return;
        setChecking(true);

        router.post('/admin/system/update/check', {}, {
            preserveScroll: true,
            onFinish: () => setChecking(false),
        });
    };

    const pullUpdate = () => {
        if (!canPull || busy) return;
        if (
            !window.confirm(
                'Pull update dari GitHub ke server ini?\n\nPerintah npm run build tidak akan dijalankan.',
            )
        ) {
            return;
        }

        rememberPullSession();
        setTerminalResult(null);
        setTerminalOpen(true);

        router.post('/admin/system/update/pull', {}, {
            preserveScroll: true,
            onSuccess: (page) => setTerminalResult(flashResultFromPage(page)),
            onError: () =>
                setTerminalResult({
                    type: 'error',
                    message: 'Gagal pull dari GitHub.',
                }),
        });
    };

    return (
        <AdminLayout
            title="Update"
            subtitle="Status repositori GitHub dan panduan sync ke VPS"
        >
            <Head title="Update" />

            <UpdateTerminal
                open={terminalOpen}
                mode="pull"
                branch={repo?.branch}
                behind={repo?.behind || 0}
                result={terminalResult}
                onClose={closeTerminal}
            />

            {canWrite && (
                <div className="mb-5 flex flex-wrap justify-end gap-2">
                    <button
                        type="button"
                        onClick={checkUpdate}
                        disabled={checking || busy}
                        className="inline-flex items-center gap-2 border border-ink/15 bg-white px-3 py-2 text-xs font-semibold text-ink hover:bg-mist disabled:opacity-60"
                    >
                        <RefreshCw
                            className={`h-3.5 w-3.5 ${checking ? 'animate-spin' : ''}`}
                        />
                        Cek update
                    </button>
                    <button
                        type="button"
                        onClick={pullUpdate}
                        disabled={!canPull || busy || checking}
                        title={
                            canPull
                                ? 'Pull update terbaru dari GitHub (tanpa npm)'
                                : 'Tidak ada update terbaru dari GitHub, atau pull belum aman'
                        }
                        className="inline-flex items-center gap-2 bg-signal-deep px-3 py-2 text-xs font-semibold text-white hover:bg-ink disabled:cursor-not-allowed disabled:bg-ink/25 disabled:hover:bg-ink/25"
                    >
                        <ArrowDownToLine className="h-3.5 w-3.5" />
                        Pull dari GitHub
                    </button>
                </div>
            )}

            <div className="mb-5 overflow-hidden border border-ink/10 bg-white">
                <div className="flex h-1.5 w-full">
                    <span className="flex-1 bg-gradient-to-r from-teal-400 to-teal-800" />
                    <span className="flex-1 bg-gradient-to-r from-slate-500 to-slate-900" />
                    <span className="flex-1 bg-gradient-to-r from-sky-400 to-blue-900" />
                </div>
                <div className="flex flex-wrap items-center justify-between gap-4 px-5 py-4 sm:px-6">
                    <div className="flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 items-center justify-center bg-gradient-to-br from-teal-500 to-teal-900 text-white">
                            <Tag className="h-4 w-4" />
                        </span>
                        <div>
                            <p className="text-[11px] font-semibold tracking-wide text-ink-soft uppercase">
                                Versi aplikasi (GitHub tag)
                            </p>
                            <p className="font-display mt-1 text-3xl font-bold tracking-tight text-ink">
                                {repo?.latest_tag || repo?.local_version
                                    ? `v${repo?.latest_tag || repo?.local_version}`
                                    : '—'}
                            </p>
                            <p className="mt-1 text-xs text-ink-soft">
                                Tag diambil dari repositori GitHub
                                {repo?.local_version_full
                                    ? ` · describe: ${repo.local_version_full}`
                                    : ''}
                            </p>
                        </div>
                    </div>
                    <div className="grid min-w-[220px] gap-2 text-sm sm:text-right">
                        <p className="text-ink">
                            <span className="text-ink-soft">Lokal:</span>{' '}
                            <strong>
                                {repo?.local_version ? `v${repo.local_version}` : '—'}
                            </strong>
                            {repo?.commits_since_tag > 0
                                ? ` (+${repo.commits_since_tag} commit)`
                                : ''}
                        </p>
                        <p className="text-ink">
                            <span className="text-ink-soft">GitHub:</span>{' '}
                            <strong>
                                {repo?.remote_version ? `v${repo.remote_version}` : '—'}
                            </strong>
                        </p>
                    </div>
                </div>
            </div>

            <div className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <div className="flex min-h-[122px] flex-col bg-gradient-to-br from-teal-500 to-teal-950 p-4 text-white shadow-sm">
                    <p className="text-[11px] tracking-wide text-teal-50/80 uppercase">Status sync</p>
                    <div className="mt-3">
                        <StatusBadge status={repo?.sync_status} label={repo?.sync_label || '—'} />
                    </div>
                    <p className="mt-auto pt-3 text-xs text-teal-50/80">{repo?.message}</p>
                </div>
                <div className="flex min-h-[122px] flex-col bg-gradient-to-br from-slate-500 to-slate-950 p-4 text-white shadow-sm">
                    <p className="text-[11px] tracking-wide text-slate-200/80 uppercase">
                        Commit lokal
                    </p>
                    <p className="font-display mt-2 text-2xl font-bold tracking-tight">
                        {repo?.local_commit_short || '—'}
                    </p>
                    <p className="mt-auto pt-2 text-xs text-slate-200/75">
                        Versi {repo?.local_version ? `v${repo.local_version}` : '—'}
                        {repo?.dirty ? ' · Ada perubahan belum di-commit' : ''}
                    </p>
                </div>
                <div className="flex min-h-[122px] flex-col bg-gradient-to-br from-sky-500 to-blue-950 p-4 text-white shadow-sm sm:col-span-2 xl:col-span-1">
                    <p className="text-[11px] tracking-wide text-sky-100/80 uppercase">
                        Commit remote
                    </p>
                    <p className="font-display mt-2 text-2xl font-bold tracking-tight">
                        {repo?.remote_commit_short || '—'}
                    </p>
                    <p className="mt-auto pt-2 text-xs text-sky-100/75">
                        Versi {repo?.remote_version ? `v${repo.remote_version}` : '—'} · Ahead{' '}
                        {repo?.ahead ?? 0} · Behind {repo?.behind ?? 0}
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
                    <InfoRow
                        label="Versi (tag)"
                        value={
                            repo?.latest_tag || repo?.local_version
                                ? `v${repo?.latest_tag || repo?.local_version}`
                                : null
                        }
                    />
                    <InfoRow
                        label="Versi lokal"
                        value={
                            repo?.local_version_full
                                ? `v${String(repo.local_version_full).replace(/^v/i, '')}`
                                : null
                        }
                        mono
                    />
                    <InfoRow
                        label="Versi GitHub"
                        value={repo?.remote_version ? `v${repo.remote_version}` : null}
                    />
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
