import { Head } from '@inertiajs/react';

export default function Offline() {
    return (
        <>
            <Head title="Offline" />
            <main className="flex min-h-screen items-center bg-slate-50 px-4 py-8 text-slate-950">
                <section className="mx-auto w-full max-w-md rounded-lg border border-slate-200 bg-white p-6">
                    <p className="text-sm font-medium text-teal-700">Offline</p>
                    <h1 className="mt-2 text-2xl font-semibold">
                        Koneksi internet diperlukan
                    </h1>
                    <p className="mt-3 text-sm leading-6 text-slate-600">
                        Pencarian Penerimaan Barang, upload foto, tanda tangan,
                        pembuatan PDF, dan download PDF harus dilakukan saat
                        online.
                    </p>
                </section>
            </main>
        </>
    );
}
