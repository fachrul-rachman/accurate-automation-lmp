<?php

use App\Enums\GoodsReceiptInspectionStatus;
use App\Enums\PdfStatus;
use App\Enums\RoundStatus;
use App\Models\AuditLog;
use App\Models\GoodsReceiptInspection;
use App\Models\GoodsReceiptRound;
use App\Models\GoodsReceiptRoundItem;
use App\Models\Location;
use App\Models\PdfExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function phaseEightUser(string $role): User
{
    return User::factory()->create([
        'role' => $role,
        'is_active' => true,
    ]);
}

function phaseEightInspection(string $pbNumber, GoodsReceiptInspectionStatus $status = GoodsReceiptInspectionStatus::OpenPartial): GoodsReceiptInspection
{
    $location = Location::factory()->create(['name' => 'Karawang']);
    $warehouse = phaseEightUser('WAREHOUSE');
    $warehouse->locations()->sync([$location->id]);
    $hod = phaseEightUser('HOD');

    $inspection = GoodsReceiptInspection::query()->create([
        'location_id' => $location->id,
        'created_by' => $warehouse->id,
        'accurate_receive_item_id' => $pbNumber,
        'pb_number' => $pbNumber,
        'po_number' => 'PO-'.$pbNumber,
        'supplier_name' => 'PT Supplier',
        'pb_date' => now()->toDateString(),
        'accurate_raw_json' => ['number' => $pbNumber],
        'pb_status' => $status,
        'pdf_status' => $status === GoodsReceiptInspectionStatus::PdfGenerated ? PdfStatus::Generated : PdfStatus::NotReady,
    ]);

    $round = GoodsReceiptRound::query()->create([
        'inspection_id' => $inspection->id,
        'round_number' => 1,
        'created_by' => $warehouse->id,
        'selected_hod_id' => $hod->id,
        'round_status' => $status === GoodsReceiptInspectionStatus::OpenPartial ? RoundStatus::WaitingHodSignature : RoundStatus::Signed,
        'completion_result' => $status === GoodsReceiptInspectionStatus::OpenPartial ? null : 'COMPLETE',
        'specification_matches_po' => true,
        'submitted_at' => now(),
        'hod_signed_by' => $status === GoodsReceiptInspectionStatus::OpenPartial ? null : $hod->id,
        'hod_signed_at' => $status === GoodsReceiptInspectionStatus::OpenPartial ? null : now(),
        'head_warehouse_signed_by' => $status === GoodsReceiptInspectionStatus::OpenPartial ? null : phaseEightUser('HEAD_WAREHOUSE')->id,
        'head_warehouse_signed_at' => $status === GoodsReceiptInspectionStatus::OpenPartial ? null : now(),
    ]);

    GoodsReceiptRoundItem::query()->create([
        'round_id' => $round->id,
        'line_number' => 1,
        'accurate_detail_item_id' => '9001',
        'item_code' => 'ITM-1',
        'item_name' => 'Barang Test',
        'qty' => 5,
        'unit' => 'PCS',
        'po_number' => 'PO-'.$pbNumber,
        'quality_status' => 'GOOD',
        'quantity_status' => 'MATCH',
        'received_qty' => 5,
        'damaged_qty' => 0,
    ]);

    return $inspection;
}

it('lets admin monitor PB list and filter by status', function () {
    $admin = phaseEightUser('ADMIN');
    phaseEightInspection('PB-OPEN', GoodsReceiptInspectionStatus::OpenPartial);
    $generated = phaseEightInspection('PB-GENERATED', GoodsReceiptInspectionStatus::PdfGenerated);

    $this->actingAs($admin)
        ->get(route('admin.monitoring.index', ['status' => 'PDF_GENERATED']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/monitoring/index')
            ->has('inspections', 1)
            ->where('inspections.0.id', $generated->id)
            ->where('filters.status', 'PDF_GENERATED')
            ->has('status_options', 3)
        );
});

it('lets admin open read-only PB detail with rounds audit logs and PDF link', function () {
    Storage::fake('public');

    $admin = phaseEightUser('ADMIN');
    $inspection = phaseEightInspection('PB-ADMIN-DETAIL', GoodsReceiptInspectionStatus::PdfGenerated);
    Storage::disk('public')->put('goods-receipts/PB-ADMIN-DETAIL/pdf/final.pdf', '%PDF test');
    $pdf = PdfExport::query()->create([
        'inspection_id' => $inspection->id,
        'generated_by' => $admin->id,
        'r2_key' => 'goods-receipts/PB-ADMIN-DETAIL/pdf/final.pdf',
        'file_name' => 'PB-ADMIN-DETAIL-final.pdf',
        'file_size' => 9,
        'generated_at' => now(),
    ]);

    AuditLog::query()->create([
        'user_id' => $admin->id,
        'event' => 'PDF_GENERATED',
        'auditable_type' => GoodsReceiptInspection::class,
        'auditable_id' => (string) $inspection->id,
        'description' => 'PDF final dibuat.',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.monitoring.show', $inspection))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/monitoring/show')
            ->where('inspection.id', $inspection->id)
            ->where('inspection.pdf_download_url', route('files.pdf-exports.show', $pdf))
            ->has('rounds', 1)
            ->has('audit_logs', 1)
            ->where('audit_logs.0.event', 'PDF dibuat')
        );
});

it('blocks non admin users from admin monitoring', function () {
    $warehouse = phaseEightUser('WAREHOUSE');
    $inspection = phaseEightInspection('PB-BLOCKED');

    $this->actingAs($warehouse)
        ->get(route('admin.monitoring.index'))
        ->assertForbidden();

    $this->actingAs($warehouse)
        ->get(route('admin.monitoring.show', $inspection))
        ->assertForbidden();
});

it('writes audit logs for completion PDF generation and admin PDF download', function () {
    Storage::fake('public');

    $admin = phaseEightUser('ADMIN');
    $location = Location::factory()->create(['name' => 'Karawang']);
    $warehouse = phaseEightUser('WAREHOUSE');
    $warehouse->locations()->sync([$location->id]);
    $hod = phaseEightUser('HOD');
    $headWarehouse = phaseEightUser('HEAD_WAREHOUSE');

    $inspection = GoodsReceiptInspection::query()->create([
        'location_id' => $location->id,
        'created_by' => $warehouse->id,
        'accurate_receive_item_id' => 'PB-AUDIT',
        'pb_number' => 'PB-AUDIT',
        'po_number' => 'PO-PB-AUDIT',
        'supplier_name' => 'PT Supplier',
        'pb_date' => now()->toDateString(),
        'accurate_raw_json' => ['number' => 'PB-AUDIT'],
        'pb_status' => GoodsReceiptInspectionStatus::OpenPartial,
        'pdf_status' => PdfStatus::NotReady,
    ]);

    $round = GoodsReceiptRound::query()->create([
        'inspection_id' => $inspection->id,
        'round_number' => 1,
        'created_by' => $warehouse->id,
        'selected_hod_id' => $hod->id,
        'round_status' => RoundStatus::WaitingHeadWarehouseSignature,
        'specification_matches_po' => true,
        'submitted_at' => now(),
        'hod_signed_by' => $hod->id,
        'hod_signed_at' => now(),
    ]);

    GoodsReceiptRoundItem::query()->create([
        'round_id' => $round->id,
        'line_number' => 1,
        'accurate_detail_item_id' => '9001',
        'item_code' => 'ITM-1',
        'item_name' => 'Barang Test',
        'qty' => 5,
        'unit' => 'PCS',
        'po_number' => 'PO-PB-AUDIT',
        'quality_status' => 'GOOD',
        'quantity_status' => 'MATCH',
        'received_qty' => 5,
        'damaged_qty' => 0,
    ]);

    $this->actingAs($headWarehouse)->post(route('head-warehouse.rounds.sign', $round));

    $pdf = PdfExport::query()->where('inspection_id', $inspection->id)->firstOrFail();

    $this->actingAs($admin)
        ->get(route('files.pdf-exports.show', $pdf))
        ->assertOk();

    $events = AuditLog::query()
        ->where('auditable_type', GoodsReceiptInspection::class)
        ->where('auditable_id', (string) $inspection->id)
        ->pluck('event')
        ->all();

    expect($events)->toContain('PB_MARKED_COMPLETE')
        ->and($events)->toContain('PDF_GENERATED')
        ->and($events)->toContain('PDF_DOWNLOADED');
});
