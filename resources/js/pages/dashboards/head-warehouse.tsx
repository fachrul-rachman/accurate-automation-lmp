import { Head, Link } from '@inertiajs/react';
import {
    CheckCircle2,
    Clock,
    Eye,
    LogOut,
    Menu,
    PanelLeftClose,
    PanelLeftOpen,
    X,
} from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import { useState } from 'react';

type PendingRound = {
    id: number;
    pb_number: string;
    po_number: string | null;
    supplier_name: string | null;
    location_name: string | null;
    round_number: number;
    submitted_at: string | null;
};

type HistoryRound = PendingRound & {
    status: string;
    completion_result: string | null;
    updated_at: string | null;
};

type Props = {
    user_summary: {
        name: string;
        locations: string;
    };
    pending_rounds: PendingRound[];
    pending_round_count: number;
    history_rounds: HistoryRound[];
    history_round_count: number;
};

export default function HeadWarehouseDashboard({
    user_summary,
    pending_rounds,
    pending_round_count,
    history_rounds,
    history_round_count,
}: Props) {
    const [activeView, setActiveView] = useState<'pending' | 'history'>(
        'pending',
    );
    const title =
        activeView === 'history'
            ? 'Riwayat Diketahui'
            : 'Menunggu Tanda Tangan Head Warehouse';

    return (
        <>
            <Head title="Dashboard Head Warehouse" />
            <HeadWarehouseShell
                activeView={activeView}
                onViewChange={(view) =>
                    setActiveView(view === 'history' ? 'history' : 'pending')
                }
                pendingCount={pending_round_count}
                historyCount={history_round_count}
                title={title}
                userSummary={user_summary}
            >
                {activeView === 'pending' && (
                    <section className="mt-6 rounded-lg border border-slate-200 bg-white p-5">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 className="text-base font-semibold">
                                    Menunggu tanda tangan Head Warehouse
                                </h2>
                                <p className="mt-1 text-sm text-slate-500">
                                    Penerimaan Barang yang menunggu tindakan
                                    Anda.
                                </p>
                            </div>
                            <span className="rounded-full bg-sky-50 px-3 py-1 text-sm font-semibold text-sky-700">
                                {pending_round_count} Penerimaan Barang
                            </span>
                        </div>

                        {pending_rounds.length === 0 ? (
                            <p className="mt-5 rounded-md border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">
                                Belum ada Penerimaan Barang yang menunggu
                                tindakan.
                            </p>
                        ) : (
                            <RoundTable rounds={pending_rounds} />
                        )}
                    </section>
                )}

                {activeView === 'history' && (
                    <section className="mt-6 rounded-lg border border-slate-200 bg-white p-5">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 className="text-base font-semibold">
                                    Riwayat
                                </h2>
                                <p className="mt-1 text-sm text-slate-500">
                                    Riwayat Round Penerimaan Barang.
                                </p>
                            </div>
                            <span className="rounded-full bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-700">
                                {history_round_count} Penerimaan Barang
                            </span>
                        </div>

                        {history_rounds.length === 0 ? (
                            <p className="mt-5 rounded-md border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">
                                Belum ada riwayat tanda tangan.
                            </p>
                        ) : (
                            <RoundTable rounds={history_rounds} showStatus />
                        )}
                    </section>
                )}
            </HeadWarehouseShell>
        </>
    );
}

function RoundTable({
    rounds,
    showStatus = false,
}: {
    rounds: Array<PendingRound | HistoryRound>;
    showStatus?: boolean;
}) {
    return (
        <div className="mt-5 overflow-x-auto rounded-lg border border-slate-200">
            <table className="w-full text-left text-sm">
                <thead className="bg-slate-100 text-slate-700">
                    <tr>
                        <th className="px-4 py-3">Nomor Penerimaan Barang</th>
                        <th className="px-4 py-3">Nomor PO</th>
                        <th className="px-4 py-3">Lokasi</th>
                        <th className="px-4 py-3">Supplier</th>
                        <th className="px-4 py-3">Round</th>
                        {showStatus && <th className="px-4 py-3">Status</th>}
                        <th className="px-4 py-3">
                            {showStatus ? 'Update' : 'Ditandatangani User'}
                        </th>
                        <th className="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    {rounds.map((round) => (
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
                                {round.location_name ?? '-'}
                            </td>
                            <td className="px-4 py-3">
                                {round.supplier_name ?? '-'}
                            </td>
                            <td className="px-4 py-3">{round.round_number}</td>
                            {showStatus && (
                                <td className="px-4 py-3">
                                    <StatusChip round={round} />
                                </td>
                            )}
                            <td className="px-4 py-3 text-slate-500">
                                {showStatus && 'updated_at' in round
                                    ? (round.updated_at ?? '-')
                                    : (round.submitted_at ?? '-')}
                            </td>
                            <td className="px-4 py-3">
                                <Link
                                    href={`/head-warehouse/rounds/${round.id}`}
                                    className="inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    <Eye className="h-4 w-4" />
                                    Detail
                                </Link>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function StatusChip({ round }: { round: PendingRound | HistoryRound }) {
    const status = 'status' in round ? round.status : '-';
    const isPartial =
        'completion_result' in round && round.completion_result === 'PARTIAL';

    return (
        <span
            className={`rounded-full px-2.5 py-1 text-xs font-semibold ${isPartial ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700'}`}
        >
            {isPartial ? 'Parsial' : status}
        </span>
    );
}

type NavItem = {
    key: 'pending' | 'history';
    label: string;
    icon: ComponentType<{ className?: string }>;
    badge: number;
};

function HeadWarehouseShell({
    activeView,
    onViewChange,
    pendingCount,
    historyCount,
    title,
    userSummary,
    children,
}: {
    activeView: 'pending' | 'history';
    onViewChange: (view: 'pending' | 'history') => void;
    pendingCount: number;
    historyCount: number;
    title: string;
    userSummary: { name: string; locations: string };
    children: ReactNode;
}) {
    const [desktopOpen, setDesktopOpen] = useState(true);
    const [mobileOpen, setMobileOpen] = useState(false);
    const navItems: NavItem[] = [
        {
            key: 'pending',
            label: 'Menunggu Saya',
            icon: Clock,
            badge: pendingCount,
        },
        {
            key: 'history',
            label: 'Riwayat',
            icon: CheckCircle2,
            badge: historyCount,
        },
    ];

    return (
        <div className="min-h-screen bg-slate-50 text-slate-950">
            <button
                type="button"
                onClick={() => setMobileOpen(true)}
                className="fixed top-4 left-4 z-30 rounded-md border border-slate-200 bg-white p-2 text-slate-700 shadow-sm lg:hidden"
                aria-label="Buka navigasi"
            >
                <Menu className="h-5 w-5" />
            </button>

            {mobileOpen && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <button
                        type="button"
                        className="absolute inset-0 bg-slate-950/40"
                        aria-label="Tutup navigasi"
                        onClick={() => setMobileOpen(false)}
                    />
                    <aside className="relative h-full w-72 border-r border-slate-200 bg-white shadow-xl">
                        <HeadWarehouseSidebar
                            navItems={navItems}
                            activeView={activeView}
                            open
                            onNavigate={(view) => {
                                onViewChange(view);
                                setMobileOpen(false);
                            }}
                        />
                        <button
                            type="button"
                            onClick={() => setMobileOpen(false)}
                            className="absolute top-4 right-4 rounded-md p-2 text-slate-500 hover:bg-slate-100"
                            aria-label="Tutup navigasi"
                        >
                            <X className="h-5 w-5" />
                        </button>
                    </aside>
                </div>
            )}

            <aside
                className={`fixed inset-y-0 left-0 hidden border-r border-slate-200 bg-white transition-all lg:block ${desktopOpen ? 'w-64' : 'w-20'}`}
            >
                <HeadWarehouseSidebar
                    navItems={navItems}
                    activeView={activeView}
                    open={desktopOpen}
                    onNavigate={onViewChange}
                />
                <button
                    type="button"
                    onClick={() => setDesktopOpen((open) => !open)}
                    className="absolute right-3 bottom-4 rounded-md border border-slate-200 p-2 text-slate-600 hover:bg-slate-100"
                    aria-label={
                        desktopOpen ? 'Tutup navigasi' : 'Buka navigasi'
                    }
                >
                    {desktopOpen ? (
                        <PanelLeftClose className="h-5 w-5" />
                    ) : (
                        <PanelLeftOpen className="h-5 w-5" />
                    )}
                </button>
            </aside>

            <main
                className={`px-4 py-6 transition-all lg:px-8 ${desktopOpen ? 'lg:ml-64' : 'lg:ml-20'}`}
            >
                <section className="mx-auto w-full max-w-6xl pt-12 lg:pt-0">
                    <p className="text-sm font-medium text-teal-700">
                        Head Warehouse
                    </p>
                    <h1 className="mt-2 text-2xl font-semibold">{title}</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        {userSummary.name} - {userSummary.locations}
                    </p>
                    {children}
                </section>
            </main>
        </div>
    );
}

function HeadWarehouseSidebar({
    navItems,
    activeView,
    open,
    onNavigate,
}: {
    navItems: NavItem[];
    activeView: 'pending' | 'history';
    open: boolean;
    onNavigate: (view: 'pending' | 'history') => void;
}) {
    return (
        <div className="flex h-full flex-col">
            <div className="border-b border-slate-200 px-4 py-5">
                <p
                    className={`font-semibold text-slate-950 ${open ? 'text-base' : 'text-center text-xs'}`}
                >
                    {open ? 'Accurate Check' : 'AC'}
                </p>
                {open && (
                    <p className="mt-1 text-xs text-slate-500">
                        Head Warehouse Console
                    </p>
                )}
            </div>
            <nav className="flex-1 space-y-1 p-3">
                {open && (
                    <p className="px-3 pb-2 text-xs font-semibold text-slate-500 uppercase">
                        Round Penerimaan Barang
                    </p>
                )}
                {navItems.map((item) => {
                    const Icon = item.icon;
                    const active = activeView === item.key;

                    return (
                        <button
                            key={item.key}
                            type="button"
                            onClick={() => onNavigate(item.key)}
                            className={`flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm font-medium ${active ? 'bg-teal-700 text-white' : 'text-slate-700 hover:bg-slate-100'}`}
                            title={open ? undefined : item.label}
                        >
                            <Icon className="h-5 w-5 shrink-0" />
                            {open && (
                                <>
                                    <span className="min-w-0 flex-1">
                                        {item.label}
                                    </span>
                                    <span
                                        className={`rounded-full px-2 py-0.5 text-xs font-semibold ${active ? 'bg-white text-teal-700' : 'bg-teal-50 text-teal-700'}`}
                                    >
                                        {item.badge}
                                    </span>
                                </>
                            )}
                        </button>
                    );
                })}
            </nav>
            <div className="border-t border-slate-200 p-3">
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    className="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                    title={open ? undefined : 'Logout'}
                >
                    <LogOut className="h-5 w-5 shrink-0" />
                    {open && <span>Logout</span>}
                </Link>
            </div>
        </div>
    );
}
