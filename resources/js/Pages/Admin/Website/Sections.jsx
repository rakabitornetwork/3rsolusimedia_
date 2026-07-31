import { Head, Link } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';

export default function Sections({ sections }) {

    return (
        <AdminLayout
            title="Konten Landing"
            subtitle="Kelola section website yang tampil di halaman publik"
        >
            <Head title="Konten Landing" />

            <div className="admin-data-scroll border border-ink/10 bg-white">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-ink/10 bg-mist/60 text-xs tracking-wide text-ink-soft uppercase">
                        <tr>
                            <th className="px-4 py-3 font-semibold">Section</th>
                            <th className="hidden px-4 py-3 font-semibold sm:table-cell">Judul</th>
                            <th className="px-4 py-3 font-semibold">Status</th>
                            <th className="px-4 py-3 font-semibold">Urutan</th>
                            <th className="px-4 py-3 font-semibold" />
                        </tr>
                    </thead>
                    <tbody>
                        {sections.map((section) => (
                            <tr key={section.id} className="border-b border-ink/5 last:border-0">
                                <td className="px-4 py-4 font-medium text-ink">{section.label}</td>
                                <td className="hidden max-w-xs truncate px-4 py-4 text-ink-soft sm:table-cell">
                                    {section.title || '—'}
                                </td>
                                <td className="px-4 py-4">
                                    <span
                                        className={`inline-flex px-2.5 py-1 text-xs font-semibold ${
                                            section.is_visible
                                                ? 'bg-signal/15 text-signal-deep'
                                                : 'bg-ink/10 text-ink-soft'
                                        }`}
                                    >
                                        {section.is_visible ? 'Aktif' : 'Disembunyikan'}
                                    </span>
                                </td>
                                <td className="px-4 py-4 text-ink-soft">{section.sort_order}</td>
                                <td className="px-4 py-4 text-right">
                                    <Link
                                        href={`/admin/sections/${section.id}/edit`}
                                        className="font-semibold text-signal-deep hover:underline"
                                    >
                                        Edit
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
