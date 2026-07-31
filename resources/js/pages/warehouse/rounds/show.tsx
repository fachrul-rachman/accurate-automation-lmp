import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Download, LogOut, Send } from 'lucide-react';
import type { FormEvent } from 'react';

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
};

type Round = {
    id: number;
    round_number: number;
    round_status: string;
    round_status_value: string;
    completion_result: string | null;
    specification_matches_po: boolean;
    notes: string | null;
    submitted_at: string | null;
    inspection: {
        pb_number: string;
        po_number: string | null;
        supplier_name: string | null;
        location_name: string | null;
    };
    items: RoundItem[];
    partial_reasons: string[];
    can_edit: boolean;
    edit_url: string | null;
    rejection_reason: string | null;
    pdf_download_url: string | null;
    continue_url: string | null;
};

type Props = {
    round: Round;
};

export default function WarehouseRoundShow({ round }: Props) {
    const { post, processing } = useForm();
    const showRemainingQty =
        round.completion_result === 'PARTIAL' ||
        round.partial_reasons.length > 0;

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        post(`/warehouse/rounds/${round.id}/submit`);
    }

    return (
        <>
            <Head title={`Round ${round.round_number} - ${round.inspection.pb_number}`} />
            <main className="min-h-screen bg-slate-50 px-4 py-6 text-slate-950">
                <section className="mx-auto w-full max-w-5xl">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p className="text-sm font-medium text-teal-700">Warehouse - {round.inspection.location_name ?? '-'}</p>
                            <h1 className="mt-2 text-2xl font-semibold">Penerimaan Barang {round.inspection.pb_number} / Round {round.round_number}</h1>
                            <StatusChip
                                status={round.round_status}
                                completionResult={round.completion_result}
                            />
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Link href="/dashboard/warehouse" className="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                <ArrowLeft className="h-4 w-4" />
                                Dashboard
                            </Link>
                            <Link href="/logout" method="post" as="button" className="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                <LogOut className="h-4 w-4" />
                                Logout
                            </Link>
                            {round.pdf_download_url ? (
                                <a href={round.pdf_download_url} className="inline-flex items-center gap-2 rounded-md bg-teal-700 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-800">
                                    <Download className="h-4 w-4" />
                                    Download PDF Final
                                </a>
                            ) : null}
                            {round.continue_url ? (
                                <Link href={round.continue_url} className="inline-flex items-center gap-2 rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">
                                    Lanjut Round
                                </Link>
                            ) : null}
                        </div>
                    </div>

                    <div className="mt-6 rounded-lg border border-slate-200 bg-white p-5">
                        <dl className="grid gap-4 md:grid-cols-2">
                            <Info label="Nomor PO" value={round.inspection.po_number ?? '-'} />
                            <Info label="Supplier" value={round.inspection.supplier_name ?? '-'} />
                            <Info label="Spesifikasi sesuai PO" value={round.specification_matches_po ? 'Ya' : 'Tidak'} />
                            <Info label="Submitted At" value={round.submitted_at ?? '-'} />
                        </dl>
                        {round.notes && <p className="mt-4 text-sm text-slate-700">{round.notes}</p>}
                    </div>

                    {round.partial_reasons.length > 0 && (
                        <PartialReasonBox reasons={round.partial_reasons} />
                    )}

                    {round.rejection_reason && (
                        <div className="mt-5 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                            <p className="font-semibold">Perlu perbaikan</p>
                            <p className="mt-1">{round.rejection_reason}</p>
                            {round.edit_url && (
                                <Link href={round.edit_url} className="mt-3 inline-flex rounded-md bg-red-700 px-4 py-2 font-semibold text-white">
                                    Perbaiki pemeriksaan
                                </Link>
                            )}
                        </div>
                    )}

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
                                    <th className="px-4 py-3">Foto</th>
                                </tr>
                            </thead>
                            <tbody>
                                {round.items.map((item) => (
                                    <tr key={item.id} className="border-t border-slate-200">
                                        <td className="px-4 py-3">{item.item_code ?? '-'}</td>
                                        <td className="px-4 py-3">{item.item_name}</td>
                                        <td className="px-4 py-3">{item.po_number ?? '-'}</td>
                                        <td className="px-4 py-3">{item.qty} {item.unit ?? ''}</td>
                                        {showRemainingQty && (
                                            <td className="px-4 py-3">
                                                {item.qty_remaining} {item.unit ?? ''}
                                            </td>
                                        )}
                                        <td className="px-4 py-3">{item.received_qty ?? '-'}</td>
                                        <td className="px-4 py-3">{item.damaged_qty}</td>
                                        <td className="px-4 py-3">{item.quality_status}</td>
                                        <td className="px-4 py-3">{item.photo_count}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {round.can_edit && (
                        <form onSubmit={submit} className="mt-5">
                            <button type="submit" disabled={processing} className="inline-flex items-center gap-2 rounded-md bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800 disabled:opacity-70">
                                <Send className="h-4 w-4" />
                                Submit ke User
                            </button>
                        </form>
                    )}
                </section>
            </main>
        </>
    );
}

function Info({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-sm font-medium text-slate-500">{label}</dt>
            <dd className="mt-1 text-base font-semibold text-slate-950">{value}</dd>
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
