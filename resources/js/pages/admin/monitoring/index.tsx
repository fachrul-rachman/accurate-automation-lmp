import { Head, Link, router } from '@inertiajs/react';
import { Download, Eye, Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { AdminShell } from '@/components/admin-shell';

type Inspection = {
    id: number;
    pb_number: string;
    po_number: string | null;
    supplier_name: string | null;
    location_name: string | null;
    pb_status: string;
    pb_status_label: string;
    pdf_status_label: string;
    round_count: number;
    updated_at: string | null;
    pdf_download_url: string | null;
};

type StatusOption = {
    value: string;
    label: string;
};

type Props = {
    inspections: Inspection[];
    filters: {
        status: string;
        q: string;
    };
    status_options: StatusOption[];
};

export default function AdminMonitoringIndex({
    inspections,
    filters,
    status_options,
}: Props) {
    const [status, setStatus] = useState(filters.status);
    const [query, setQuery] = useState(filters.q);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(
            '/admin/monitoring',
            { status, q: query },
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Monitoring PB" />
            <AdminShell current="monitoring" title="Monitoring PB">
                <form
                    onSubmit={submit}
                    className="mt-5 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-[220px_1fr_auto]"
                >
                    <label className="block text-sm">
                        <span className="font-medium text-slate-700">
                            Status PB
                        </span>
                        <select
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                            className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                        >
                            <option value="">Semua status</option>
                            {status_options.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="block text-sm">
                        <span className="font-medium text-slate-700">
                            Cari PB / PO / Supplier
                        </span>
                        <input
                            type="text"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                            placeholder="Contoh: PB-001"
                        />
                    </label>
                    <button
                        type="submit"
                        className="inline-flex h-10 items-center justify-center gap-2 self-end rounded-md bg-teal-700 px-4 text-sm font-semibold text-white hover:bg-teal-800"
                    >
                        <Search className="h-4 w-4" />
                        Filter
                    </button>
                </form>

                <div className="mt-5 overflow-x-auto rounded-lg border border-slate-200 bg-white">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-slate-100 text-slate-700">
                            <tr>
                                <th className="px-4 py-3">Nomor PB</th>
                                <th className="px-4 py-3">Nomor PO</th>
                                <th className="px-4 py-3">Lokasi</th>
                                <th className="px-4 py-3">Supplier</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">PDF</th>
                                <th className="px-4 py-3">Round</th>
                                <th className="px-4 py-3">Update</th>
                                <th className="px-4 py-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {inspections.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={9}
                                        className="px-4 py-8 text-center text-slate-500"
                                    >
                                        Tidak ada PB untuk filter ini.
                                    </td>
                                </tr>
                            ) : (
                                inspections.map((inspection) => (
                                    <tr
                                        key={inspection.id}
                                        className="border-t border-slate-200"
                                    >
                                        <td className="px-4 py-3 font-semibold">
                                            {inspection.pb_number}
                                        </td>
                                        <td className="px-4 py-3">
                                            {inspection.po_number ?? '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {inspection.location_name ?? '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {inspection.supplier_name ?? '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusChip
                                                status={inspection.pb_status}
                                                label={
                                                    inspection.pb_status_label
                                                }
                                            />
                                        </td>
                                        <td className="px-4 py-3">
                                            {inspection.pdf_status_label}
                                        </td>
                                        <td className="px-4 py-3">
                                            {inspection.round_count}
                                        </td>
                                        <td className="px-4 py-3 text-slate-500">
                                            {inspection.updated_at ?? '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-2">
                                                <Link
                                                    href={`/admin/monitoring/${inspection.id}`}
                                                    className="inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                                >
                                                    <Eye className="h-4 w-4" />
                                                    Detail
                                                </Link>
                                                {inspection.pdf_download_url ? (
                                                    <a
                                                        href={
                                                            inspection.pdf_download_url
                                                        }
                                                        className="inline-flex items-center gap-2 rounded-md bg-teal-700 px-3 py-1.5 text-sm font-semibold text-white hover:bg-teal-800"
                                                    >
                                                        <Download className="h-4 w-4" />
                                                        PDF
                                                    </a>
                                                ) : null}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </AdminShell>
        </>
    );
}

function StatusChip({ status, label }: { status: string; label: string }) {
    const className =
        {
            OPEN_PARTIAL: 'border-amber-200 bg-amber-50 text-amber-700',
            COMPLETE: 'border-sky-200 bg-sky-50 text-sky-700',
            PDF_GENERATED: 'border-emerald-200 bg-emerald-50 text-emerald-700',
        }[status] ?? 'border-slate-200 bg-slate-50 text-slate-700';

    return (
        <span
            className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold ${className}`}
        >
            {label}
        </span>
    );
}
