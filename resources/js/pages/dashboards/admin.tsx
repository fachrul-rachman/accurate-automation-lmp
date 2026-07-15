import { Head } from '@inertiajs/react';
import { AdminShell } from '@/components/admin-shell';

export default function AdminDashboard() {
    return (
        <>
            <Head title="Dashboard Admin" />
            <AdminShell current="dashboard" title="Dashboard">
                <div className="mt-5 grid gap-4 md:grid-cols-3">
                    <section className="rounded-lg border border-slate-200 bg-white p-5">
                        <h2 className="font-semibold">Users</h2>
                        <p className="mt-2 text-sm text-slate-600">Kelola akun, role, status aktif, dan lokasi user.</p>
                    </section>
                    <section className="rounded-lg border border-slate-200 bg-white p-5">
                        <h2 className="font-semibold">Locations</h2>
                        <p className="mt-2 text-sm text-slate-600">Kelola lokasi warehouse untuk workflow penerimaan barang.</p>
                    </section>
                    <section className="rounded-lg border border-slate-200 bg-white p-5">
                        <h2 className="font-semibold">Monitoring</h2>
                        <p className="mt-2 text-sm text-slate-600">Monitoring PB akan aktif setelah workflow inspection tersedia.</p>
                    </section>
                </div>
            </AdminShell>
        </>
    );
}
