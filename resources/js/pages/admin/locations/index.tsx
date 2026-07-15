import { Head, useForm } from '@inertiajs/react';
import { Edit3, Plus, X } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { AdminShell } from '@/components/admin-shell';

type LocationRow = { id: number; name: string; accurate_host_url: string; is_active: boolean };

export default function AdminLocationsIndex({ locations }: { locations: LocationRow[] }) {
    const [editingLocation, setEditingLocation] = useState<LocationRow | null>(null);
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        name: '',
        accurate_host_url: '',
        is_active: true,
    });

    const isEditing = editingLocation !== null;

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (editingLocation) {
            put(`/admin/locations/${editingLocation.id}`, { onSuccess: () => cancelEdit() });

            return;
        }

        post('/admin/locations', { onSuccess: () => reset() });
    }

    function startEdit(location: LocationRow) {
        clearErrors();
        setEditingLocation(location);
        setData({
            name: location.name,
            accurate_host_url: location.accurate_host_url,
            is_active: location.is_active,
        });
    }

    function cancelEdit() {
        clearErrors();
        setEditingLocation(null);
        reset();
    }

    return (
        <>
            <Head title="Locations" />
            <AdminShell current="locations" title="Locations">
                <form onSubmit={submit} className="mt-5 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-2">
                    <label className="block text-sm">
                        <span className="font-medium text-slate-700">Name</span>
                        <input value={data.name} onChange={(event) => setData('name', event.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                        {errors.name && <span className="mt-1 block text-red-600">{errors.name}</span>}
                    </label>
                    <label className="block text-sm">
                        <span className="font-medium text-slate-700">Accurate URL</span>
                        <input value={data.accurate_host_url} onChange={(event) => setData('accurate_host_url', event.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="https://zeus.accurate.id" />
                        {errors.accurate_host_url && <span className="mt-1 block text-red-600">{errors.accurate_host_url}</span>}
                    </label>
                    <label className="flex items-center gap-2 self-end text-sm text-slate-700">
                        <input type="checkbox" checked={data.is_active} onChange={(event) => setData('is_active', event.target.checked)} />
                        Active
                    </label>
                    <div className="flex flex-wrap gap-2 md:col-span-2">
                        <button type="submit" disabled={processing} className="inline-flex items-center gap-2 rounded-md bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800 disabled:opacity-70">
                            {isEditing ? <Edit3 className="h-4 w-4" /> : <Plus className="h-4 w-4" />}
                            {isEditing ? 'Update Location' : 'Create Location'}
                        </button>
                        {isEditing && (
                            <button type="button" onClick={cancelEdit} className="inline-flex items-center gap-2 rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                <X className="h-4 w-4" />
                                Cancel
                            </button>
                        )}
                    </div>
                </form>
                <div className="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white">
                    {locations.map((location) => (
                        <div key={location.id} className="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 first:border-t-0">
                            <div>
                                <p className="font-medium">{location.name}</p>
                                <p className="text-sm text-slate-600">{location.accurate_host_url}</p>
                                <p className="text-sm text-slate-600">{location.is_active ? 'Active' : 'Inactive'}</p>
                            </div>
                            <button type="button" onClick={() => startEdit(location)} className="inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                <Edit3 className="h-4 w-4" />
                                Edit
                            </button>
                        </div>
                    ))}
                </div>
            </AdminShell>
        </>
    );
}
