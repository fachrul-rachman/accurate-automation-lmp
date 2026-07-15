<?php

use App\Enums\RoundStatus;
use App\Models\GoodsReceiptInspection;
use App\Models\GoodsReceiptRound;
use App\Models\GoodsReceiptRoundItem;
use App\Models\GoodsReceiptRoundItemPhoto;
use App\Models\Location;
use App\Models\RoundAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function phaseFiveLocation(): Location
{
    return Location::factory()->create([
        'name' => 'Karawang',
        'accurate_host_url' => 'https://zeus.accurate.id',
        'is_active' => true,
    ]);
}

function phaseFiveUser(string $role, ?Location $location = null): User
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

function phaseFiveRound(Location $location, User $warehouse, User $hod, string $pbNumber, RoundStatus $status = RoundStatus::WaitingHodSignature): GoodsReceiptRound
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
        'pb_status' => 'OPEN_PARTIAL',
        'pdf_status' => 'NOT_READY',
    ]);

    $round = GoodsReceiptRound::query()->create([
        'inspection_id' => $inspection->id,
        'round_number' => 1,
        'created_by' => $warehouse->id,
        'selected_hod_id' => $hod->id,
        'round_status' => $status,
        'specification_matches_po' => true,
        'submitted_at' => now(),
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

function phaseFivePartialReasonRound(Location $location, User $warehouse, User $hod): GoodsReceiptRound
{
    $inspection = GoodsReceiptInspection::query()->create([
        'location_id' => $location->id,
        'created_by' => $warehouse->id,
        'accurate_receive_item_id' => 'PB-HOD-PARTIAL-REASON',
        'pb_number' => 'PB-HOD-PARTIAL-REASON',
        'po_number' => 'PO-PARTIAL',
        'supplier_name' => 'PT Supplier',
        'pb_date' => now()->toDateString(),
        'accurate_raw_json' => [
            'number' => 'PB-HOD-PARTIAL-REASON',
            'detailItem' => [
                ['id' => 9001, 'quantity' => 45],
                ['id' => 9002, 'quantity' => 10],
                ['id' => 9003, 'quantity' => 5],
            ],
        ],
        'pb_status' => 'OPEN_PARTIAL',
        'pdf_status' => 'NOT_READY',
    ]);

    $round = GoodsReceiptRound::query()->create([
        'inspection_id' => $inspection->id,
        'round_number' => 1,
        'created_by' => $warehouse->id,
        'selected_hod_id' => $hod->id,
        'round_status' => RoundStatus::WaitingHodSignature,
        'specification_matches_po' => true,
        'submitted_at' => now(),
    ]);

    GoodsReceiptRoundItem::query()->create([
        'round_id' => $round->id,
        'line_number' => 1,
        'accurate_detail_item_id' => '9001',
        'item_code' => 'ITM-1',
        'item_name' => 'Barang Kurang',
        'qty' => 45,
        'unit' => 'PCS',
        'po_number' => 'PO-PARTIAL',
        'quality_status' => 'GOOD',
        'quantity_status' => 'PARTIAL',
        'received_qty' => 20,
        'damaged_qty' => 0,
    ]);

    GoodsReceiptRoundItem::query()->create([
        'round_id' => $round->id,
        'line_number' => 2,
        'accurate_detail_item_id' => '9002',
        'item_code' => 'ITM-2',
        'item_name' => 'Barang Rusak',
        'qty' => 10,
        'unit' => 'PCS',
        'po_number' => 'PO-PARTIAL',
        'quality_status' => 'DAMAGED',
        'quantity_status' => 'MATCH',
        'received_qty' => 10,
        'damaged_qty' => 2,
    ]);

    return $round->load('inspection');
}

it('shows HoD only rounds assigned to them', function () {
    $location = phaseFiveLocation();
    $warehouse = phaseFiveUser('WAREHOUSE', $location);
    $hod = phaseFiveUser('HOD', $location);
    $otherHod = phaseFiveUser('HOD', $location);
    $visibleRound = phaseFiveRound($location, $warehouse, $hod, 'PB-HOD-1');
    phaseFiveRound($location, $warehouse, $otherHod, 'PB-HOD-2');

    $this->actingAs($hod)
        ->get(route('dashboard.hod'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboards/hod')
            ->has('pending_rounds', 1)
            ->where('pending_rounds.0.id', $visibleRound->id)
            ->where('pending_rounds.0.pb_number', 'PB-HOD-1')
        );
});

it('shows HoD pending and history counts separately', function () {
    $location = phaseFiveLocation();
    $warehouse = phaseFiveUser('WAREHOUSE', $location);
    $hod = phaseFiveUser('HOD', $location);
    phaseFiveRound($location, $warehouse, $hod, 'PB-HOD-PENDING');
    phaseFiveRound($location, $warehouse, $hod, 'PB-HOD-HISTORY', RoundStatus::WaitingHeadWarehouseSignature);

    $this->actingAs($hod)
        ->get(route('dashboard.hod'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboards/hod')
            ->has('pending_rounds', 1)
            ->has('history_rounds', 1)
            ->where('pending_round_count', 1)
            ->where('history_round_count', 1)
            ->where('history_rounds.0.pb_number', 'PB-HOD-HISTORY')
        );
});

it('lets assigned HoD view and sign a waiting round', function () {
    $location = phaseFiveLocation();
    $warehouse = phaseFiveUser('WAREHOUSE', $location);
    $hod = phaseFiveUser('HOD', $location);
    $round = phaseFiveRound($location, $warehouse, $hod, 'PB-HOD-SIGN');

    $this->actingAs($hod)
        ->get(route('hod.rounds.show', $round))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('hod/rounds/show')
            ->where('round.id', $round->id)
            ->where('round.can_sign', true)
        );

    $this->actingAs($hod)
        ->post(route('hod.rounds.sign', $round))
        ->assertRedirect(route('hod.rounds.show', $round));

    $round->refresh();

    expect($round->round_status)->toBe(RoundStatus::WaitingHeadWarehouseSignature)
        ->and($round->hod_signed_by)->toBe($hod->id)
        ->and($round->hod_signed_at)->not->toBeNull();
});

it('shows partial reasons before User signs a round', function () {
    $location = phaseFiveLocation();
    $warehouse = phaseFiveUser('WAREHOUSE', $location);
    $hod = phaseFiveUser('HOD', $location);
    $round = phaseFivePartialReasonRound($location, $warehouse, $hod);

    $this->actingAs($hod)
        ->get(route('hod.rounds.show', $round))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('hod/rounds/show')
            ->where('round.partial_reasons.0', 'Ada item yang belum datang.')
            ->where('round.partial_reasons.1', 'Ada quantity item yang belum lengkap.')
            ->where('round.partial_reasons.2', 'Ada item rusak.')
            ->where('round.items.0.qty_remaining', 25)
            ->where('round.items.1.qty_remaining', 2)
        );
});

it('lets assigned HoD view uploaded round and item photos', function () {
    Storage::fake('public');
    $location = phaseFiveLocation();
    $warehouse = phaseFiveUser('WAREHOUSE', $location);
    $hod = phaseFiveUser('HOD', $location);
    $round = phaseFiveRound($location, $warehouse, $hod, 'PB-HOD-PHOTO');
    $item = GoodsReceiptRoundItem::query()->where('round_id', $round->id)->firstOrFail();

    Storage::disk('public')->put('goods-receipts/PB-HOD-PHOTO/round-1/delivery.jpg', 'delivery');
    Storage::disk('public')->put('goods-receipts/PB-HOD-PHOTO/round-1/items/item.jpg', 'item');

    $attachment = RoundAttachment::query()->create([
        'round_id' => $round->id,
        'attachment_type' => 'DELIVERY_NOTE',
        'r2_key' => 'goods-receipts/PB-HOD-PHOTO/round-1/delivery.jpg',
        'original_filename' => 'delivery.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 8,
    ]);

    $photo = GoodsReceiptRoundItemPhoto::query()->create([
        'round_item_id' => $item->id,
        'photo_type' => 'ITEM',
        'r2_key' => 'goods-receipts/PB-HOD-PHOTO/round-1/items/item.jpg',
        'original_filename' => 'item.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 4,
    ]);

    $this->actingAs($hod)
        ->get(route('hod.rounds.show', $round))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('hod/rounds/show')
            ->has('round.attachments', 1)
            ->where('round.attachments.0.url', route('files.round-attachments.show', $attachment))
            ->has('round.items.0.photos', 1)
            ->where('round.items.0.photos.0.url', route('files.round-item-photos.show', $photo))
        );

    $this->actingAs($hod)
        ->get(route('files.round-attachments.show', $attachment))
        ->assertOk();

    $this->actingAs($hod)
        ->get(route('files.round-item-photos.show', $photo))
        ->assertOk();
});

it('blocks HoD from signing unrelated or non waiting rounds', function () {
    $location = phaseFiveLocation();
    $warehouse = phaseFiveUser('WAREHOUSE', $location);
    $hod = phaseFiveUser('HOD', $location);
    $otherHod = phaseFiveUser('HOD', $location);
    $unrelatedRound = phaseFiveRound($location, $warehouse, $otherHod, 'PB-HOD-OTHER');
    $signedRound = phaseFiveRound($location, $warehouse, $hod, 'PB-HOD-DONE', RoundStatus::WaitingHeadWarehouseSignature);

    $this->actingAs($hod)
        ->get(route('hod.rounds.show', $unrelatedRound))
        ->assertForbidden();

    $this->actingAs($hod)
        ->post(route('hod.rounds.sign', $unrelatedRound))
        ->assertForbidden();

    $this->actingAs($hod)
        ->post(route('hod.rounds.sign', $signedRound))
        ->assertSessionHasErrors('round');
});
