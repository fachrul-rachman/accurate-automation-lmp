<?php

namespace App\Services;

use App\Enums\GoodsReceiptInspectionStatus;
use App\Enums\PdfStatus;
use App\Enums\QualityStatus;
use App\Enums\QuantityStatus;
use App\Enums\RoundAttachmentType;
use App\Enums\RoundItemPhotoType;
use App\Enums\RoundItemStatus;
use App\Enums\RoundStatus;
use App\Models\GoodsReceiptInspection;
use App\Models\GoodsReceiptRound;
use App\Models\GoodsReceiptRoundItem;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class GoodsReceiptRoundService
{
    public function __construct(
        private readonly AccurateReceiveItemService $accurateReceiveItems,
        private readonly R2StorageService $storage,
        private readonly PdfGenerationService $pdfGeneration,
        private readonly AuditLogService $auditLogs,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraftRound(Location $location, User $warehouse, array $data): GoodsReceiptRound
    {
        $detail = $this->accurateReceiveItems->findReceiveItemDetail($location, (string) $data['pb_number']);

        return DB::transaction(function () use ($location, $warehouse, $data, $detail): GoodsReceiptRound {
            $inspection = $this->inspectionFor($location, $warehouse, $detail);
            $this->ensureRoundCanBeCreated($inspection);

            $round = GoodsReceiptRound::query()->create([
                'inspection_id' => $inspection->id,
                'round_number' => ((int) $inspection->rounds()->max('round_number')) + 1,
                'created_by' => $warehouse->id,
                'selected_user_id' => $this->firstSelectedUserId($data),
                'round_status' => RoundStatus::Draft,
                'specification_matches_po' => (bool) $data['specification_matches_po'],
                'notes' => $data['notes'] ?? null,
                'delivery_note_number' => $data['delivery_note_number'],
            ]);

            $this->storeRoundContents($round, $detail, $data);

            $this->auditLogs->write(
                'ROUND_CREATED',
                $warehouse,
                $round,
                "Round {$round->round_number} dibuat untuk Penerimaan Barang {$inspection->pb_number}.",
                metadata: empty($data['notes']) ? null : ['notes' => $data['notes']],
            );

            return $round->load(['inspection', 'items.photos', 'attachments']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function reviseRound(GoodsReceiptRound $round, Location $location, User $warehouse, array $data): GoodsReceiptRound
    {
        if ($round->round_status !== RoundStatus::NeedsRevision) {
            throw new RuntimeException('Hanya Round yang ditolak yang dapat diperbaiki.');
        }

        $detail = $this->accurateReceiveItems->findReceiveItemDetail($location, (string) $data['pb_number']);
        $round->loadMissing(['inspection', 'attachments', 'items.photos']);

        if ($round->inspection->pb_number !== $detail['number']) {
            throw new RuntimeException('Nomor Penerimaan Barang tidak sesuai.');
        }

        $keysToDelete = [];

        $updated = DB::transaction(function () use ($round, $data, $detail, $warehouse, &$keysToDelete): GoodsReceiptRound {
            $round->update([
                'selected_user_id' => $this->firstSelectedUserId($data),
                'specification_matches_po' => (bool) $data['specification_matches_po'],
                'notes' => $data['notes'] ?? null,
                'delivery_note_number' => $data['delivery_note_number'],
            ]);

            $deletedPhotos = $round->items->flatMap(fn (GoodsReceiptRoundItem $item) => $item->photos)->whereIn('id', $data['delete_photo_ids'] ?? []);
            $deletedAttachments = $round->attachments->whereIn('id', $data['delete_attachment_ids'] ?? []);
            $keysToDelete = [...$deletedPhotos->pluck('r2_key')->all(), ...$deletedAttachments->pluck('r2_key')->all()];
            $deletedPhotos->each->delete();
            $deletedAttachments->each->delete();

            foreach ([
                'delivery_note_photo' => RoundAttachmentType::DeliveryNote,
                'receiver_photo' => RoundAttachmentType::ReceiverPhoto,
            ] as $field => $type) {
                if (($data[$field] ?? null) instanceof UploadedFile) {
                    $replaced = $round->attachments()->where('attachment_type', $type->value)->get();
                    $keysToDelete = [...$keysToDelete, ...$replaced->pluck('r2_key')->all()];
                    $replaced->each->delete();
                    $this->storeRoundAttachment($round, $data[$field], $type);
                }
            }

            $editableItemIds = $round->items
                ->filter(fn (GoodsReceiptRoundItem $item): bool => $item->head_status === RoundItemStatus::Rejected || $item->user_status === RoundItemStatus::Rejected)
                ->modelKeys();
            $keptItemIds = $round->items->whereNotIn('id', $editableItemIds)->modelKeys();

            foreach ($data['items'] as $index => $itemInput) {
                if (! is_array($itemInput) || ! (bool) ($itemInput['is_checked'] ?? true)) {
                    continue;
                }

                $accurateItem = $this->findAccurateItem($detail['items'], (string) $itemInput['accurate_detail_item_id']);
                $roundItem = $round->items()->where('accurate_detail_item_id', (string) $itemInput['accurate_detail_item_id'])->first();

                if ($roundItem !== null && ! in_array($roundItem->id, $editableItemIds, true)) {
                    continue;
                }

                $values = $this->roundItemValues($round, $accurateItem, $itemInput, $index + 1);

                if ($roundItem === null) {
                    $roundItem = GoodsReceiptRoundItem::query()->create(['round_id' => $round->id, ...$values]);
                } else {
                    $roundItem->update($values);
                }

                $keptItemIds[] = $roundItem->id;

                foreach ($this->uploadedFiles($itemInput, 'item_photo', 'item_photos') as $file) {
                    $this->storeItemPhoto($roundItem, $file, RoundItemPhotoType::Item);
                }

                if (($itemInput['quality_status'] ?? null) === QualityStatus::Damaged->value) {
                    foreach ($this->uploadedFiles($itemInput, 'damage_photo', 'damage_photos') as $file) {
                        $this->storeItemPhoto($roundItem, $file, RoundItemPhotoType::Damage);
                    }
                }
            }

            $removedItems = $round->items()->with('photos')->whereNotIn('id', $keptItemIds)->get();
            $keysToDelete = [...$keysToDelete, ...$removedItems->flatMap(fn (GoodsReceiptRoundItem $item) => $item->photos)->pluck('r2_key')->all()];
            $removedItems->each->delete();

            $this->auditLogs->write(
                'ROUND_REVISED',
                $warehouse,
                $round,
                "Round {$round->round_number} Penerimaan Barang {$round->inspection->pb_number} diperbaiki.",
                metadata: empty($data['notes']) ? null : ['notes' => $data['notes']],
            );

            return $round->refresh();
        });

        Storage::disk($this->storage->disk())->delete(array_unique($keysToDelete));

        return $updated;
    }

    public function submitToHeadWarehouse(GoodsReceiptRound $round): GoodsReceiptRound
    {
        if (! in_array($round->round_status, [RoundStatus::Draft, RoundStatus::NeedsRevision], true)) {
            throw new RuntimeException('Round sudah dikunci dan tidak bisa disubmit ulang.');
        }

        DB::transaction(function () use ($round): void {
            $round->items()
                ->whereIn('head_status', [RoundItemStatus::Pending->value, RoundItemStatus::Rejected->value])
                ->update([
                    'head_status' => RoundItemStatus::Pending,
                    'head_signed_by' => null,
                    'head_signed_at' => null,
                    'user_status' => RoundItemStatus::Pending,
                    'user_signed_by' => null,
                    'user_signed_at' => null,
                    'rejection_reason' => null,
                ]);

            $round->update([
                'round_status' => RoundStatus::WaitingHeadWarehouseSignature,
                'submitted_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'head_warehouse_notes' => null,
                'user_notes' => null,
            ]);
        });

        $round->loadMissing('inspection');
        $this->auditLogs->write(
            'ROUND_SUBMITTED_TO_HEAD_WAREHOUSE',
            $round->creator,
            $round,
            "Round {$round->round_number} Penerimaan Barang {$round->inspection->pb_number} dikirim ke Head Warehouse.",
        );

        return $round->refresh();
    }

    public function signByHeadWarehouse(GoodsReceiptRound $round, User $headWarehouse, ?string $notes = null): GoodsReceiptRound
    {
        if ($round->round_status !== RoundStatus::WaitingHeadWarehouseSignature
            || ! $round->items()->where('head_status', RoundItemStatus::Pending->value)->exists()) {
            throw new RuntimeException('Round ini belum siap ditandatangani Head Warehouse.');
        }

        return $this->reviewByHeadWarehouse($round, $headWarehouse, [], $notes);
    }

    /** @param array<int, int> $rejectedItemIds */
    public function rejectByHeadWarehouse(GoodsReceiptRound $round, User $headWarehouse, string $reason, array $rejectedItemIds = []): GoodsReceiptRound
    {
        if ($round->round_status !== RoundStatus::WaitingHeadWarehouseSignature
            || ! $round->items()->where('head_status', RoundItemStatus::Pending->value)->exists()) {
            throw new RuntimeException('Round ini belum siap diperiksa Head Warehouse.');
        }

        $rejectedItemIds = $rejectedItemIds === []
            ? $round->items()->where('head_status', RoundItemStatus::Pending->value)->pluck('id')->all()
            : $rejectedItemIds;

        return $this->reviewByHeadWarehouse($round, $headWarehouse, $rejectedItemIds, $reason);
    }

    public function rejectByUser(GoodsReceiptRound $round, User $user, string $reason): GoodsReceiptRound
    {
        $items = $round->items()
            ->where('selected_user_id', $user->id)
            ->where('head_status', RoundItemStatus::Approved->value)
            ->where('user_status', RoundItemStatus::Pending->value)
            ->get();

        if ($items->isEmpty()) {
            throw new RuntimeException('Round ini belum siap diperiksa User.');
        }

        return DB::transaction(function () use ($round, $user, $reason, $items): GoodsReceiptRound {
            GoodsReceiptRoundItem::query()->whereKey($items->modelKeys())->update([
                'head_status' => RoundItemStatus::Rejected,
                'head_signed_by' => null,
                'head_signed_at' => null,
                'user_status' => RoundItemStatus::Rejected,
                'user_signed_by' => null,
                'user_signed_at' => null,
                'rejection_reason' => $reason,
            ]);

            $round->update([
                'user_notes' => $reason,
                'rejection_reason' => $reason,
                'rejected_by' => $user->id,
                'rejected_at' => now(),
                'head_warehouse_signed_by' => $round->items()->where('head_status', RoundItemStatus::Approved->value)->exists() ? $round->head_warehouse_signed_by : null,
                'head_warehouse_signed_at' => $round->items()->where('head_status', RoundItemStatus::Approved->value)->exists() ? $round->head_warehouse_signed_at : null,
            ]);
            $this->syncRoundStatus($round);
            $round->loadMissing('inspection');
            $this->auditLogs->write(
                'USER_REJECTED', $user, $round,
                "Round {$round->round_number} Penerimaan Barang {$round->inspection->pb_number} dikembalikan ke Warehouse.",
                metadata: ['notes' => $reason, 'item_ids' => $items->modelKeys()],
            );

            return $round->refresh();
        });
    }

    public function signByUser(GoodsReceiptRound $round, User $user, ?string $notes = null): GoodsReceiptRound
    {
        $items = $round->items()
            ->where('selected_user_id', $user->id)
            ->where('head_status', RoundItemStatus::Approved->value)
            ->where('user_status', RoundItemStatus::Pending->value)
            ->get();

        if ($items->isEmpty()) {
            throw new RuntimeException('Round ini belum siap ditandatangani User.');
        }

        return DB::transaction(function () use ($round, $user, $notes, $items): GoodsReceiptRound {
            GoodsReceiptRoundItem::query()->whereKey($items->modelKeys())->update([
                'user_status' => RoundItemStatus::Signed,
                'user_signed_by' => $user->id,
                'user_signed_at' => now(),
                'rejection_reason' => null,
            ]);

            $round->update([
                'user_signed_by' => $user->id,
                'user_signed_at' => now(),
                'user_notes' => $notes,
            ]);

            $this->syncRoundStatus($round);

            $round->loadMissing('inspection.rounds.items');
            $this->auditLogs->write(
                'USER_SIGNED',
                $user,
                $round,
                "User menandatangani round {$round->round_number} Penerimaan Barang {$round->inspection->pb_number}.",
                metadata: ['notes' => $notes, 'item_ids' => $items->modelKeys()],
            );

            if ($round->round_status !== RoundStatus::Signed) {
                return $round->refresh();
            }

            $completionResult = $this->calculateCompletionResult($round);
            $round->update(['completion_result' => $completionResult]);

            if ($completionResult === 'COMPLETE') {
                $round->inspection->update([
                    'pb_status' => GoodsReceiptInspectionStatus::Complete,
                ]);

                $this->auditLogs->write(
                    'PB_MARKED_COMPLETE',
                    $user,
                    $round->inspection,
                    "Penerimaan Barang {$round->inspection->pb_number} ditandai lengkap.",
                );

                $this->pdfGeneration->generateFinalPdf($round->inspection, $user);

                $round->inspection->update([
                    'pb_status' => GoodsReceiptInspectionStatus::PdfGenerated,
                    'pdf_status' => PdfStatus::Generated,
                ]);
            } else {
                $round->inspection->update([
                    'pb_status' => GoodsReceiptInspectionStatus::OpenPartial,
                    'pdf_status' => PdfStatus::NotReady,
                ]);

                $this->auditLogs->write(
                    'PB_MARKED_PARTIAL',
                    $user,
                    $round->inspection,
                    "Penerimaan Barang {$round->inspection->pb_number} masih sebagian.",
                );
            }

            return $round->refresh();
        });
    }

    /** @param array<int, int> $rejectedItemIds */
    private function reviewByHeadWarehouse(GoodsReceiptRound $round, User $headWarehouse, array $rejectedItemIds, ?string $notes): GoodsReceiptRound
    {
        return DB::transaction(function () use ($round, $headWarehouse, $rejectedItemIds, $notes): GoodsReceiptRound {
            $pendingItems = $round->items()->where('head_status', RoundItemStatus::Pending->value)->get();
            $invalidIds = array_diff($rejectedItemIds, $pendingItems->modelKeys());

            if ($invalidIds !== []) {
                throw new RuntimeException('Item yang dipilih tidak valid atau sudah diproses.');
            }

            $approvedIds = $pendingItems->whereNotIn('id', $rejectedItemIds)->modelKeys();
            GoodsReceiptRoundItem::query()->whereKey($approvedIds)->update([
                'head_status' => RoundItemStatus::Approved,
                'head_signed_by' => $headWarehouse->id,
                'head_signed_at' => now(),
                'rejection_reason' => null,
            ]);
            GoodsReceiptRoundItem::query()->whereKey($rejectedItemIds)->update([
                'head_status' => RoundItemStatus::Rejected,
                'head_signed_by' => null,
                'head_signed_at' => null,
                'user_status' => RoundItemStatus::Pending,
                'rejection_reason' => $notes,
            ]);

            $round->update([
                'head_warehouse_signed_by' => $approvedIds === [] ? null : $headWarehouse->id,
                'head_warehouse_signed_at' => $approvedIds === [] ? null : now(),
                'head_warehouse_notes' => $notes,
                'rejected_by' => $rejectedItemIds === [] ? null : $headWarehouse->id,
                'rejected_at' => $rejectedItemIds === [] ? null : now(),
                'rejection_reason' => $rejectedItemIds === [] ? null : $notes,
            ]);
            $this->syncRoundStatus($round);
            $round->loadMissing('inspection');
            $this->auditLogs->write(
                $rejectedItemIds === [] ? 'HEAD_WAREHOUSE_SIGNED' : 'HEAD_WAREHOUSE_REJECTED',
                $headWarehouse, $round,
                $rejectedItemIds === []
                    ? "Head Warehouse menandatangani round {$round->round_number} Penerimaan Barang {$round->inspection->pb_number}."
                    : "Round {$round->round_number} Penerimaan Barang {$round->inspection->pb_number} dikembalikan sebagian ke Warehouse.",
                metadata: ['notes' => $notes, 'item_ids' => $rejectedItemIds === [] ? $approvedIds : $rejectedItemIds],
            );

            return $round->refresh();
        });
    }

    private function syncRoundStatus(GoodsReceiptRound $round): void
    {
        $items = $round->items()->get(['head_status', 'user_status']);
        $status = match (true) {
            $items->contains(fn ($item): bool => $item->head_status === RoundItemStatus::Rejected || $item->user_status === RoundItemStatus::Rejected) => RoundStatus::NeedsRevision,
            $items->contains(fn ($item): bool => $item->head_status === RoundItemStatus::Pending) => RoundStatus::WaitingHeadWarehouseSignature,
            $items->contains(fn ($item): bool => $item->user_status === RoundItemStatus::Pending) => RoundStatus::WaitingUserSignature,
            default => RoundStatus::Signed,
        };

        $round->update(['round_status' => $status]);
        $round->refresh();
    }

    private function calculateCompletionResult(GoodsReceiptRound $round): string
    {
        $round->loadMissing('inspection.rounds.items');
        $requiredByItem = $this->requiredQuantitiesFromAccurateSnapshot($round->inspection);
        $goodByItem = [];

        foreach ($round->inspection->rounds as $inspectionRound) {
            foreach ($inspectionRound->items as $item) {
                $key = $item->accurate_detail_item_id ?? $item->item_code ?? (string) $item->line_number;
                $requiredByItem[$key] = max($requiredByItem[$key] ?? 0, (float) $item->qty);

                if (! $this->itemIsSigned($item, $inspectionRound)) {
                    continue;
                }

                $goodByItem[$key] = ($goodByItem[$key] ?? 0)
                    + max(0, (float) $item->received_qty - (float) $item->damaged_qty);
            }
        }

        foreach ($requiredByItem as $key => $requiredQty) {
            if (($goodByItem[$key] ?? 0) < $requiredQty) {
                return 'PARTIAL';
            }
        }

        return 'COMPLETE';
    }

    /**
     * @return array<string, float>
     */
    private function requiredQuantitiesFromAccurateSnapshot(GoodsReceiptInspection $inspection): array
    {
        $items = $inspection->accurate_raw_json['detailItem'] ?? null;

        if (! is_array($items)) {
            return [];
        }

        $requiredByItem = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $key = isset($item['id'])
                ? (string) $item['id']
                : (string) ($item['item']['no'] ?? $index + 1);

            $quantity = $item['quantity'] ?? null;

            if (is_numeric($quantity)) {
                $requiredByItem[$key] = max($requiredByItem[$key] ?? 0, (float) $quantity);
            }
        }

        return $requiredByItem;
    }

    /**
     * @param  array{
     *     accurate_receive_item_id: string|null,
     *     number: string,
     *     po_number: string|null,
     *     supplier_name: string|null,
     *     pb_date: string|null,
     *     raw: array<string, mixed>
     * }  $detail
     */
    private function inspectionFor(Location $location, User $warehouse, array $detail): GoodsReceiptInspection
    {
        $existing = GoodsReceiptInspection::query()
            ->where('pb_number', $detail['number'])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $inspection = GoodsReceiptInspection::query()->create([
            'location_id' => $location->id,
            'created_by' => $warehouse->id,
            'accurate_receive_item_id' => $detail['accurate_receive_item_id'],
            'pb_number' => $detail['number'],
            'po_number' => $detail['po_number'],
            'supplier_name' => $detail['supplier_name'],
            'pb_date' => $detail['pb_date'],
            'accurate_raw_json' => $detail['raw'],
            'pb_status' => GoodsReceiptInspectionStatus::OpenPartial,
            'pdf_status' => PdfStatus::NotReady,
        ]);

        $this->auditLogs->write(
            'PB_IMPORTED',
            $warehouse,
            $inspection,
            "Penerimaan Barang {$inspection->pb_number} diimport dari Accurate.",
            newValues: [
                'pb_number' => $inspection->pb_number,
                'po_number' => $inspection->po_number,
                'supplier_name' => $inspection->supplier_name,
                'location_id' => $inspection->location_id,
            ],
        );

        return $inspection;
    }

    private function ensureRoundCanBeCreated(GoodsReceiptInspection $inspection): void
    {
        if ($inspection->pb_status === GoodsReceiptInspectionStatus::PdfGenerated) {
            throw new RuntimeException('Penerimaan Barang sudah terkunci karena PDF final sudah dibuat.');
        }

        $hasActiveRound = $inspection->rounds()
            ->where('round_status', '!=', RoundStatus::Signed->value)
            ->exists();

        if ($hasActiveRound) {
            throw new RuntimeException('Penerimaan Barang ini masih memiliki round aktif.');
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function findAccurateItem(array $items, string $accurateDetailItemId): array
    {
        foreach ($items as $item) {
            if (($item['accurate_item_row_id'] ?? null) === $accurateDetailItemId) {
                return $item;
            }
        }

        throw new RuntimeException('Item Accurate tidak ditemukan.');
    }

    /**
     * @param  array<string, mixed>  $detail
     * @param  array<string, mixed>  $data
     */
    private function storeRoundContents(GoodsReceiptRound $round, array $detail, array $data): void
    {
        $this->storeRoundAttachment($round, $data['delivery_note_photo'], RoundAttachmentType::DeliveryNote);
        $this->storeRoundAttachment($round, $data['receiver_photo'], RoundAttachmentType::ReceiverPhoto);

        foreach ($data['items'] as $index => $itemInput) {
            if (! is_array($itemInput) || ! (bool) ($itemInput['is_checked'] ?? true)) {
                continue;
            }

            $accurateItem = $this->findAccurateItem($detail['items'], (string) $itemInput['accurate_detail_item_id']);
            $roundItem = $this->createRoundItem($round, $accurateItem, $itemInput, $index + 1);

            foreach ($this->uploadedFiles($itemInput, 'item_photo', 'item_photos') as $file) {
                $this->storeItemPhoto($roundItem, $file, RoundItemPhotoType::Item);
            }

            if (($itemInput['quality_status'] ?? null) === QualityStatus::Damaged->value) {
                foreach ($this->uploadedFiles($itemInput, 'damage_photo', 'damage_photos') as $file) {
                    $this->storeItemPhoto($roundItem, $file, RoundItemPhotoType::Damage);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $accurateItem
     * @param  array<string, mixed>  $itemInput
     */
    private function createRoundItem(GoodsReceiptRound $round, array $accurateItem, array $itemInput, int $lineNumber): GoodsReceiptRoundItem
    {
        return GoodsReceiptRoundItem::query()->create([
            'round_id' => $round->id,
            ...$this->roundItemValues($round, $accurateItem, $itemInput, $lineNumber),
        ]);
    }

    /**
     * @param  array<string, mixed>  $accurateItem
     * @param  array<string, mixed>  $itemInput
     * @return array<string, mixed>
     */
    private function roundItemValues(GoodsReceiptRound $round, array $accurateItem, array $itemInput, int $lineNumber): array
    {
        $qty = (float) $accurateItem['quantity'];
        $remainingQty = $this->remainingQuantity($round, $accurateItem);
        $quantityStatus = QuantityStatus::from((string) $itemInput['quantity_status']);
        $receivedQty = $quantityStatus === QuantityStatus::Match ? $remainingQty : (float) $itemInput['received_qty'];
        $damagedQty = (float) ($itemInput['damaged_qty'] ?? 0);

        if ($remainingQty <= 0) {
            throw new RuntimeException('Item ini sudah lengkap dan tidak dapat diterima lagi.');
        }

        if ($receivedQty > $remainingQty) {
            throw new RuntimeException('Received quantity tidak boleh lebih besar dari quantity sisa.');
        }

        if ($damagedQty > $receivedQty) {
            throw new RuntimeException('Damaged quantity tidak boleh lebih besar dari received quantity.');
        }

        return [
            'line_number' => $lineNumber,
            'accurate_detail_item_id' => $accurateItem['accurate_item_row_id'],
            'accurate_item_id' => null,
            'item_code' => $accurateItem['item_no'],
            'item_name' => $accurateItem['item_name'],
            'qty' => $qty,
            'unit' => $accurateItem['unit'],
            'po_number' => $accurateItem['po_number'],
            'selected_user_id' => $itemInput['selected_user_id'] ?? $round->selected_user_id,
            'quality_status' => QualityStatus::from((string) $itemInput['quality_status']),
            'quantity_status' => $quantityStatus,
            'received_qty' => $receivedQty,
            'damaged_qty' => $damagedQty,
        ];
    }

    /** @param array<string, mixed> $accurateItem */
    private function remainingQuantity(GoodsReceiptRound $round, array $accurateItem): float
    {
        $goodQty = GoodsReceiptRoundItem::query()
            ->with('round')
            ->whereHas('round', fn ($query) => $query->where('inspection_id', $round->inspection_id))
            ->when(
                $accurateItem['accurate_item_row_id'] !== null,
                fn ($query) => $query->where('accurate_detail_item_id', (string) $accurateItem['accurate_item_row_id']),
                fn ($query) => $query->where('item_code', $accurateItem['item_no'])
            )
            ->get()
            ->filter(fn (GoodsReceiptRoundItem $item): bool => $this->itemIsSigned($item, $item->round))
            ->sum(fn (GoodsReceiptRoundItem $item): float => max(0, (float) $item->received_qty - (float) $item->damaged_qty));

        return max(0, (float) $accurateItem['quantity'] - $goodQty);
    }

    private function storeRoundAttachment(GoodsReceiptRound $round, UploadedFile $file, RoundAttachmentType $type): void
    {
        $metadata = $this->storage->storePhoto($file, $this->roundDirectory($round).'/'.$type->value);
        $round->attachments()->create([
            'attachment_type' => $type,
            ...$metadata,
        ]);
    }

    private function storeItemPhoto(GoodsReceiptRoundItem $roundItem, UploadedFile $file, RoundItemPhotoType $type): void
    {
        $round = $roundItem->round()->with('inspection')->firstOrFail();
        $metadata = $this->storage->storePhoto(
            $file,
            $this->roundDirectory($round).'/items/'.$this->safePathPart($roundItem->item_code ?? 'item').'/'.$type->value,
        );

        $roundItem->photos()->create([
            'photo_type' => $type,
            ...$metadata,
        ]);
    }

    /**
     * @param  array<string, mixed>  $itemInput
     * @return array<int, UploadedFile>
     */
    private function uploadedFiles(array $itemInput, string $singularField, string $arrayField): array
    {
        $files = [];

        if (($itemInput[$singularField] ?? null) instanceof UploadedFile) {
            $files[] = $itemInput[$singularField];
        }

        if (is_array($itemInput[$arrayField] ?? null)) {
            foreach ($itemInput[$arrayField] as $file) {
                if ($file instanceof UploadedFile) {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }

    private function roundDirectory(GoodsReceiptRound $round): string
    {
        $round->loadMissing('inspection');

        return 'goods-receipts/'.$this->safePathPart($round->inspection->pb_number).'/round-'.$round->round_number;
    }

    private function safePathPart(string $value): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '-', $value);

        return trim((string) $safe, '-') ?: 'file';
    }

    /** @param array<string, mixed> $data */
    private function firstSelectedUserId(array $data): int
    {
        foreach ($data['items'] as $item) {
            if (is_array($item) && (bool) ($item['is_checked'] ?? true) && isset($item['selected_user_id'])) {
                return (int) $item['selected_user_id'];
            }
        }

        return (int) $data['selected_user_id'];
    }

    private function itemIsSigned(GoodsReceiptRoundItem $item, GoodsReceiptRound $round): bool
    {
        return $item->user_status === RoundItemStatus::Signed
            || ($round->round_status === RoundStatus::Signed && $item->user_status === RoundItemStatus::Pending && $item->user_signed_by === null);
    }
}
