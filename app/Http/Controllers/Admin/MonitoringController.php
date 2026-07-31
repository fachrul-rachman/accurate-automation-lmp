<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GoodsReceiptInspectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RemoveInspectionRequest;
use App\Models\AuditLog;
use App\Models\GoodsReceiptInspection;
use App\Models\GoodsReceiptRound;
use App\Models\GoodsReceiptRoundItem;
use App\Support\DateFormatter;
use App\Support\RoundItemQuantity;
use App\Services\AuditLogService;
use App\Services\R2StorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

final class MonitoringController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogs,
        private readonly R2StorageService $storage,
    ) {}

    public function index(Request $request): Response
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('q', ''));

        $inspections = GoodsReceiptInspection::query()
            ->with(['location', 'rounds', 'pdfExport'])
            ->when(
                is_string($status) && $status !== '',
                fn ($query) => $query->where('pb_status', $status)
            )
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('pb_number', 'like', "%{$search}%")
                        ->orWhere('po_number', 'like', "%{$search}%")
                        ->orWhere('supplier_name', 'like', "%{$search}%");
                });
            })
            ->latest('updated_at')
            ->limit(50)
            ->get()
            ->map(fn (GoodsReceiptInspection $inspection): array => [
                'id' => $inspection->id,
                'pb_number' => $inspection->pb_number,
                'po_number' => $inspection->po_number,
                'supplier_name' => $inspection->supplier_name,
                'location_name' => $inspection->location?->name,
                'pb_status' => $inspection->pb_status->value,
                'pb_status_label' => $inspection->pb_status->label(),
                'pdf_status_label' => $inspection->pdf_status->label(),
                'round_count' => $inspection->rounds->count(),
                'updated_at' => DateFormatter::wib($inspection->updated_at),
                'pdf_download_url' => $inspection->pdfExport === null
                    ? null
                    : route('files.pdf-exports.show', $inspection->pdfExport),
            ])
            ->all();

        return Inertia::render('admin/monitoring/index', [
            'inspections' => $inspections,
            'filters' => [
                'status' => is_string($status) ? $status : '',
                'q' => $search,
            ],
            'status_options' => array_map(fn (GoodsReceiptInspectionStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ], GoodsReceiptInspectionStatus::cases()),
        ]);
    }

    public function show(GoodsReceiptInspection $inspection): Response
    {
        $inspection->load([
            'location',
            'creator',
            'pdfExport',
            'rounds.items.photos',
            'rounds.attachments',
            'rounds.creator',
            'rounds.selectedUser',
            'rounds.userSigner',
            'rounds.headWarehouseSigner',
        ]);

        $roundIds = $inspection->rounds->pluck('id')->map(fn ($id): string => (string) $id)->all();
        $auditLogs = AuditLog::query()
            ->with('user:id,name')
            ->where(function ($query) use ($inspection, $roundIds): void {
                $query
                    ->where(function ($query) use ($inspection): void {
                        $query
                            ->where('auditable_type', GoodsReceiptInspection::class)
                            ->where('auditable_id', (string) $inspection->id);
                    })
                    ->orWhere(function ($query) use ($roundIds): void {
                        $query
                            ->where('auditable_type', GoodsReceiptRound::class)
                            ->whereIn('auditable_id', $roundIds);
                    });
            })
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'event' => $this->auditEventLabel($log->event),
                'description' => $log->description,
                'user_name' => $log->user?->name,
                'created_at' => DateFormatter::wib($log->created_at),
            ])
            ->all();

        return Inertia::render('admin/monitoring/show', [
            'inspection' => [
                'id' => $inspection->id,
                'pb_number' => $inspection->pb_number,
                'po_number' => $inspection->po_number,
                'supplier_name' => $inspection->supplier_name,
                'pb_date' => $inspection->pb_date?->toDateString(),
                'location_name' => $inspection->location?->name,
                'created_by' => $inspection->creator?->name,
                'pb_status' => $inspection->pb_status->label(),
                'pdf_status' => $inspection->pdf_status->label(),
                'pdf_download_url' => $inspection->pdfExport === null
                    ? null
                    : route('files.pdf-exports.show', $inspection->pdfExport),
            ],
            'rounds' => $inspection->rounds->map(fn (GoodsReceiptRound $round): array => $this->roundPayload($round))->all(),
            'audit_logs' => $auditLogs,
        ]);
    }

    public function destroy(RemoveInspectionRequest $request, GoodsReceiptInspection $inspection): RedirectResponse
    {
        $inspection->load(['location', 'pdfExport', 'rounds.attachments', 'rounds.items.photos']);
        $keys = $inspection->rounds
            ->flatMap(fn (GoodsReceiptRound $round) => [
                ...$round->attachments->pluck('r2_key')->all(),
                ...$round->items->flatMap->photos->pluck('r2_key')->all(),
            ])
            ->when($inspection->pdfExport !== null, fn ($keys) => $keys->push($inspection->pdfExport->r2_key))
            ->filter()
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($request, $inspection): void {
            $this->auditLogs->write(
                'GOODS_RECEIPT_REMOVED',
                $request->user(),
                null,
                "Penerimaan Barang {$inspection->pb_number} dihapus dari operasional.",
                metadata: [
                    'pb_number' => $inspection->pb_number,
                    'location' => $inspection->location?->name,
                    'last_status' => $inspection->pb_status->value,
                    'reason' => $request->validated('reason'),
                ],
                request: $request,
            );

            $inspection->delete();
        });

        foreach (array_unique([$this->storage->disk(), 'public']) as $disk) {
            Storage::disk($disk)->delete($keys);
        }

        return redirect()->route('admin.monitoring.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function roundPayload(GoodsReceiptRound $round): array
    {
        $remainingByItem = RoundItemQuantity::remainingByRoundItem($round);

        return [
            'id' => $round->id,
            'round_number' => $round->round_number,
            'round_status' => $round->round_status->label(),
            'completion_result' => $round->completion_result,
            'specification_matches_po' => $round->specification_matches_po,
            'notes' => $round->notes,
            'created_by' => $round->creator?->name,
            'selected_user' => $round->selectedUser?->name,
            'user_signed_by' => $round->userSigner?->name,
            'user_signed_at' => DateFormatter::wib($round->user_signed_at),
            'head_warehouse_signed_by' => $round->headWarehouseSigner?->name,
            'head_warehouse_signed_at' => DateFormatter::wib($round->head_warehouse_signed_at),
            'items' => $round->items->map(fn (GoodsReceiptRoundItem $item): array => $this->itemPayload($item, $remainingByItem[$item->id] ?? 0.0))->all(),
            'attachment_count' => $round->attachments->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(GoodsReceiptRoundItem $item, float $remainingQty): array
    {
        return [
            'id' => $item->id,
            'item_code' => $item->item_code,
            'item_name' => $item->item_name,
            'po_number' => $item->po_number,
            'qty' => (float) $item->qty,
            'unit' => $item->unit,
            'received_qty' => $item->received_qty === null ? null : (float) $item->received_qty,
            'damaged_qty' => (float) $item->damaged_qty,
            'qty_remaining' => $remainingQty,
            'quality_status' => $item->quality_status->label(),
            'quantity_status' => $item->quantity_status->label(),
            'photo_count' => $item->photos->count(),
        ];
    }

    private function auditEventLabel(string $event): string
    {
        return match ($event) {
            'ROUND_CREATED' => 'Round dibuat',
            'ROUND_SUBMITTED_TO_HEAD_WAREHOUSE' => 'Round dikirim ke Head Warehouse',
            'HEAD_WAREHOUSE_SIGNED' => 'Kepala gudang menandatangani',
            'USER_SIGNED' => 'User menandatangani',
            'HEAD_WAREHOUSE_REJECTED' => 'Head Warehouse menolak',
            'USER_REJECTED' => 'User menolak',
            'PB_IMPORTED' => 'Penerimaan Barang diimpor',
            'ROUND_UPDATED' => 'Draft round diperbarui',
            'PB_MARKED_COMPLETE' => 'Penerimaan Barang lengkap',
            'PB_MARKED_PARTIAL' => 'Penerimaan Barang sebagian',
            'PDF_GENERATED' => 'PDF dibuat',
            'PDF_DOWNLOADED' => 'PDF diunduh',
            'USER_CREATED' => 'User dibuat',
            'USER_UPDATED' => 'User diperbarui',
            'LOCATION_CREATED' => 'Lokasi dibuat',
            'LOCATION_UPDATED' => 'Lokasi diperbarui',
            'USER_LOCATION_ACCESS_UPDATED' => 'Akses lokasi user diperbarui',
            'GOODS_RECEIPT_REMOVED' => 'Penerimaan Barang dihapus dari operasional',
            default => str($event)->lower()->replace('_', ' ')->title()->toString(),
        };
    }
}
