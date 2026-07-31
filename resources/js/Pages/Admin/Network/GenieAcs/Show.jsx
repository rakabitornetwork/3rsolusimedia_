import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, RefreshCw } from 'lucide-react';
import AdminLayout from '../../../../Layouts/AdminLayout';

function Row({ label, value }) {
    return (
        <div className="grid gap-1 border-b border-ink/5 py-3 last:border-0 sm:grid-cols-[180px_1fr] sm:gap-4">
            <dt className="text-xs font-semibold tracking-wide text-ink-soft uppercase">{label}</dt>
            <dd className="text-sm text-ink break-all">{value || '—'}</dd>
        </div>
    );
}

export default function Show({ device, ui_url }) {
    const { auth } = usePage().props;
    const canWrite = auth?.user?.can_write !== false;

    return (
        <AdminLayout
            title={device.serial || device.id}
            subtitle="Detail perangkat GenieACS"
        >
            <Head title={`GenieACS · ${device.serial || device.id}`} />

            <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <Link
                    href="/admin/network/genieacs"
                    className="inline-flex items-center gap-2 text-sm font-semibold text-signal-deep hover:text-ink"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Kembali ke daftar
                </Link>
                <div className="admin-toolbar-actions">
                    {ui_url && (
                        <a
                            href={`${ui_url.replace(/\/$/, '')}/#!/devices/${encodeURIComponent(device.id)}`}
                            target="_blank"
                            rel="noreferrer"
                            className="border border-ink/15 bg-white px-3 text-xs font-semibold text-ink hover:bg-mist"
                        >
                            <ExternalLink className="mr-1.5 h-3.5 w-3.5" />
                            Buka di GenieACS UI
                        </a>
                    )}
                    {canWrite && (
                        <button
                            type="button"
                            onClick={() =>
                                router.post(
                                    `/admin/network/genieacs/devices/${encodeURIComponent(device.id)}/summon`,
                                )
                            }
                            className="bg-signal-deep px-3 text-xs font-semibold text-white hover:bg-ink"
                        >
                            <RefreshCw className="mr-1.5 h-3.5 w-3.5" />
                            Summon / Refresh
                        </button>
                    )}
                </div>
            </div>

            <div className="mb-4 flex flex-wrap items-center gap-2">
                <span
                    className={`px-2 py-1 text-xs font-semibold ${
                        device.online
                            ? 'bg-signal/15 text-signal-deep'
                            : 'bg-ink/10 text-ink-soft'
                    }`}
                >
                    {device.online ? 'Online' : 'Offline'}
                </span>
                {(device.tags || []).map((tag) => (
                    <span key={tag} className="bg-mist px-2 py-1 text-xs font-semibold text-ink-soft">
                        {tag}
                    </span>
                ))}
            </div>

            <div className="border border-ink/10 bg-white p-5 sm:p-6">
                <h2 className="mb-2 text-sm font-semibold text-ink">Identitas perangkat</h2>
                <dl>
                    <Row label="Device ID" value={device.id} />
                    <Row label="Manufacturer" value={device.manufacturer} />
                    <Row label="Model" value={device.model} />
                    <Row label="Serial Number" value={device.serial} />
                    <Row label="Product Class" value={device.product_class} />
                    <Row label="OUI" value={device.oui} />
                    <Row label="Software Version" value={device.software_version} />
                    <Row label="Hardware Version" value={device.hardware_version} />
                    <Row label="Last Inform" value={device.last_inform_label} />
                </dl>
            </div>

            {device.raw_keys?.length > 0 && (
                <div className="mt-4 border border-ink/10 bg-white p-5 sm:p-6">
                    <h2 className="mb-2 text-sm font-semibold text-ink">Root objek TR-069</h2>
                    <p className="mb-3 text-xs text-ink-soft">
                        Objek yang tersedia pada dokumen perangkat di GenieACS.
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {device.raw_keys.map((key) => (
                            <span key={key} className="bg-mist px-2 py-1 text-xs font-medium text-ink">
                                {key}
                            </span>
                        ))}
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
