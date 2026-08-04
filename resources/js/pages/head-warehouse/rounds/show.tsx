import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Download, LogOut } from 'lucide-react';
import type { FormEvent } from 'react';
import { useOnlineStatus } from '@/lib/use-online-status';

type RoundPhoto = {
    id: number;
    type: string;
    type_label: string;
    url: string;
};

type RoundAttachment = {
    id: number;
    type: string;
    type_label: string;
    url: string;
};

type RoundItem = {
    id: number;
    item_code: string | null;
    item_name: string;
    po_number: string | null;
    qty: number;
    unit: string | null;
    quality_status: string;
    quantity_status: string;
    received_qty: number | null;
    damaged_qty: number;
    qty_remaining: number;
    photo_count: number;
    photos: RoundPhoto[];
};

type Round = {
    id: number;
    round_number: number;
    round_status: string;
    round_status_value: string;
    completion_result: string | null;
    specification_matches_po: boolean;
    notes: string | null;
    delivery_note_number: string | null;
    submitted_at: string | null;
    head_warehouse_signed_at: string | null;
    head_warehouse_notes: string | null;
    user_signed_at: string | null;
    user_notes: string | null;
    rejection_reason: string | null;
    pdf_download_url: string | null;
    inspection: {
        pb_number: string;
        po_number: string | null;
        supplier_name: string | null;
        location_name: string | null;
    };
    items: RoundItem[];
    attachments: RoundAttachment[];
    partial_reasons: string[];
    can_sign: boolean;
    can_reject: boolean;
};

type Props = {
    round: Round;
};

export default function HeadWarehouseRoundShow({ round }: Props) {
    const online = useOnlineStatus();
    const { data, setData, post, processing, errors } = useForm<{
        notes: string;
        round?: string;
    }>({ notes: '' });
    const showRemainingQty =
        round.completion_result === 'PARTIAL' ||
        round.partial_reasons.length > 0;

    function sign() {
        post(`/head-warehouse/rounds/${round.id}/sign`);
    }

    function reject(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        post(`/head-warehouse/rounds/${round.id}/reject`);
    }

    return (
        <>
            <Head
                title={`Head Warehouse - Penerimaan Barang ${round.inspection.pb_number}`}
            />
            <main className="min-h-screen bg-slate-50 px-4 py-6 text-slate-950">
                <section className="mx-auto w-full max-w-5xl">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p className="text-sm font-medium text-teal-700">
                                Head Warehouse -{' '}
                                {round.inspection.location_name ?? '-'}
                            </p>
                            <h1 className="mt-2 text-2xl font-semibold">
                                Penerimaan Barang {round.inspection.pb_number} /
                                Round {round.round_number}
                            </h1>
                            <StatusChip
                                status={round.round_status}
                                completionResult={round.completion_result}
                            />
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {round.pdf_download_url && (
                                <a
                                    href={round.pdf_download_url}
                                    className="inline-flex items-center gap-2 rounded-md bg-teal-700 px-3 py-2 text-sm font-medium text-white hover:bg-teal-800"
                                >
                                    <Download className="h-4 w-4" />
                                    PDF
                                </a>
                            )}
                            <Link
                                href="/dashboard/head-warehouse"
                                className="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                <ArrowLeft className="h-4 w-4" />
                                Kembali
                            </Link>
                            <Link
                                href="/logout"
                                method="post"
                                as="button"
                                className="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                <LogOut className="h-4 w-4" />
                                Logout
                            </Link>
                        </div>
                    </div>

                    {errors.round && (
                        <div className="mt-5 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                            {errors.round}
                        </div>
                    )}

                    {!online && (
                        <div className="mt-5 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                            Koneksi internet diperlukan untuk tanda tangan.
                        </div>
                    )}

                    {round.partial_reasons.length > 0 && (
                        <PartialReasonBox reasons={round.partial_reasons} />
                    )}

                    <div className="mt-6 rounded-lg border border-slate-200 bg-white p-5">
                        <dl className="grid gap-4 md:grid-cols-2">
                            <Info
                                label="Nomor PO"
                                value={round.inspection.po_number ?? '-'}
                            />
                            <Info
                                label="Supplier"
                                value={round.inspection.supplier_name ?? '-'}
                            />
                            <Info
                                label="Spesifikasi sesuai PO"
                                value={
                                    round.specification_matches_po
                                        ? 'Ya'
                                        : 'Tidak'
                                }
                            />
                            <Info
                                label="Dikirim"
                                value={round.submitted_at ?? '-'}
                            />
                            <Info
                                label="Nomor Surat Jalan"
                                value={round.delivery_note_number ?? '-'}
                            />
                            {round.completion_result && (
                                <Info
                                    label="Hasil"
                                    value={
                                        round.completion_result === 'COMPLETE'
                                            ? 'Lengkap'
                                            : 'Partial'
                                    }
                                />
                            )}
                        </dl>
                        {round.notes && (
                            <p className="mt-4 text-sm text-slate-700">
                                <strong>Notes Warehouse:</strong> {round.notes}
                            </p>
                        )}
                        {round.head_warehouse_notes && (
                            <p className="mt-2 text-sm text-slate-700">
                                <strong>Notes Head Warehouse:</strong>{' '}
                                {round.head_warehouse_notes}
                            </p>
                        )}
                        {round.user_notes && (
                            <p className="mt-2 text-sm text-slate-700">
                                <strong>Notes User:</strong> {round.user_notes}
                            </p>
                        )}
                    </div>

                    <section className="mt-6 rounded-lg border border-slate-200 bg-white p-5">
                        <h2 className="text-base font-semibold">Foto Round</h2>
                        {round.attachments.length === 0 ? (
                            <p className="mt-3 text-sm text-slate-500">
                                Tidak ada foto round.
                            </p>
                        ) : (
                            <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                {round.attachments.map((attachment) => (
                                    <PhotoPreview
                                        key={attachment.id}
                                        label={attachment.type_label}
                                        url={attachment.url}
                                    />
                                ))}
                            </div>
                        )}
                    </section>

                    <div className="mt-6 overflow-x-auto rounded-lg border border-slate-200 bg-white">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-slate-100 text-slate-700">
                                <tr>
                                    <th className="px-4 py-3">Kode</th>
                                    <th className="px-4 py-3">Item</th>
                                    <th className="px-4 py-3">No. Pesanan</th>
                                    <th className="px-4 py-3">Qty Pesanan</th>
                                    {showRemainingQty && (
                                        <th className="px-4 py-3">Qty Sisa</th>
                                    )}
                                    <th className="px-4 py-3">Received</th>
                                    <th className="px-4 py-3">Rusak</th>
                                    <th className="px-4 py-3">Kualitas</th>
                                    <th className="px-4 py-3">Foto Item</th>
                                </tr>
                            </thead>
                            <tbody>
                                {round.items.map((item) => (
                                    <tr
                                        key={item.id}
                                        className="border-t border-slate-200"
                                    >
                                        <td className="px-4 py-3">
                                            {item.item_code ?? '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {item.item_name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {item.po_number ?? '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {item.qty} {item.unit ?? ''}
                                        </td>
                                        {showRemainingQty && (
                                            <td className="px-4 py-3">
                                                {item.qty_remaining}{' '}
                                                {item.unit ?? ''}
                                            </td>
                                        )}
                                        <td className="px-4 py-3">
                                            {item.received_qty ?? '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {item.damaged_qty}
                                        </td>
                                        <td className="px-4 py-3">
                                            {item.quality_status}
                                        </td>
                                        <td className="px-4 py-3">
                                            {item.photos.length === 0 ? (
                                                '-'
                                            ) : (
                                                <div className="grid min-w-56 gap-3">
                                                    {item.photos.map(
                                                        (photo) => (
                                                            <PhotoPreview
                                                                key={photo.id}
                                                                label={
                                                                    photo.type_label
                                                                }
                                                                url={photo.url}
                                                                compact
                                                            />
                                                        ),
                                                    )}
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {round.can_sign ? (
                        <form onSubmit={reject} className="mt-5 space-y-3">
                            <label
                                className="block text-sm font-medium text-slate-700"
                                htmlFor="notes"
                            >
                                Notes
                            </label>
                            <textarea
                                id="notes"
                                value={data.notes}
                                onChange={(event) =>
                                    setData('notes', event.target.value)
                                }
                                maxLength={1000}
                                required
                                className="w-full rounded-md border border-slate-300 px-3 py-2"
                            />
                            {errors.notes && (
                                <p className="text-sm text-red-600">
                                    {errors.notes}
                                </p>
                            )}
                            <div className="flex flex-wrap gap-3">
                                <button
                                    type="button"
                                    onClick={sign}
                                    disabled={processing || !online}
                                    className="inline-flex items-center gap-2 rounded-md bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800 disabled:opacity-70"
                                >
                                    <CheckCircle2 className="h-4 w-4" />
                                    Tanda tangani
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing || !online}
                                    className="rounded-md border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 disabled:opacity-70"
                                >
                                    Reject ke Warehouse
                                </button>
                            </div>
                        </form>
                    ) : (
                        <p className="mt-5 text-sm text-slate-500">
                            Round ini sudah tidak menunggu tindakan Head
                            Warehouse.
                        </p>
                    )}
                </section>
            </main>
        </>
    );
}

function PhotoPreview({
    label,
    url,
    compact = false,
}: {
    label: string;
    url: string;
    compact?: boolean;
}) {
    return (
        <a
            href={url}
            target="_blank"
            rel="noreferrer"
            className="block rounded-md border border-slate-200 bg-slate-50 p-2 hover:border-teal-300"
        >
            <span className="mb-2 block text-xs font-semibold text-slate-700">
                {label}
            </span>
            <img
                src={url}
                alt={label}
                className={`w-full rounded border border-slate-200 bg-white object-cover ${compact ? 'h-24' : 'h-48'}`}
                loading="lazy"
            />
        </a>
    );
}

function Info({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-sm font-medium text-slate-500">{label}</dt>
            <dd className="mt-1 text-base font-semibold text-slate-950">
                {value}
            </dd>
        </div>
    );
}

function StatusChip({
    status,
    completionResult,
}: {
    status: string;
    completionResult: string | null;
}) {
    const isPartial = completionResult === 'PARTIAL';

    return (
        <span
            className={`mt-2 inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold ${isPartial ? 'border-red-200 bg-red-50 text-red-700' : 'border-slate-200 bg-slate-50 text-slate-700'}`}
        >
            {isPartial ? 'Parsial' : status}
        </span>
    );
}

function PartialReasonBox({ reasons }: { reasons: string[] }) {
    return (
        <section className="mt-5 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p className="font-semibold">Round ini parsial.</p>
            <ul className="mt-2 list-disc space-y-1 pl-5">
                {reasons.map((reason) => (
                    <li key={reason}>{reason}</li>
                ))}
            </ul>
        </section>
    );
}
