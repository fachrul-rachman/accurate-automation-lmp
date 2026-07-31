<?php

use App\Enums\GoodsReceiptInspectionStatus;
use App\Enums\RoundStatus;
use App\Models\GoodsReceiptInspection;
use App\Models\GoodsReceiptRound;
use App\Models\GoodsReceiptRoundItem;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function phaseSixLocation(): Location
{
    return Location::factory()->create([
        'name' => 'Karawang',
        'accurate_host_url' => 'https://zeus.accurate.id',
        'is_active' => true,
    ]);
}

function phaseSixUser(string $role, ?Location $location = null): User
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

function phaseSixRound(Location $location, User $warehouse, User $hod, string $pbNumber, float $qty = 5, float $receivedQty = 5, RoundStatus $status = RoundStatus::WaitingHeadWarehouseSignature): GoodsReceiptRound
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
        'pdf_status' => 'NOT_READY',
    ]);

    $round = GoodsReceiptRound::query()->create([
        'inspection_id' => $inspection->id,
        'round_number' => 1,
        'created_by' => $warehouse->id,
        'selected_user_id' => $hod->id,
        'round_status' => $status,
        'specification_matches_po' => true,
        'submitted_at' => now(),
        'user_signed_by' => null,
        'user_signed_at' => null,
    ]);

    GoodsReceiptRoundItem::query()->create([
        'round_id' => $round->id,
        'line_number' => 1,
        'accurate_detail_item_id' => '9001',
        'item_code' => 'ITM-1',
        'item_name' => 'Barang Test',
        'qty' => $qty,
        'unit' => 'PCS',
        'po_number' => 'PO-'.$pbNumber,
        'quality_status' => 'GOOD',
        'quantity_status' => $receivedQty >= $qty ? 'MATCH' : 'PARTIAL',
        'received_qty' => $receivedQty,
        'damaged_qty' => 0,
    ]);

    return $round->load('inspection');
}

function phaseSixRoundWithMissingAccurateItem(Location $location, User $warehouse, User $hod): GoodsReceiptRound
{
    $inspection = GoodsReceiptInspection::query()->create([
        'location_id' => $location->id,
        'created_by' => $warehouse->id,
        'accurate_receive_item_id' => 'PB-HW-MISSING-ITEM',
        'pb_number' => 'PB-HW-MISSING-ITEM',
        'po_number' => 'PO-MISSING',
        'supplier_name' => 'PT Supplier',
        'pb_date' => now()->toDateString(),
        'accurate_raw_json' => [
            'number' => 'PB-HW-MISSING-ITEM',
            'detailItem' => [
                ['id' => 9001, 'quantity' => 5],
                ['id' => 9002, 'quantity' => 5],
            ],
        ],
        'pb_status' => GoodsReceiptInspectionStatus::OpenPartial,
        'pdf_status' => 'NOT_READY',
    ]);

    $round = GoodsReceiptRound::query()->create([
        'inspection_id' => $inspection->id,
        'round_number' => 1,
        'created_by' => $warehouse->id,
        'selected_user_id' => $hod->id,
        'round_status' => RoundStatus::WaitingHeadWarehouseSignature,
        'specification_matches_po' => true,
        'submitted_at' => now(),
        'user_signed_by' => null,
        'user_signed_at' => null,
    ]);

    GoodsReceiptRoundItem::query()->create([
        'round_id' => $round->id,
        'line_number' => 1,
        'accurate_detail_item_id' => '9001',
        'item_code' => 'ITM-1',
        'item_name' => 'Barang Datang',
        'qty' => 5,
        'unit' => 'PCS',
        'po_number' => 'PO-MISSING',
        'quality_status' => 'GOOD',
        'quantity_status' => 'MATCH',
        'received_qty' => 5,
        'damaged_qty' => 0,
    ]);

    return $round->load('inspection');
}

it('shows Head Warehouse pending and history rounds', function () {
    $location = phaseSixLocation();
    $warehouse = phaseSixUser('WAREHOUSE', $location);
    $hod = phaseSixUser('USER', $location);
    $headWarehouse = phaseSixUser('HEAD_WAREHOUSE');
    $pendingRound = phaseSixRound($location, $warehouse, $hod, 'PB-HW-PENDING');
    phaseSixRound($location, $warehouse, $hod, 'PB-HW-HISTORY', 5, 5, RoundStatus::Signed);

    $this->actingAs($headWarehouse)
        ->get(route('dashboard.head-warehouse'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboards/head-warehouse')
            ->has('pending_rounds', 1)
            ->has('history_rounds', 1)
            ->where('pending_round_count', 1)
            ->where('history_round_count', 1)
            ->where('pending_rounds.0.id', $pendingRound->id)
        );
});

it('lets Head Warehouse view and sign a waiting round as partial', function () {
    $location = phaseSixLocation();
    $warehouse = phaseSixUser('WAREHOUSE', $location);
    $hod = phaseSixUser('USER', $location);
    $headWarehouse = phaseSixUser('HEAD_WAREHOUSE');
    $round = phaseSixRound($location, $warehouse, $hod, 'PB-HW-PARTIAL', 5, 3);

    $this->actingAs($headWarehouse)
        ->get(route('head-warehouse.rounds.show', $round))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('head-warehouse/rounds/show')
            ->where('round.id', $round->id)
            ->where('round.can_sign', true)
        );

    $this->actingAs($headWarehouse)
        ->post(route('head-warehouse.rounds.sign', $round))
        ->assertRedirect(route('head-warehouse.rounds.show', $round));

    $round->refresh();
    $round->inspection->refresh();

    expect($round->round_status)->toBe(RoundStatus::WaitingUserSignature)
        ->and($round->head_warehouse_signed_by)->toBe($headWarehouse->id)
        ->and($round->head_warehouse_signed_at)->not->toBeNull()
        ->and($round->completion_result)->toBeNull()
        ->and($round->inspection->pb_status)->toBe(GoodsReceiptInspectionStatus::OpenPartial);
});

it('marks PB complete when signed round fulfills all quantity', function () {
    $location = phaseSixLocation();
    $warehouse = phaseSixUser('WAREHOUSE', $location);
    $hod = phaseSixUser('USER', $location);
    $headWarehouse = phaseSixUser('HEAD_WAREHOUSE');
    $round = phaseSixRound($location, $warehouse, $hod, 'PB-HW-COMPLETE', 5, 5);

    $this->actingAs($headWarehouse)
        ->post(route('head-warehouse.rounds.sign', $round))
        ->assertRedirect(route('head-warehouse.rounds.show', $round));
    $this->actingAs($hod)
        ->post(route('user.rounds.sign', $round))
        ->assertRedirect(route('user.rounds.show', $round));

    $round->refresh();
    $round->inspection->refresh();

    expect($round->round_status)->toBe(RoundStatus::Signed)
        ->and($round->completion_result)->toBe('COMPLETE')
        ->and($round->inspection->pb_status)->toBe(GoodsReceiptInspectionStatus::PdfGenerated);
});

it('keeps PB partial when an Accurate item has not arrived yet', function () {
    $location = phaseSixLocation();
    $warehouse = phaseSixUser('WAREHOUSE', $location);
    $hod = phaseSixUser('USER', $location);
    $headWarehouse = phaseSixUser('HEAD_WAREHOUSE');
    $round = phaseSixRoundWithMissingAccurateItem($location, $warehouse, $hod);

    $this->actingAs($headWarehouse)
        ->post(route('head-warehouse.rounds.sign', $round))
        ->assertRedirect(route('head-warehouse.rounds.show', $round));
    $this->actingAs($hod)
        ->post(route('user.rounds.sign', $round))
        ->assertRedirect(route('user.rounds.show', $round));

    $round->refresh();
    $round->inspection->refresh();

    expect($round->completion_result)->toBe('PARTIAL')
        ->and($round->inspection->pb_status)->toBe(GoodsReceiptInspectionStatus::OpenPartial);
});

it('blocks non Head Warehouse and wrong status from signing', function () {
    $location = phaseSixLocation();
    $warehouse = phaseSixUser('WAREHOUSE', $location);
    $hod = phaseSixUser('USER', $location);
    $round = phaseSixRound($location, $warehouse, $hod, 'PB-HW-BLOCKED');
    $wrongStatusRound = phaseSixRound($location, $warehouse, $hod, 'PB-HW-DONE', 5, 5, RoundStatus::Signed);

    $this->actingAs($hod)
        ->post(route('head-warehouse.rounds.sign', $round))
        ->assertForbidden();

    $this->actingAs(phaseSixUser('HEAD_WAREHOUSE'))
        ->post(route('head-warehouse.rounds.sign', $wrongStatusRound))
        ->assertSessionHasErrors('round');
});
