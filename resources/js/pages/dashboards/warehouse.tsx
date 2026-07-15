import { Head, Link, useForm } from '@inertiajs/react';
import { Download, Eye, Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { WarehouseShell } from '@/components/warehouse-shell';
import { useOnlineStatus } from '@/lib/use-online-status';

type LocationOption = {
    id: number;
    name: string;
};

type Props = {
    locations: LocationOption[];
    active_rounds: ActiveRound[];
    active_round_count: number;
    selected_location_id?: number;
    pb_number?: string;
};

type ActiveRound = {
    id: number;
    pb_number: string;
    po_number: string | null;
    supplier_name: string | null;
    location_name: string | null;
    round_number: number;
    status: string;
    status_label: string;
    completion_result: string | null;
    updated_at: string | null;
    pdf_download_url: string | null;
    continue_url: string | null;
};

export default function WarehouseDashboard({
    locations,
    active_rounds,
    active_round_count,
    selected_location_id,
    pb_number,
}: Props) {
    const online = useOnlineStatus();
    const [activeView, setActiveView] = useState<'pb-search' | 'rounds'>(
        'pb-search',
    );
    const { data, setData, post, processing, errors } = useForm({
        location_id: selected_location_id ?? locations[0]?.id ?? '',
        pb_number: pb_number ?? '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        post('/warehouse/pb-search');
    }

    return (
        <>
            <Head title="Warehouse" />
            <WarehouseShell
                activeView={activeView}
                onViewChange={(view) =>
                    setActiveView(view === 'rounds' ? 'rounds' : 'pb-search')
                }
                title={activeView === 'rounds' ? 'Round PB' : 'Cari PB'}
                roundCount={active_round_count}
            >
                <div className="mt-6">
                    {activeView === 'pb-search' && (
                        <section
                            id="pb-search"
                            className="scroll-mt-6 rounded-lg border border-slate-200 bg-white p-5"
                        >
                            <div className="mb-4">
                                <h2 className="text-base font-semibold">
                                    Cari PB Accurate
                                </h2>
                                <p className="mt-1 text-sm text-slate-500">
                                    Pilih lokasi lalu masukkan nomor PB.
                                </p>
                            </div>
                            <form
                                onSubmit={submit}
                                className="grid gap-3 md:grid-cols-[220px_1fr_auto]"
                            >
                                {!online && (
                                    <p className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 md:col-span-3">
                                        Koneksi internet diperlukan untuk cari
                                        PB Accurate.
                                    </p>
                                )}
                                <label className="block text-sm">
                                    <span className="font-medium text-slate-700">
                                        Lokasi
                                    </span>
                                    <select
                                        value={data.location_id}
                                        onChange={(event) =>
                                            setData(
                                                'location_id',
                                                Number(event.target.value),
                                            )
                                        }
                                        className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                                    >
                                        {locations.map((location) => (
                                            <option
                                                key={location.id}
                                                value={location.id}
                                            >
                                                {location.name}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.location_id && (
                                        <span className="mt-1 block text-red-600">
                                            {errors.location_id}
                                        </span>
                                    )}
                                </label>
                                <label className="block text-sm">
                                    <span className="font-medium text-slate-700">
                                        Nomor PB
                                    </span>
                                    <input
                                        type="text"
                                        value={data.pb_number}
                                        onChange={(event) =>
                                            setData(
                                                'pb_number',
                                                event.target.value,
                                            )
                                        }
                                        className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                                        placeholder="Contoh: PB-001"
                                    />
                                    {errors.pb_number && (
                                        <span className="mt-1 block text-red-600">
                                            {errors.pb_number}
                                        </span>
                                    )}
                                </label>
                                <button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        locations.length === 0 ||
                                        !online
                                    }
                                    className="inline-flex h-10 items-center justify-center gap-2 self-end rounded-md bg-teal-700 px-4 text-sm font-semibold text-white hover:bg-teal-800 disabled:opacity-70"
                                >
                                    <Search className="h-4 w-4" />
                                    Cari PB
                                </button>
                            </form>
                            {locations.length === 0 && (
                                <p className="mt-3 text-sm text-red-600">
                                    User ini belum memiliki akses lokasi aktif.
                                </p>
                            )}
                        </section>
                    )}

                    {activeView === 'rounds' && (
                        <section
                            id="rounds"
                            className="scroll-mt-6 rounded-lg border border-slate-200 bg-white p-5"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 className="text-base font-semibold">
                                        Round PB
                                    </h2>
                                    <p className="mt-1 text-sm text-slate-500">
                                        Status PB yang sudah pernah disimpan.
                                    </p>
                                </div>
                                <span className="rounded-full bg-teal-50 px-3 py-1 text-sm font-semibold text-teal-700">
                                    {active_round_count} PB
                                </span>
                            </div>

                            {active_rounds.length === 0 ? (
                                <p className="mt-5 rounded-md border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">
                                    Belum ada PB yang disimpan.
                                </p>
                            ) : (
                                <div className="mt-5 overflow-x-auto rounded-lg border border-slate-200">
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-slate-100 text-slate-700">
                                            <tr>
                                                <th className="px-4 py-3">
                                                    Nomor PB
                                                </th>
                                                <th className="px-4 py-3">
                                                    Nomor PO
                                                </th>
                                                <th className="px-4 py-3">
                                                    Lokasi
                                                </th>
                                                <th className="px-4 py-3">
                                                    Supplier
                                                </th>
                                                <th className="px-4 py-3">
                                                    Round
                                                </th>
                                                <th className="px-4 py-3">
                                                    Step
                                                </th>
                                                <th className="px-4 py-3">
                                                    Update
                                                </th>
                                                <th className="px-4 py-3">
                                                    Aksi
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {active_rounds.map((round) => (
                                                <tr
                                                    key={round.id}
                                                    className="border-t border-slate-200"
                                                >
                                                    <td className="px-4 py-3 font-semibold text-slate-950">
                                                        {round.pb_number}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {round.po_number ?? '-'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {round.location_name ??
                                                            '-'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {round.supplier_name ??
                                                            '-'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {round.round_number}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <StatusChip
                                                            status={
                                                                round.status
                                                            }
                                                            label={
                                                                round.status_label
                                                            }
                                                            completionResult={
                                                                round.completion_result
                                                            }
                                                        />
                                                    </td>
                                                    <td className="px-4 py-3 text-slate-500">
                                                        {round.updated_at ??
                                                            '-'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="flex flex-wrap gap-2">
                                                            <Link
                                                                href={`/warehouse/rounds/${round.id}`}
                                                                className="inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                                            >
                                                                <Eye className="h-4 w-4" />
                                                                Detail
                                                            </Link>
                                                            {round.pdf_download_url ? (
                                                                <a
                                                                    href={
                                                                        round.pdf_download_url
                                                                    }
                                                                    className="inline-flex items-center gap-2 rounded-md bg-teal-700 px-3 py-1.5 text-sm font-semibold text-white hover:bg-teal-800"
                                                                >
                                                                    <Download className="h-4 w-4" />
                                                                    PDF
                                                                </a>
                                                            ) : null}
                                                            {round.continue_url ? (
                                                                <Link
                                                                    href={
                                                                        round.continue_url
                                                                    }
                                                                    className="inline-flex items-center gap-2 rounded-md bg-red-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-red-700"
                                                                >
                                                                    Lanjut Round
                                                                </Link>
                                                            ) : null}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </section>
                    )}
                </div>
            </WarehouseShell>
        </>
    );
}

function StatusChip({
    status,
    label,
    completionResult,
}: {
    status: string;
    label: string;
    completionResult: string | null;
}) {
    if (completionResult === 'PARTIAL') {
        return (
            <span className="inline-flex rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">
                Parsial
            </span>
        );
    }

    const className =
        {
            DRAFT: 'border-amber-200 bg-amber-50 text-amber-700',
            WAITING_HOD_SIGNATURE: 'border-sky-200 bg-sky-50 text-sky-700',
            WAITING_HEAD_WAREHOUSE_SIGNATURE:
                'border-violet-200 bg-violet-50 text-violet-700',
            SIGNED: 'border-emerald-200 bg-emerald-50 text-emerald-700',
        }[status] ?? 'border-slate-200 bg-slate-50 text-slate-700';

    return (
        <span
            className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold ${className}`}
        >
            {label}
        </span>
    );
}
