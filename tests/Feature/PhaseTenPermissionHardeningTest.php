<?php

use App\Enums\GoodsReceiptInspectionStatus;
use App\Enums\PdfStatus;
use App\Enums\RoundStatus;
use App\Models\GoodsReceiptInspection;
use App\Models\GoodsReceiptRound;
use App\Models\GoodsReceiptRoundItem;
use App\Models\Location;
use App\Models\PdfExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function phaseTenUser(string $role, ?Location $location = null): User
{
    $user = User::factory()->create([
        'role' => $role,
        'is_active' => true,
    ]);

    if ($location !== null) {
        $user->locations()->sync([$location->id]);
    }

    return $user;
}

function phaseTenRound(Location $location, User $warehouse, User $hod, string $pbNumber, RoundStatus $status): GoodsReceiptRound
{
    $inspection = GoodsReceiptInspection::query()->create([
        'location_id' => $location->id,
        'created_by' => $warehouse->id,
        'accurate_receive_item_id' => $pbNumber,
        'pb_number' => $pbNumber,
        'po_number' => 'PO-'.$pbNumber,
        'supplier_name' => 'PT Supplier',
        'pb_date' => now()->toDateString(),
        'accurate_raw_json' => ['number' => $pbNumber],
        'pb_status' => GoodsReceiptInspectionStatus::OpenPartial,
        'pdf_status' => PdfStatus::NotReady,
    ]);

    $round = GoodsReceiptRound::query()->create([
        'inspection_id' => $inspection->id,
        'round_number' => 1,
        'created_by' => $warehouse->id,
        'selected_hod_id' => $hod->id,
        'round_status' => $status,
        'specification_matches_po' => true,
        'submitted_at' => now(),
        'hod_signed_by' => in_array($status, [RoundStatus::WaitingHeadWarehouseSignature, RoundStatus::Signed], true) ? $hod->id : null,
        'hod_signed_at' => in_array($status, [RoundStatus::WaitingHeadWarehouseSignature, RoundStatus::Signed], true) ? now() : null,
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

    return $round->load('inspection');
}

it('blocks direct access to dashboards for the wrong role', function () {
    $location = Location::factory()->create();
    $warehouse = phaseTenUser('WAREHOUSE', $location);
    $hod = phaseTenUser('HOD', $location);
    $headWarehouse = phaseTenUser('HEAD_WAREHOUSE');
    $admin = phaseTenUser('ADMIN');

    $this->actingAs($warehouse)->get(route('dashboard.hod'))->assertForbidden();
    $this->actingAs($warehouse)->get(route('dashboard.head-warehouse'))->assertForbidden();
    $this->actingAs($warehouse)->get(route('dashboard.admin'))->assertForbidden();

    $this->actingAs($hod)->get(route('dashboard.warehouse'))->assertForbidden();
    $this->actingAs($headWarehouse)->get(route('dashboard.warehouse'))->assertForbidden();
    $this->actingAs($admin)->get(route('dashboard.warehouse'))->assertForbidden();
});

it('blocks warehouse from viewing PB detail outside assigned locations', function () {
    $allowedLocation = Location::factory()->create();
    $otherLocation = Location::factory()->create();
    $warehouse = phaseTenUser('WAREHOUSE', $allowedLocation);
    $otherWarehouse = phaseTenUser('WAREHOUSE', $otherLocation);
    $hod = phaseTenUser('HOD', $otherLocation);
    $round = phaseTenRound($otherLocation, $otherWarehouse, $hod, 'PB-OTHER-LOCATION', RoundStatus::Signed);

    $this->actingAs($warehouse)
        ->get(route('warehouse.inspections.show', $round->inspection))
        ->assertForbidden();
});

it('blocks Head Warehouse from opening rounds before Head Warehouse step', function () {
    $location = Location::factory()->create();
    $warehouse = phaseTenUser('WAREHOUSE', $location);
    $hod = phaseTenUser('HOD', $location);
    $headWarehouse = phaseTenUser('HEAD_WAREHOUSE');
    $draftRound = phaseTenRound($location, $warehouse, $hod, 'PB-HW-NOT-READY', RoundStatus::Draft);
    $hodRound = phaseTenRound($location, $warehouse, $hod, 'PB-HW-HOD-STEP', RoundStatus::WaitingHodSignature);

    $this->actingAs($headWarehouse)
        ->get(route('head-warehouse.rounds.show', $draftRound))
        ->assertForbidden();

    $this->actingAs($headWarehouse)
        ->get(route('head-warehouse.rounds.show', $hodRound))
        ->assertForbidden();
});

it('allows only Admin and assigned-location Warehouse to download final PDF', function () {
    Storage::fake('public');

    $location = Location::factory()->create();
    $otherLocation = Location::factory()->create();
    $warehouse = phaseTenUser('WAREHOUSE', $location);
    $otherWarehouse = phaseTenUser('WAREHOUSE', $otherLocation);
    $hod = phaseTenUser('HOD', $location);
    $headWarehouse = phaseTenUser('HEAD_WAREHOUSE');
    $admin = phaseTenUser('ADMIN');
    $round = phaseTenRound($location, $warehouse, $hod, 'PB-PDF-PERMISSION', RoundStatus::Signed);
    $round->inspection->update([
        'pb_status' => GoodsReceiptInspectionStatus::PdfGenerated,
        'pdf_status' => PdfStatus::Generated,
    ]);

    Storage::disk('public')->put('goods-receipts/PB-PDF-PERMISSION/pdf/final.pdf', '%PDF test');
    $pdf = PdfExport::query()->create([
        'inspection_id' => $round->inspection_id,
        'generated_by' => $admin->id,
        'r2_key' => 'goods-receipts/PB-PDF-PERMISSION/pdf/final.pdf',
        'file_name' => 'PB-PDF-PERMISSION-final.pdf',
        'file_size' => 9,
        'generated_at' => now(),
    ]);

    $this->actingAs($warehouse)->get(route('files.pdf-exports.show', $pdf))->assertOk();
    $this->actingAs($admin)->get(route('files.pdf-exports.show', $pdf))->assertOk();
    $this->actingAs($otherWarehouse)->get(route('files.pdf-exports.show', $pdf))->assertForbidden();
    $this->actingAs($hod)->get(route('files.pdf-exports.show', $pdf))->assertForbidden();
    $this->actingAs($headWarehouse)->get(route('files.pdf-exports.show', $pdf))->assertForbidden();
});
