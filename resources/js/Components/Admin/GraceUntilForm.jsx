import { router } from '@inertiajs/react';
import { useState } from 'react';
import { keepPage } from '../../lib/keepPage';
import DatePickerField from './DatePickerField';

function todayIso() {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
}

export default function GraceUntilForm({
    customerId,
    graceUntil = '',
    graceNote = '',
    compact = false,
    onDone,
}) {
    const min = todayIso();
    const [until, setUntil] = useState(graceUntil && graceUntil >= min ? graceUntil : '');
    const [note, setNote] = useState(graceNote || '');
    const [saving, setSaving] = useState(false);

    const submit = (event) => {
        event.preventDefault();
        if (!until || saving) return;

        setSaving(true);
        router.post(
            `/admin/billing/customers/${customerId}/grace`,
            {
                grace_until: until,
                note: note.trim() || undefined,
            },
            {
                ...keepPage,
                onFinish: () => setSaving(false),
                onSuccess: () => onDone?.(),
            },
        );
    };

    return (
        <form onSubmit={submit} className={compact ? 'space-y-2 px-3 py-2' : 'mt-4 space-y-3'}>
            <DatePickerField
                label="Sampai tanggal"
                value={until}
                min={min}
                compact={compact}
                onChange={setUntil}
            />
            <label className={`block font-medium text-ink ${compact ? 'text-xs' : 'text-sm'}`}>
                Catatan
                <input
                    type="text"
                    value={note}
                    maxLength={255}
                    onChange={(event) => setNote(event.target.value)}
                    placeholder="Opsional"
                    className={`w-full border border-ink/15 px-3 outline-none focus:border-signal ${
                        compact ? 'mt-1 py-2 text-xs' : 'mt-1.5 py-2.5 text-sm'
                    }`}
                />
            </label>
            <button
                type="submit"
                disabled={!until || saving}
                className="btn-action btn-action-xs btn-warn w-full"
            >
                {saving ? 'Menyimpan...' : 'Simpan toleransi'}
            </button>
        </form>
    );
}
