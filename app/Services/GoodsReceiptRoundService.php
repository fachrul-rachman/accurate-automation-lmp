<?php

namespace App\Services;

use App\Enums\GoodsReceiptInspectionStatus;
use App\Enums\PdfStatus;
use App\Enums\QualityStatus;
use App\Enums\QuantityStatus;
use App\Enums\RoundAttachmentType;
use App\Enums\RoundItemPhotoType;
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
                'selected_user_id' => $data['selected_user_id'],
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

        $oldKeys = [
            ...$round->attachments->pluck('r2_key')->all(),
            ...$round->items->flatMap->photos->pluck('r2_key')->all(),
        ];

        $updated = DB::transaction(function () use ($round, $data, $detail, $warehouse): GoodsReceiptRound {
            $round->items()->delete();
            $round->attachments()->delete();
            $round->update([
                'selected_user_id' => $data['selected_user_id'],
                'specification_matches_po' => (bool) $data['specification_matches_po'],
                'notes' => $data['notes'] ?? null,
                'delivery_note_number' => $data['delivery_note_number'],
            ]);
            $this->storeRoundContents($round, $detail, $data);

            $this->auditLogs->write(
                'ROUND_REVISED',
                $warehouse,
                $round,
                "Round {$round->round_number} Penerimaan Barang {$round->inspection->pb_number} diperbaiki.",
            );

            return $round->refresh();
        });

        Storage::disk($this->storage->disk())->delete($oldKeys);

        return $updated;
    }

    public function submitToHeadWarehouse(GoodsReceiptRound $round): GoodsReceiptRound
    {
        if (! in_array($round->round_status, [RoundStatus::Draft, RoundStatus::NeedsRevision], true)) {
            throw new RuntimeException('Round sudah dikunci dan tidak bisa disubmit ulang.');
        }

        $round->update([
            'round_status' => RoundStatus::WaitingHeadWarehouseSignature,
            'submitted_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'head_warehouse_notes' => null,
            'user_notes' => null,
        ]);

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
        if ($round->round_status !== RoundStatus::WaitingHeadWarehouseSignature) {
            throw new RuntimeException('Round ini belum siap ditandatangani Head Warehouse.');
        }

        return DB::transaction(function () use ($round, $headWarehouse, $notes): GoodsReceiptRound {
            $round->update([
                'round_status' => RoundStatus::WaitingUserSignature,
                'head_warehouse_signed_by' => $headWarehouse->id,
                'head_warehouse_signed_at' => now(),
                'head_warehouse_notes' => $notes,
            ]);

            $round->loadMissing('inspection');
            $this->auditLogs->write(
                'HEAD_WAREHOUSE_SIGNED',
                $headWarehouse,
                $round,
                "Head Warehouse menandatangani round {$round->round_number} Penerimaan Barang {$round->inspection->pb_number}.",
            );

            return $round->refresh();
        });
    }

    public function rejectByHeadWarehouse(GoodsReceiptRound $round, User $headWarehouse, string $reason): GoodsReceiptRound
    {
        if ($round->round_status !== RoundStatus::WaitingHeadWarehouseSignature) {
            throw new RuntimeException('Round ini belum siap diperiksa Head Warehouse.');
        }

        return $this->reject($round, $headWarehouse, $reason, 'head_warehouse_notes', 'HEAD_WAREHOUSE_REJECTED');
    }

    public function rejectByUser(GoodsReceiptRound $round, User $user, string $reason): GoodsReceiptRound
    {
        if ($round->round_status !== RoundStatus::WaitingUserSignature) {
            throw new RuntimeException('Round ini belum siap diperiksa User.');
        }

        return $this->reject($round, $user, $reason, 'user_notes', 'USER_REJECTED');
    }

    public function signByUser(GoodsReceiptRound $round, User $user, ?string $notes = null): GoodsReceiptRound
    {
        if ($round->round_status !== RoundStatus::WaitingUserSignature) {
            throw new RuntimeException('Round ini belum siap ditandatangani User.');
        }

        return DB::transaction(function () use ($round, $user, $notes): GoodsReceiptRound {
            $round->update([
                'round_status' => RoundStatus::Signed,
                'user_signed_by' => $user->id,
                'user_signed_at' => now(),
                'user_notes' => $notes,
            ]);

            $round->loadMissing('inspection.rounds.items');
            $completionResult = $this->calculateCompletionResult($round);

            $round->update([
                'completion_result' => $completionResult,
            ]);

            $this->auditLogs->write(
                'USER_SIGNED',
                $user,
                $round,
                "User menandatangani round {$round->round_number} Penerimaan Barang {$round->inspection->pb_number}.",
            );

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

    private function reject(GoodsReceiptRound $round, User $actor, string $notes, string $notesColumn, string $event): GoodsReceiptRound
    {
        return DB::transaction(function () use ($round, $actor, $notes, $notesColumn, $event): GoodsReceiptRound {
            $round->update([
                'round_status' => RoundStatus::NeedsRevision,
                'head_warehouse_signed_by' => null,
                'head_warehouse_signed_at' => null,
                'user_signed_by' => null,
                'user_signed_at' => null,
                'rejected_by' => $actor->id,
                'rejected_at' => now(),
                'rejection_reason' => $notes,
                $notesColumn => $notes,
            ]);

            $round->loadMissing('inspection');
            $this->auditLogs->write(
                $event,
                $actor,
                $round,
                "Round {$round->round_number} Penerimaan Barang {$round->inspection->pb_number} dikembalikan ke Warehouse.",
                metadata: ['notes' => $notes],
            );

            return $round->refresh();
        });
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

                if ($inspectionRound->round_status !== RoundStatus::Signed) {
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
        $qty = (float) $accurateItem['quantity'];
        $quantityStatus = QuantityStatus::from((string) $itemInput['quantity_status']);
        $receivedQty = $quantityStatus === QuantityStatus::Match ? $qty : (float) $itemInput['received_qty'];
        $damagedQty = (float) ($itemInput['damaged_qty'] ?? 0);

        if ($receivedQty > $qty) {
            throw new RuntimeException('Received quantity tidak boleh lebih besar dari quantity Accurate.');
        }

        if ($damagedQty > $receivedQty) {
            throw new RuntimeException('Damaged quantity tidak boleh lebih besar dari received quantity.');
        }

        return GoodsReceiptRoundItem::query()->create([
            'round_id' => $round->id,
            'line_number' => $lineNumber,
            'accurate_detail_item_id' => $accurateItem['accurate_item_row_id'],
            'accurate_item_id' => null,
            'item_code' => $accurateItem['item_no'],
            'item_name' => $accurateItem['item_name'],
            'qty' => $qty,
            'unit' => $accurateItem['unit'],
            'po_number' => $accurateItem['po_number'],
            'quality_status' => QualityStatus::from((string) $itemInput['quality_status']),
            'quantity_status' => $quantityStatus,
            'received_qty' => $receivedQty,
            'damaged_qty' => $damagedQty,
        ]);
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
}
