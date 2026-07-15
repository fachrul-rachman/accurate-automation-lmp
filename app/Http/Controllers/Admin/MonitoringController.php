<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GoodsReceiptInspectionStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\GoodsReceiptInspection;
use App\Models\GoodsReceiptRound;
use App\Models\GoodsReceiptRoundItem;
use App\Support\DateFormatter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class MonitoringController extends Controller
{
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
            'rounds.selectedHod',
            'rounds.hodSigner',
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

    /**
     * @return array<string, mixed>
     */
    private function roundPayload(GoodsReceiptRound $round): array
    {
        return [
            'id' => $round->id,
            'round_number' => $round->round_number,
            'round_status' => $round->round_status->label(),
            'completion_result' => $round->completion_result,
            'specification_matches_po' => $round->specification_matches_po,
            'notes' => $round->notes,
            'created_by' => $round->creator?->name,
            'selected_hod' => $round->selectedHod?->name,
            'hod_signed_by' => $round->hodSigner?->name,
            'hod_signed_at' => DateFormatter::wib($round->hod_signed_at),
            'head_warehouse_signed_by' => $round->headWarehouseSigner?->name,
            'head_warehouse_signed_at' => DateFormatter::wib($round->head_warehouse_signed_at),
            'items' => $round->items->map(fn (GoodsReceiptRoundItem $item): array => $this->itemPayload($item))->all(),
            'attachment_count' => $round->attachments->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(GoodsReceiptRoundItem $item): array
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
            'quality_status' => $item->quality_status->label(),
            'quantity_status' => $item->quantity_status->label(),
            'photo_count' => $item->photos->count(),
        ];
    }

    private function auditEventLabel(string $event): string
    {
        return match ($event) {
            'ROUND_CREATED' => 'Round dibuat',
            'ROUND_SUBMITTED_TO_HOD' => 'Round dikirim ke user',
            'HOD_SIGNED' => 'User menandatangani',
            'HEAD_WAREHOUSE_SIGNED' => 'Kepala gudang menandatangani',
            'PB_IMPORTED' => 'PB diimport',
            'ROUND_UPDATED' => 'Draft round diperbarui',
            'PB_MARKED_COMPLETE' => 'PB lengkap',
            'PB_MARKED_PARTIAL' => 'PB sebagian',
            'PDF_GENERATED' => 'PDF dibuat',
            'PDF_DOWNLOADED' => 'PDF diunduh',
            'USER_CREATED' => 'User dibuat',
            'USER_UPDATED' => 'User diperbarui',
            'LOCATION_CREATED' => 'Lokasi dibuat',
            'LOCATION_UPDATED' => 'Lokasi diperbarui',
            'HOD_MAPPING_UPDATED' => 'Akses lokasi user diperbarui',
            default => str($event)->lower()->replace('_', ' ')->title()->toString(),
        };
    }
}
