import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    LogOut,
    Save,
} from 'lucide-react';
import { useState } from 'react';
import { useOnlineStatus } from '@/lib/use-online-status';

type Location = {
    id: number;
    name: string;
};

type SearchItem = {
    accurate_item_row_id: string | null;
    item_no: string | null;
    item_name: string | null;
    po_number: string | null;
    quantity: number | null;
    qty_remaining: number | null;
    is_complete: boolean;
    unit: string | null;
};

type SearchResult = {
    accurate_receive_item_id: string | null;
    number: string;
    po_number: string | null;
    supplier_name: string | null;
    pb_date: string | null;
    items: SearchItem[];
};

type Props = {
    location: Location;
    result: SearchResult;
    users: Array<{ id: number; name: string }>;
    editing_round?: {
        id: number;
        selected_user_id: number;
        specification_matches_po: boolean;
        notes: string | null;
        delivery_note_number: string | null;
        rejection_reason: string | null;
        attachments: Array<{
            id: number;
            type: string;
            name: string;
            url: string;
        }>;
        items: Array<{
            accurate_detail_item_id: string | null;
            quality_status: string;
            quantity_status: string;
            received_qty: string | null;
            damaged_qty: string;
            photos: Array<{
                id: number;
                type: string;
                name: string;
                url: string;
            }>;
        }>;
    };
};

export default function WarehousePbSearchResult({
    location,
    result,
    users,
    editing_round,
}: Props) {
    const online = useOnlineStatus();
    const [step, setStep] = useState<'items' | 'round'>('items');
    const [showValidationErrors, setShowValidationErrors] = useState(false);
    const { data, setData, post, put, processing, errors, clearErrors } =
        useForm({
            location_id: location.id,
            pb_number: result.number,
            selected_user_id:
                editing_round?.selected_user_id ?? users[0]?.id ?? '',
            specification_matches_po:
                editing_round?.specification_matches_po ?? true,
            notes: editing_round?.notes ?? '',
            delivery_note_number: editing_round?.delivery_note_number ?? '',
            delivery_note_photo: null as File | null,
            receiver_photo: null as File | null,
            items: result.items.map((item) => {
                const existing = editing_round?.items.find(
                    (roundItem) =>
                        roundItem.accurate_detail_item_id ===
                        item.accurate_item_row_id,
                );

                return {
                    is_checked: existing !== undefined && !item.is_complete,
                    accurate_detail_item_id: item.accurate_item_row_id ?? '',
                    quality_status: existing?.quality_status ?? 'GOOD',
                    quantity_status: existing?.quantity_status ?? 'MATCH',
                    received_qty: existing?.received_qty ?? '',
                    damaged_qty: existing?.damaged_qty ?? '0',
                    item_photos: [] as File[],
                    damage_photos: [] as File[],
                };
            }),
            delete_attachment_ids: [] as number[],
            delete_photo_ids: [] as number[],
        });

    function submit() {
        setShowValidationErrors(true);

        if (editing_round) {
            put(`/warehouse/rounds/${editing_round.id}`, {
                forceFormData: true,
            });
        } else {
            post('/warehouse/rounds', { forceFormData: true });
        }
    }

    function toggleDeletedPhoto(id: number) {
        setData(
            'delete_photo_ids',
            data.delete_photo_ids.includes(id)
                ? data.delete_photo_ids.filter((photoId) => photoId !== id)
                : [...data.delete_photo_ids, id],
        );
    }

    function toggleDeletedAttachment(id: number) {
        setData(
            'delete_attachment_ids',
            data.delete_attachment_ids.includes(id)
                ? data.delete_attachment_ids.filter(
                      (attachmentId) => attachmentId !== id,
                  )
                : [...data.delete_attachment_ids, id],
        );
    }

    function existingPhotos(item: SearchItem, type: string) {
        return (
            editing_round?.items
                .find(
                    (roundItem) =>
                        roundItem.accurate_detail_item_id ===
                        item.accurate_item_row_id,
                )
                ?.photos.filter((photo) => photo.type === type) ?? []
        );
    }

    function goToRoundStep() {
        if (!itemsComplete) {
            return;
        }

        clearErrors();
        setShowValidationErrors(false);
        setStep('round');
    }

    const errorMessages = showValidationErrors
        ? Object.values(errors).filter(Boolean)
        : [];
    const formErrors = errors as Record<string, string | undefined>;
    const checkedItems = data.items.filter((item) => item.is_checked);
    const showRemainingQty = result.items.some(
        (item) => item.qty_remaining !== null,
    );
    const itemsComplete =
        data.items.every((item, index) => {
            if (!item.is_checked) {
                return true;
            }

            const existing = editing_round?.items.find(
                (roundItem) =>
                    roundItem.accurate_detail_item_id ===
                    result.items[index].accurate_item_row_id,
            );
            const retainedPhotos =
                existing?.photos.filter(
                    (photo) => !data.delete_photo_ids.includes(photo.id),
                ) ?? [];

            const receivedOk =
                item.quantity_status === 'MATCH' || item.received_qty !== '';
            const damageOk =
                item.quality_status !== 'DAMAGED' ||
                (Number(item.damaged_qty) > 0 &&
                    (item.damage_photos.length > 0 ||
                        retainedPhotos.some(
                            (photo) => photo.type === 'DAMAGE',
                        )));

            return (
                item.accurate_detail_item_id !== '' &&
                (item.item_photos.length > 0 ||
                    retainedPhotos.some((photo) => photo.type === 'ITEM')) &&
                receivedOk &&
                damageOk
            );
        }) && checkedItems.length > 0;

    return (
        <>
            <Head title={`Penerimaan Barang ${result.number}`} />
            <main className="min-h-screen bg-slate-50 px-4 py-6 text-slate-950">
                <section className="mx-auto w-full max-w-5xl">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p className="text-sm font-medium text-teal-700">
                                Warehouse - {location.name}
                            </p>
                            <h1 className="mt-2 text-2xl font-semibold">
                                Penerimaan Barang {result.number}
                            </h1>
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
                        {editing_round?.rejection_reason && (
                            <div className="mb-5 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                                <p className="font-semibold">Alasan reject</p>
                                <p className="mt-1">
                                    {editing_round.rejection_reason}
                                </p>
                                <p className="mt-2">
                                    Unggah ulang seluruh foto setelah
                                    memperbaiki data.
                                </p>
                            </div>
                        )}
                        <div className="flex items-center gap-3 border-b border-slate-200 pb-4">
                            <span className="flex h-10 w-10 items-center justify-center rounded-md bg-teal-50 text-teal-700">
                                <ClipboardList className="h-5 w-5" />
                            </span>
                            <div>
                                <p className="font-semibold">
                                    Preview Accurate
                                </p>
                                <p className="text-sm text-slate-500">
                                    Data belum disimpan sebelum item
                                    di-checklist.
                                </p>
                            </div>
                        </div>

                        <dl className="mt-5 grid gap-4 md:grid-cols-2">
                            <Info
                                label="Nomor Penerimaan Barang"
                                value={result.number}
                            />
                            <Info
                                label="Nomor PO"
                                value={result.po_number ?? '-'}
                            />
                            <Info
                                label="Supplier"
                                value={result.supplier_name ?? '-'}
                            />
                            <Info
                                label="Tanggal Penerimaan Barang"
                                value={result.pb_date ?? '-'}
                            />
                        </dl>

                        {errorMessages.length > 0 && (
                            <div className="mt-5 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                                <p className="font-semibold">
                                    Ada data yang belum lengkap.
                                </p>
                                <ul className="mt-2 list-disc space-y-1 pl-5">
                                    {errorMessages
                                        .slice(0, 6)
                                        .map((message, index) => (
                                            <li key={`${message}-${index}`}>
                                                {message}
                                            </li>
                                        ))}
                                </ul>
                            </div>
                        )}

                        {!online && (
                            <div className="mt-5 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                                Koneksi internet diperlukan untuk upload foto
                                dan menyimpan round.
                            </div>
                        )}

                        <div className="mt-6 flex gap-2 border-b border-slate-200">
                            <button
                                type="button"
                                onClick={() => setStep('items')}
                                className={`border-b-2 px-3 py-2 text-sm font-semibold ${step === 'items' ? 'border-teal-700 text-teal-700' : 'border-transparent text-slate-500'}`}
                            >
                                Item Checklist
                            </button>
                            <button
                                type="button"
                                onClick={goToRoundStep}
                                disabled={!itemsComplete}
                                className={`border-b-2 px-3 py-2 text-sm font-semibold ${step === 'round' ? 'border-teal-700 text-teal-700' : 'border-transparent text-slate-500'}`}
                            >
                                Detail Round
                            </button>
                        </div>

                        {step === 'round' && (
                            <div className="mt-6 grid gap-4 md:grid-cols-2">
                                <label className="block text-sm">
                                    <span className="font-medium text-slate-700">
                                        User setelah Head Warehouse
                                    </span>
                                    <select
                                        value={data.selected_user_id}
                                        onChange={(event) =>
                                            setData(
                                                'selected_user_id',
                                                Number(event.target.value),
                                            )
                                        }
                                        className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                                    >
                                        {users.map((user) => (
                                            <option
                                                key={user.id}
                                                value={user.id}
                                            >
                                                {user.name}
                                            </option>
                                        ))}
                                    </select>
                                    {showValidationErrors &&
                                        errors.selected_user_id && (
                                            <span className="mt-1 block text-red-600">
                                                {errors.selected_user_id}
                                            </span>
                                        )}
                                </label>
                                <label className="flex items-center gap-2 self-end text-sm text-slate-700">
                                    <input
                                        type="checkbox"
                                        checked={data.specification_matches_po}
                                        onChange={(event) =>
                                            setData(
                                                'specification_matches_po',
                                                event.target.checked,
                                            )
                                        }
                                    />
                                    Spesifikasi sesuai PO
                                </label>
                                <label className="block text-sm md:col-span-2">
                                    <span className="font-medium text-slate-700">
                                        Nomor Surat Jalan
                                    </span>
                                    <input
                                        type="text"
                                        value={data.delivery_note_number}
                                        onChange={(event) =>
                                            setData(
                                                'delivery_note_number',
                                                event.target.value,
                                            )
                                        }
                                        maxLength={100}
                                        required
                                        className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                                    />
                                    {showValidationErrors &&
                                        errors.delivery_note_number && (
                                            <span className="mt-1 block text-red-600">
                                                {errors.delivery_note_number}
                                            </span>
                                        )}
                                </label>
                                <label className="block text-sm md:col-span-2">
                                    <span className="font-medium text-slate-700">
                                        Notes
                                    </span>
                                    <textarea
                                        value={data.notes}
                                        onChange={(event) =>
                                            setData('notes', event.target.value)
                                        }
                                        className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                                    />
                                </label>
                                <FileInput
                                    label="Foto Surat Jalan"
                                    onChange={(file) =>
                                        setData('delivery_note_photo', file)
                                    }
                                    error={
                                        showValidationErrors
                                            ? errors.delivery_note_photo
                                            : undefined
                                    }
                                    existingFiles={editing_round?.attachments.filter(
                                        (attachment) =>
                                            attachment.type === 'DELIVERY_NOTE',
                                    )}
                                    deletedIds={data.delete_attachment_ids}
                                    onToggleDeleted={toggleDeletedAttachment}
                                />
                                <FileInput
                                    label="Foto Penerima"
                                    onChange={(file) =>
                                        setData('receiver_photo', file)
                                    }
                                    error={
                                        showValidationErrors
                                            ? errors.receiver_photo
                                            : undefined
                                    }
                                    existingFiles={editing_round?.attachments.filter(
                                        (attachment) =>
                                            attachment.type ===
                                            'RECEIVER_PHOTO',
                                    )}
                                    deletedIds={data.delete_attachment_ids}
                                    onToggleDeleted={toggleDeletedAttachment}
                                />
                            </div>
                        )}

                        {step === 'items' && (
                            <>
                                {!itemsComplete && (
                                    <p className="mt-4 text-sm text-amber-700">
                                        Centang barang yang diterima, lalu
                                        lengkapi quantity dan foto untuk item
                                        tersebut.
                                    </p>
                                )}
                                <div className="mt-6 overflow-x-auto rounded-lg border border-slate-200">
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-slate-100 text-slate-700">
                                            <tr>
                                                <th className="px-4 py-3">
                                                    Diterima
                                                </th>
                                                <th className="px-4 py-3">
                                                    Kode Item
                                                </th>
                                                <th className="px-4 py-3">
                                                    Nama Item
                                                </th>
                                                <th className="px-4 py-3">
                                                    No. Pesanan
                                                </th>
                                                <th className="px-4 py-3">
                                                    Qty Pesanan
                                                </th>
                                                {showRemainingQty && (
                                                    <th className="px-4 py-3">
                                                        Qty Sisa
                                                    </th>
                                                )}
                                                <th className="px-4 py-3">
                                                    Unit
                                                </th>
                                                <th className="px-4 py-3">
                                                    Kualitas
                                                </th>
                                                <th className="px-4 py-3">
                                                    Quantity
                                                </th>
                                                <th className="px-4 py-3">
                                                    Received
                                                </th>
                                                <th className="px-4 py-3">
                                                    Rusak
                                                </th>
                                                <th className="px-4 py-3">
                                                    Foto
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {result.items.map((item, index) => (
                                                <tr
                                                    key={
                                                        item.accurate_item_row_id ??
                                                        index
                                                    }
                                                    className="border-t border-slate-200"
                                                >
                                                    <td className="px-4 py-3">
                                                        <label className="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                                                            <input
                                                                type="checkbox"
                                                                checked={
                                                                    data.items[
                                                                        index
                                                                    ].is_checked
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) => {
                                                                    const items =
                                                                        [
                                                                            ...data.items,
                                                                        ];
                                                                    items[
                                                                        index
                                                                    ] = {
                                                                        ...items[
                                                                            index
                                                                        ],
                                                                        is_checked:
                                                                            event
                                                                                .target
                                                                                .checked,
                                                                    };
                                                                    setData(
                                                                        'items',
                                                                        items,
                                                                    );
                                                                }}
                                                                disabled={
                                                                    item.is_complete
                                                                }
                                                            />
                                                            {item.is_complete
                                                                ? 'Selesai'
                                                                : 'Ya'}
                                                        </label>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {item.item_no ?? '-'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {item.item_name ?? '-'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {item.po_number ?? '-'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {item.quantity ?? '-'}
                                                    </td>
                                                    {showRemainingQty && (
                                                        <td className="px-4 py-3">
                                                            {item.qty_remaining ??
                                                                '-'}
                                                        </td>
                                                    )}
                                                    <td className="px-4 py-3">
                                                        {item.unit ?? '-'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <select
                                                            value={
                                                                data.items[
                                                                    index
                                                                ].quality_status
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) => {
                                                                const items = [
                                                                    ...data.items,
                                                                ];
                                                                items[index] = {
                                                                    ...items[
                                                                        index
                                                                    ],
                                                                    quality_status:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                };
                                                                setData(
                                                                    'items',
                                                                    items,
                                                                );
                                                            }}
                                                            className="min-w-28 rounded-md border border-slate-300 px-2 py-1"
                                                            disabled={
                                                                !data.items[
                                                                    index
                                                                ].is_checked
                                                            }
                                                        >
                                                            <option value="GOOD">
                                                                Baik
                                                            </option>
                                                            <option value="DAMAGED">
                                                                Rusak
                                                            </option>
                                                        </select>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <select
                                                            value={
                                                                data.items[
                                                                    index
                                                                ]
                                                                    .quantity_status
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) => {
                                                                const items = [
                                                                    ...data.items,
                                                                ];
                                                                items[index] = {
                                                                    ...items[
                                                                        index
                                                                    ],
                                                                    quantity_status:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                };
                                                                setData(
                                                                    'items',
                                                                    items,
                                                                );
                                                            }}
                                                            className="min-w-32 rounded-md border border-slate-300 px-2 py-1"
                                                            disabled={
                                                                !data.items[
                                                                    index
                                                                ].is_checked
                                                            }
                                                        >
                                                            <option value="MATCH">
                                                                Sesuai
                                                            </option>
                                                            <option value="NOT_MATCH">
                                                                Tidak Sesuai
                                                            </option>
                                                            <option value="PARTIAL">
                                                                Sebagian
                                                            </option>
                                                        </select>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            max={
                                                                item.qty_remaining ??
                                                                item.quantity ??
                                                                undefined
                                                            }
                                                            step="0.01"
                                                            value={
                                                                data.items[
                                                                    index
                                                                ].received_qty
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) => {
                                                                const items = [
                                                                    ...data.items,
                                                                ];
                                                                items[index] = {
                                                                    ...items[
                                                                        index
                                                                    ],
                                                                    received_qty:
                                                                        clampQuantity(
                                                                            event
                                                                                .target
                                                                                .value,
                                                                            item.qty_remaining ??
                                                                                item.quantity,
                                                                        ),
                                                                };
                                                                setData(
                                                                    'items',
                                                                    items,
                                                                );
                                                            }}
                                                            className="w-24 rounded-md border border-slate-300 px-2 py-1"
                                                            disabled={
                                                                !data.items[
                                                                    index
                                                                ].is_checked ||
                                                                data.items[
                                                                    index
                                                                ]
                                                                    .quantity_status ===
                                                                    'MATCH'
                                                            }
                                                        />
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            value={
                                                                data.items[
                                                                    index
                                                                ].damaged_qty
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) => {
                                                                const items = [
                                                                    ...data.items,
                                                                ];
                                                                items[index] = {
                                                                    ...items[
                                                                        index
                                                                    ],
                                                                    damaged_qty:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                };
                                                                setData(
                                                                    'items',
                                                                    items,
                                                                );
                                                            }}
                                                            className="w-24 rounded-md border border-slate-300 px-2 py-1"
                                                            disabled={
                                                                !data.items[
                                                                    index
                                                                ].is_checked
                                                            }
                                                        />
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <label className="block min-w-48 text-xs font-medium text-slate-700">
                                                            Foto Item
                                                            <input
                                                                type="file"
                                                                accept="image/*"
                                                                multiple
                                                                onChange={(
                                                                    event,
                                                                ) => {
                                                                    const items =
                                                                        [
                                                                            ...data.items,
                                                                        ];
                                                                    items[
                                                                        index
                                                                    ] = {
                                                                        ...items[
                                                                            index
                                                                        ],
                                                                        item_photos:
                                                                            Array.from(
                                                                                event
                                                                                    .target
                                                                                    .files ??
                                                                                    [],
                                                                            ),
                                                                    };
                                                                    setData(
                                                                        'items',
                                                                        items,
                                                                    );
                                                                }}
                                                                className="mt-1 block w-48 text-xs"
                                                                disabled={
                                                                    !data.items[
                                                                        index
                                                                    ].is_checked
                                                                }
                                                            />
                                                        </label>
                                                        <ExistingFiles
                                                            files={existingPhotos(
                                                                item,
                                                                'ITEM',
                                                            )}
                                                            deletedIds={
                                                                data.delete_photo_ids
                                                            }
                                                            onToggleDeleted={
                                                                toggleDeletedPhoto
                                                            }
                                                        />
                                                        {showValidationErrors &&
                                                            (formErrors[
                                                                `items.${index}.item_photo`
                                                            ] ||
                                                                formErrors[
                                                                    `items.${index}.item_photos`
                                                                ] ||
                                                                formErrors[
                                                                    `items.${index}.item_photos.0`
                                                                ]) && (
                                                                <span className="mt-1 block text-xs text-red-600">
                                                                    {formErrors[
                                                                        `items.${index}.item_photo`
                                                                    ] ??
                                                                        formErrors[
                                                                            `items.${index}.item_photos`
                                                                        ] ??
                                                                        formErrors[
                                                                            `items.${index}.item_photos.0`
                                                                        ]}
                                                                </span>
                                                            )}
                                                        {data.items[index]
                                                            .is_checked &&
                                                            data.items[index]
                                                                .quality_status ===
                                                                'DAMAGED' && (
                                                                <label className="mt-3 block min-w-48 text-xs font-medium text-slate-700">
                                                                    Foto
                                                                    Kerusakan
                                                                    <input
                                                                        type="file"
                                                                        accept="image/*"
                                                                        multiple
                                                                        onChange={(
                                                                            event,
                                                                        ) => {
                                                                            const items =
                                                                                [
                                                                                    ...data.items,
                                                                                ];
                                                                            items[
                                                                                index
                                                                            ] =
                                                                                {
                                                                                    ...items[
                                                                                        index
                                                                                    ],
                                                                                    damage_photos:
                                                                                        Array.from(
                                                                                            event
                                                                                                .target
                                                                                                .files ??
                                                                                                [],
                                                                                        ),
                                                                                };
                                                                            setData(
                                                                                'items',
                                                                                items,
                                                                            );
                                                                        }}
                                                                        className="mt-1 block w-48 text-xs"
                                                                    />
                                                                </label>
                                                            )}
                                                        <ExistingFiles
                                                            files={existingPhotos(
                                                                item,
                                                                'DAMAGE',
                                                            )}
                                                            deletedIds={
                                                                data.delete_photo_ids
                                                            }
                                                            onToggleDeleted={
                                                                toggleDeletedPhoto
                                                            }
                                                        />
                                                        {showValidationErrors &&
                                                            (formErrors[
                                                                `items.${index}.damage_photo`
                                                            ] ||
                                                                formErrors[
                                                                    `items.${index}.damage_photos`
                                                                ] ||
                                                                formErrors[
                                                                    `items.${index}.damage_photos.0`
                                                                ]) && (
                                                                <span className="mt-1 block text-xs text-red-600">
                                                                    {formErrors[
                                                                        `items.${index}.damage_photo`
                                                                    ] ??
                                                                        formErrors[
                                                                            `items.${index}.damage_photos`
                                                                        ] ??
                                                                        formErrors[
                                                                            `items.${index}.damage_photos.0`
                                                                        ]}
                                                                </span>
                                                            )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </>
                        )}

                        <div className="mt-5 flex flex-wrap gap-2">
                            {step === 'round' && (
                                <button
                                    type="button"
                                    onClick={() => setStep('items')}
                                    className="inline-flex items-center gap-2 rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    <ChevronLeft className="h-4 w-4" />
                                    Kembali ke Item
                                </button>
                            )}
                            {step === 'items' ? (
                                <button
                                    type="button"
                                    onClick={goToRoundStep}
                                    disabled={!itemsComplete}
                                    className="inline-flex items-center gap-2 rounded-md bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800 disabled:opacity-50"
                                >
                                    Lanjut Detail Round
                                    <ChevronRight className="h-4 w-4" />
                                </button>
                            ) : (
                                <button
                                    type="button"
                                    onClick={submit}
                                    disabled={
                                        processing ||
                                        users.length === 0 ||
                                        !online
                                    }
                                    className="inline-flex items-center gap-2 rounded-md bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800 disabled:opacity-70"
                                >
                                    <Save className="h-4 w-4" />
                                    {editing_round
                                        ? 'Simpan Perbaikan'
                                        : 'Simpan Draft Round'}
                                </button>
                            )}
                        </div>
                    </div>
                </section>
            </main>
        </>
    );
}

function clampQuantity(value: string, maximum: number | null): string {
    if (value === '') {
        return '';
    }

    return String(Math.min(Math.max(Number(value), 0), maximum ?? Infinity));
}

function FileInput({
    label,
    error,
    onChange,
    existingFiles = [],
    deletedIds = [],
    onToggleDeleted,
}: {
    label: string;
    error?: string;
    onChange: (file: File | null) => void;
    existingFiles?: ExistingFile[];
    deletedIds?: number[];
    onToggleDeleted?: (id: number) => void;
}) {
    return (
        <div className="text-sm">
            <label className="block">
                <span className="font-medium text-slate-700">{label}</span>
                <input
                    type="file"
                    accept="image/*"
                    onChange={(event) =>
                        onChange(event.target.files?.[0] ?? null)
                    }
                    className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                />
            </label>
            <ExistingFiles
                files={existingFiles}
                deletedIds={deletedIds}
                onToggleDeleted={onToggleDeleted}
            />
            {error && <span className="mt-1 block text-red-600">{error}</span>}
        </div>
    );
}

type ExistingFile = { id: number; name: string; url: string };

function ExistingFiles({
    files,
    deletedIds,
    onToggleDeleted,
}: {
    files: ExistingFile[];
    deletedIds: number[];
    onToggleDeleted?: (id: number) => void;
}) {
    if (files.length === 0) {
        return null;
    }

    return (
        <div className="mt-2 space-y-1">
            {files.map((file) => {
                const deleted = deletedIds.includes(file.id);

                return (
                    <div
                        key={file.id}
                        className="flex items-center gap-2 text-xs"
                    >
                        <a
                            href={file.url}
                            target="_blank"
                            rel="noreferrer"
                            className={
                                deleted
                                    ? 'line-through opacity-50'
                                    : 'text-teal-700 underline'
                            }
                        >
                            {file.name}
                        </a>
                        {onToggleDeleted && (
                            <button
                                type="button"
                                onClick={() => onToggleDeleted(file.id)}
                                className="font-semibold text-red-600"
                            >
                                {deleted ? 'Batal hapus' : 'Hapus'}
                            </button>
                        )}
                    </div>
                );
            })}
        </div>
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
