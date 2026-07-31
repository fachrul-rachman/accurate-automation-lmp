import { Head, useForm } from '@inertiajs/react';
import { Check, ChevronDown, Edit3, Eye, EyeOff, Plus, Trash2, X } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { AdminShell } from '@/components/admin-shell';

type LocationOption = { id: number; name: string };
type RoleOption = { value: string; label: string };
type UserRow = {
    id: number;
    name: string;
    email: string;
    role: string;
    is_active: boolean;
    locations: LocationOption[];
};

type Props = {
    users: UserRow[];
    locations: LocationOption[];
    roles: RoleOption[];
};

const roleLabels: Record<string, string> = {
    WAREHOUSE: 'Warehouse',
    USER: 'User',
    HEAD_WAREHOUSE: 'Head Warehouse',
    ADMIN: 'Admin',
};

export default function AdminUsersIndex({ users, locations, roles }: Props) {
    const [editingUser, setEditingUser] = useState<UserRow | null>(null);
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, put, delete: destroy, processing, errors, reset, clearErrors } = useForm({
        name: '',
        email: '',
        password: '',
        role: 'WAREHOUSE',
        location_ids: [] as number[],
        is_active: true,
    });

    const isEditing = editingUser !== null;

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (editingUser) {
            put(`/admin/users/${editingUser.id}`, {
                onSuccess: () => cancelEdit(),
            });

            return;
        }

        post('/admin/users', {
            onSuccess: () => {
                reset();
                setShowPassword(false);
            },
        });
    }

    function startEdit(user: UserRow) {
        clearErrors();
        setEditingUser(user);
        setShowPassword(false);
        setData({
            name: user.name,
            email: user.email,
            password: '',
            role: user.role,
            location_ids: user.locations.map((location) => location.id),
            is_active: user.is_active,
        });
    }

    function cancelEdit() {
        clearErrors();
        setEditingUser(null);
        setShowPassword(false);
        reset();
    }

    function deleteUser(user: UserRow) {
        if (!window.confirm(`Delete ${user.name}?`)) {
            return;
        }

        destroy(`/admin/users/${user.id}`);
    }

    return (
        <>
            <Head title="Users" />
            <AdminShell current="users" title="Users">
                <form onSubmit={submit} className="mt-5 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-3">
                    <TextInput label="Name" value={data.name} onChange={(value) => setData('name', value)} error={errors.name} />
                    <TextInput label="Email" type="email" value={data.email} onChange={(value) => setData('email', value)} error={errors.email} />
                    <label className="block text-sm">
                        <span className="font-medium text-slate-700">{isEditing ? 'New Password' : 'Password'}</span>
                        <div className="mt-1 flex rounded-md border border-slate-300 bg-white">
                            <input
                                type={showPassword ? 'text' : 'password'}
                                value={data.password}
                                onChange={(event) => setData('password', event.target.value)}
                                className="min-w-0 flex-1 rounded-l-md px-3 py-2 outline-none"
                                placeholder={isEditing ? 'Leave blank to keep current password' : ''}
                            />
                            <button
                                type="button"
                                onClick={() => setShowPassword((show) => !show)}
                                className="border-l border-slate-300 px-3 text-slate-600 hover:bg-slate-50"
                                aria-label={showPassword ? 'Hide password' : 'Show password'}
                            >
                                {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                            </button>
                        </div>
                        {errors.password && <span className="mt-1 block text-red-600">{errors.password}</span>}
                    </label>
                    <label className="block text-sm">
                        <span className="font-medium text-slate-700">Role</span>
                        <select value={data.role} onChange={(event) => setData('role', event.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                            {roles.map((role) => (
                                <option key={role.value} value={role.value}>{role.label}</option>
                            ))}
                        </select>
                    </label>
                    <LocationMultiSelect locations={locations} selectedIds={data.location_ids} onChange={(ids) => setData('location_ids', ids)} />
                    <label className="flex items-center gap-2 self-end text-sm text-slate-700">
                        <input type="checkbox" checked={data.is_active} onChange={(event) => setData('is_active', event.target.checked)} />
                        Active
                    </label>
                    <div className="flex flex-wrap gap-2 md:col-span-3">
                        <button type="submit" disabled={processing} className="inline-flex items-center gap-2 rounded-md bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800 disabled:opacity-70">
                            {isEditing ? <Edit3 className="h-4 w-4" /> : <Plus className="h-4 w-4" />}
                            {isEditing ? 'Update User' : 'Create User'}
                        </button>
                        {isEditing && (
                            <button type="button" onClick={cancelEdit} className="inline-flex items-center gap-2 rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                <X className="h-4 w-4" />
                                Cancel
                            </button>
                        )}
                    </div>
                </form>

                <div className="mt-6 overflow-x-auto rounded-lg border border-slate-200 bg-white">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-slate-100 text-slate-700">
                            <tr>
                                <th className="px-4 py-3">Name</th>
                                <th className="px-4 py-3">Email</th>
                                <th className="px-4 py-3">Role</th>
                                <th className="px-4 py-3">Location</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((user) => (
                                <tr key={user.id} className="border-t border-slate-200">
                                    <td className="px-4 py-3">{user.name}</td>
                                    <td className="px-4 py-3">{user.email}</td>
                                    <td className="px-4 py-3">{roleLabels[user.role] ?? user.role}</td>
                                    <td className="px-4 py-3">{user.locations.length > 0 ? user.locations.map((location) => location.name).join(', ') : '-'}</td>
                                    <td className="px-4 py-3">{user.is_active ? 'Active' : 'Inactive'}</td>
                                    <td className="flex gap-2 px-4 py-3">
                                        <button type="button" onClick={() => startEdit(user)} className="inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                            <Edit3 className="h-4 w-4" />
                                            Edit
                                        </button>
                                        <button type="button" onClick={() => deleteUser(user)} className="inline-flex items-center gap-2 rounded-md border border-red-200 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50">
                                            <Trash2 className="h-4 w-4" />
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </AdminShell>
        </>
    );
}

function LocationMultiSelect({ locations, selectedIds, onChange }: { locations: LocationOption[]; selectedIds: number[]; onChange: (ids: number[]) => void }) {
    const [open, setOpen] = useState(false);
    const selectedNames = locations.filter((location) => selectedIds.includes(location.id)).map((location) => location.name);

    function toggleLocation(locationId: number) {
        if (selectedIds.includes(locationId)) {
            onChange(selectedIds.filter((id) => id !== locationId));

            return;
        }

        onChange([...selectedIds, locationId]);
    }

    return (
        <div className="relative text-sm">
            <span className="font-medium text-slate-700">Locations</span>
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                className="mt-1 flex min-h-10 w-full items-center justify-between gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-left"
            >
                <span className="line-clamp-1 text-slate-700">{selectedNames.length > 0 ? selectedNames.join(', ') : 'No location'}</span>
                <ChevronDown className="h-4 w-4 shrink-0 text-slate-500" />
            </button>
            {open && (
                <div className="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-md border border-slate-200 bg-white p-1 shadow-lg">
                    {locations.map((location) => {
                        const selected = selectedIds.includes(location.id);

                        return (
                            <button
                                key={location.id}
                                type="button"
                                onClick={() => toggleLocation(location.id)}
                                className="flex w-full items-center gap-2 rounded px-2 py-2 text-left text-sm hover:bg-slate-100"
                            >
                                <span className={`flex h-4 w-4 items-center justify-center rounded border ${selected ? 'border-teal-700 bg-teal-700 text-white' : 'border-slate-300'}`}>
                                    {selected && <Check className="h-3 w-3" />}
                                </span>
                                {location.name}
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

function TextInput({ label, value, onChange, error, type = 'text' }: { label: string; value: string; onChange: (value: string) => void; error?: string; type?: string }) {
    return (
        <label className="block text-sm">
            <span className="font-medium text-slate-700">{label}</span>
            <input type={type} value={value} onChange={(event) => onChange(event.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
            {error && <span className="mt-1 block text-red-600">{error}</span>}
        </label>
    );
}
