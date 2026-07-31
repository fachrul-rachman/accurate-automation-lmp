import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ClipboardList, Download, LogOut } from 'lucide-react';

type Inspection = {
    id: number;
    pb_number: string;
    po_number: string | null;
    supplier_name: string | null;
    pb_date: string | null;
    pb_status: string;
    pdf_status: string;
    item_count: number;
    pdf_download_url: string | null;
};

type Props = {
    inspection: Inspection;
};

export default function WarehouseInspectionShow({ inspection }: Props) {
    return (
        <>
            <Head title={`Penerimaan Barang ${inspection.pb_number}`} />
            <main className="min-h-screen bg-slate-50 px-4 py-6 text-slate-950">
                <section className="mx-auto w-full max-w-5xl">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p className="text-sm font-medium text-teal-700">Warehouse</p>
                            <h1 className="mt-2 text-2xl font-semibold">Penerimaan Barang {inspection.pb_number}</h1>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Link
                                href="/dashboard/warehouse"
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

                    <div className="mt-6 rounded-lg border border-slate-200 bg-white p-5">
                        <div className="flex items-center gap-3 border-b border-slate-200 pb-4">
                            <span className="flex h-10 w-10 items-center justify-center rounded-md bg-teal-50 text-teal-700">
                                <ClipboardList className="h-5 w-5" />
                            </span>
                            <div>
                                <p className="font-semibold">Detail Penerimaan Barang</p>
                                <p className="text-sm text-slate-500">Data awal dari Accurate sudah disimpan.</p>
                            </div>
                        </div>

                        <dl className="mt-5 grid gap-4 md:grid-cols-2">
                            <Info label="Nomor Penerimaan Barang" value={inspection.pb_number} />
                            <Info label="Nomor PO" value={inspection.po_number ?? '-'} />
                            <Info label="Supplier" value={inspection.supplier_name ?? '-'} />
                            <Info label="Tanggal Penerimaan Barang" value={inspection.pb_date ?? '-'} />
                            <Info label="Status Penerimaan Barang" value={inspection.pb_status} />
                            <Info label="Status PDF" value={inspection.pdf_status} />
                            <Info label="Jumlah Item" value={String(inspection.item_count)} />
                        </dl>

                        {inspection.pdf_download_url ? (
                            <a
                                href={inspection.pdf_download_url}
                                className="mt-5 inline-flex items-center gap-2 rounded-md bg-teal-700 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-800"
                            >
                                <Download className="h-4 w-4" />
                                Download PDF Final
                            </a>
                        ) : null}
                    </div>
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
