import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download } from 'lucide-react';
import { AdminShell } from '@/components/admin-shell';

type Item = {
    id: number;
    item_code: string | null;
    item_name: string;
    po_number: string | null;
    qty: number;
    unit: string | null;
    received_qty: number | null;
    damaged_qty: number;
    quality_status: string;
    quantity_status: string;
    photo_count: number;
};

type Round = {
    id: number;
    round_number: number;
    round_status: string;
    completion_result: string | null;
    specification_matches_po: boolean;
    notes: string | null;
    created_by: string | null;
    selected_hod: string | null;
    hod_signed_by: string | null;
    hod_signed_at: string | null;
    head_warehouse_signed_by: string | null;
    head_warehouse_signed_at: string | null;
    items: Item[];
    attachment_count: number;
};

type AuditLog = {
    id: number;
    event: string;
    description: string;
    user_name: string | null;
    created_at: string | null;
};

type Props = {
    inspection: {
        id: number;
        pb_number: string;
        po_number: string | null;
        supplier_name: string | null;
        pb_date: string | null;
        location_name: string | null;
        created_by: string | null;
        pb_status: string;
        pdf_status: string;
        pdf_download_url: string | null;
    };
    rounds: Round[];
    audit_logs: AuditLog[];
};

export default function AdminMonitoringShow({
    inspection,
    rounds,
    audit_logs,
}: Props) {
    return (
        <>
            <Head title={`Monitoring ${inspection.pb_number}`} />
            <AdminShell current="monitoring" title={`PB ${inspection.pb_number}`}>
                <div className="mt-5 flex flex-wrap gap-2">
                    <Link
                        href="/admin/monitoring"
                        className="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Kembali
                    </Link>
                    {inspection.pdf_download_url ? (
                        <a
                            href={inspection.pdf_download_url}
                            className="inline-flex items-center gap-2 rounded-md bg-teal-700 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-800"
                        >
                            <Download className="h-4 w-4" />
                            Download PDF
                        </a>
                    ) : null}
                </div>

                <section className="mt-5 rounded-lg border border-slate-200 bg-white p-5">
                    <h2 className="text-base font-semibold">Detail PB</h2>
                    <dl className="mt-4 grid gap-4 md:grid-cols-2">
                        <Info label="Nomor PB" value={inspection.pb_number} />
                        <Info
                            label="Nomor PO"
                            value={inspection.po_number ?? '-'}
                        />
                        <Info
                            label="Supplier"
                            value={inspection.supplier_name ?? '-'}
                        />
                        <Info
                            label="Tanggal PB"
                            value={inspection.pb_date ?? '-'}
                        />
                        <Info
                            label="Lokasi"
                            value={inspection.location_name ?? '-'}
                        />
                        <Info
                            label="Dibuat oleh"
                            value={inspection.created_by ?? '-'}
                        />
                        <Info label="Status PB" value={inspection.pb_status} />
                        <Info label="Status PDF" value={inspection.pdf_status} />
                    </dl>
                </section>

                <section className="mt-5 space-y-4">
                    <h2 className="text-base font-semibold">Round History</h2>
                    {rounds.map((round) => (
                        <div
                            key={round.id}
                            className="rounded-lg border border-slate-200 bg-white p-5"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 className="font-semibold">
                                        Round {round.round_number}
                                    </h3>
                                    <p className="mt-1 text-sm text-slate-600">
                                        {round.round_status}
                                    </p>
                                </div>
                                <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                    {round.completion_result ?? 'Belum final'}
                                </span>
                            </div>

                            <dl className="mt-4 grid gap-4 md:grid-cols-3">
                                <Info
                                    label="Warehouse"
                                    value={round.created_by ?? '-'}
                                />
                                <Info
                                    label="User"
                                    value={round.hod_signed_by ?? round.selected_hod ?? '-'}
                                />
                                <Info
                                    label="Tanda tangan User"
                                    value={round.hod_signed_at ?? '-'}
                                />
                                <Info
                                    label="Kepala Gudang"
                                    value={round.head_warehouse_signed_by ?? '-'}
                                />
                                <Info
                                    label="Tanda tangan Kepala Gudang"
                                    value={round.head_warehouse_signed_at ?? '-'}
                                />
                                <Info
                                    label="Foto round"
                                    value={String(round.attachment_count)}
                                />
                            </dl>

                            {round.notes ? (
                                <p className="mt-4 text-sm text-slate-700">
                                    {round.notes}
                                </p>
                            ) : null}

                            <div className="mt-4 overflow-x-auto rounded-lg border border-slate-200">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-slate-100 text-slate-700">
                                        <tr>
                                            <th className="px-4 py-3">Kode</th>
                                            <th className="px-4 py-3">Item</th>
                                            <th className="px-4 py-3">
                                                No. Pesanan
                                            </th>
                                            <th className="px-4 py-3">Qty</th>
                                            <th className="px-4 py-3">
                                                Diterima
                                            </th>
                                            <th className="px-4 py-3">Rusak</th>
                                            <th className="px-4 py-3">
                                                Kualitas
                                            </th>
                                            <th className="px-4 py-3">Foto</th>
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
                                                    {item.photo_count}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ))}
                </section>

                <section className="mt-5 rounded-lg border border-slate-200 bg-white p-5">
                    <h2 className="text-base font-semibold">Audit Log</h2>
                    {audit_logs.length === 0 ? (
                        <p className="mt-3 text-sm text-slate-500">
                            Belum ada audit log untuk PB ini.
                        </p>
                    ) : (
                        <div className="mt-4 overflow-x-auto rounded-lg border border-slate-200">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-slate-100 text-slate-700">
                                    <tr>
                                        <th className="px-4 py-3">Waktu</th>
                                        <th className="px-4 py-3">Event</th>
                                        <th className="px-4 py-3">User</th>
                                        <th className="px-4 py-3">Deskripsi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {audit_logs.map((log) => (
                                        <tr
                                            key={log.id}
                                            className="border-t border-slate-200"
                                        >
                                            <td className="px-4 py-3 text-slate-500">
                                                {log.created_at ?? '-'}
                                            </td>
                                            <td className="px-4 py-3 font-semibold">
                                                {log.event}
                                            </td>
                                            <td className="px-4 py-3">
                                                {log.user_name ?? '-'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {log.description}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </AdminShell>
        </>
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
