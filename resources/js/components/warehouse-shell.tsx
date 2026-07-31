import { Link } from '@inertiajs/react';
import {
    ClipboardList,
    LogOut,
    Menu,
    PanelLeftClose,
    PanelLeftOpen,
    Search,
    X,
} from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import { useState } from 'react';

type NavItem = {
    key: string;
    label: string;
    icon: ComponentType<{ className?: string }>;
    badge?: number;
};

export function WarehouseShell({
    activeView,
    onViewChange,
    title,
    roundCount,
    userSummary,
    children,
}: {
    activeView: string;
    onViewChange: (view: string) => void;
    title: string;
    roundCount: number;
    userSummary: { name: string; locations: string };
    children: ReactNode;
}) {
    const [desktopOpen, setDesktopOpen] = useState(true);
    const [mobileOpen, setMobileOpen] = useState(false);
    const navItems: NavItem[] = [
        { key: 'search', label: 'Cari Penerimaan Barang', icon: Search },
        { key: 'draft', label: 'Draft', icon: ClipboardList },
        { key: 'revision', label: 'Perlu Perbaikan', icon: ClipboardList },
        {
            key: 'process',
            label: 'Dalam Proses',
            icon: ClipboardList,
            badge: roundCount,
        },
        { key: 'partial', label: 'Parsial', icon: ClipboardList },
        { key: 'complete', label: 'Selesai', icon: ClipboardList },
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
                        <SidebarContent
                            navItems={navItems}
                            current={activeView}
                            open
                            onNavigate={(key) => {
                                onViewChange(key);
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
                <SidebarContent
                    navItems={navItems}
                    current={activeView}
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
                        Warehouse
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

function SidebarContent({
    navItems,
    current,
    open,
    onNavigate,
}: {
    navItems: NavItem[];
    current: string;
    open: boolean;
    onNavigate?: (key: string) => void;
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
                        Warehouse Console
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
                    const active = current === item.key;

                    return (
                        <button
                            key={item.key}
                            type="button"
                            onClick={() => onNavigate?.(item.key)}
                            className={`flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm font-medium ${active ? 'bg-teal-700 text-white' : 'text-slate-700 hover:bg-slate-100'}`}
                            title={open ? undefined : item.label}
                        >
                            <Icon className="h-5 w-5 shrink-0" />
                            {open && (
                                <>
                                    <span className="min-w-0 flex-1">
                                        {item.label}
                                    </span>
                                    {typeof item.badge === 'number' && (
                                        <span
                                            className={`rounded-full px-2 py-0.5 text-xs font-semibold ${active ? 'bg-white text-teal-700' : 'bg-teal-50 text-teal-700'}`}
                                        >
                                            {item.badge}
                                        </span>
                                    )}
                                </>
                            )}
                            {!open && typeof item.badge === 'number' && (
                                <span className="ml-auto rounded-full bg-teal-50 px-1.5 py-0.5 text-[10px] font-semibold text-teal-700">
                                    {item.badge}
                                </span>
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
