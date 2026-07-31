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

function phaseSevenLocation(): Location
{
    return Location::factory()->create([
        'name' => 'Karawang',
        'accurate_host_url' => 'https://zeus.accurate.id',
        'is_active' => true,
    ]);
}

function phaseSevenUser(string $role, ?Location $location = null): User
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

function phaseSevenRound(Location $location, User $warehouse, User $hod, string $pbNumber, float $qty = 5, float $receivedQty = 5): GoodsReceiptRound
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
        'selected_user_id' => $hod->id,
        'round_status' => RoundStatus::WaitingUserSignature,
        'specification_matches_po' => true,
        'notes' => 'Barang diterima baik.',
        'submitted_at' => now(),
        'head_warehouse_signed_by' => phaseSevenUser('HEAD_WAREHOUSE')->id,
        'head_warehouse_signed_at' => now(),
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

it('generates final PDF when Head Warehouse signs a complete PB', function () {
    Storage::fake('public');

    $location = phaseSevenLocation();
    $warehouse = phaseSevenUser('WAREHOUSE', $location);
    $hod = phaseSevenUser('USER', $location);
    $headWarehouse = phaseSevenUser('HEAD_WAREHOUSE');
    $round = phaseSevenRound($location, $warehouse, $hod, 'PB-PDF-COMPLETE', 5, 5);

    $this->actingAs($hod)
        ->post(route('user.rounds.sign', $round))
        ->assertRedirect(route('user.rounds.show', $round));

    $round->refresh();
    $round->inspection->refresh();
    $pdf = PdfExport::query()->where('inspection_id', $round->inspection_id)->first();

    expect($round->inspection->pb_status)->toBe(GoodsReceiptInspectionStatus::PdfGenerated)
        ->and($round->inspection->pdf_status)->toBe(PdfStatus::Generated)
        ->and($pdf)->not->toBeNull();

    Storage::disk('public')->assertExists($pdf->r2_key);
    expect(Storage::disk('public')->get($pdf->r2_key))->toStartWith('%PDF');
});

it('does not generate final PDF for partial PB', function () {
    Storage::fake('public');

    $location = phaseSevenLocation();
    $warehouse = phaseSevenUser('WAREHOUSE', $location);
    $hod = phaseSevenUser('USER', $location);
    $headWarehouse = phaseSevenUser('HEAD_WAREHOUSE');
    $round = phaseSevenRound($location, $warehouse, $hod, 'PB-PDF-PARTIAL', 5, 3);

    $this->actingAs($hod)
        ->post(route('user.rounds.sign', $round))
        ->assertRedirect(route('user.rounds.show', $round));

    $round->inspection->refresh();

    expect($round->inspection->pb_status)->toBe(GoodsReceiptInspectionStatus::OpenPartial)
        ->and($round->inspection->pdf_status)->toBe(PdfStatus::NotReady)
        ->and(PdfExport::query()->where('inspection_id', $round->inspection_id)->exists())->toBeFalse();
});

it('lets authorized users download generated final PDF', function () {
    Storage::fake('public');

    $location = phaseSevenLocation();
    $warehouse = phaseSevenUser('WAREHOUSE', $location);
    $hod = phaseSevenUser('USER', $location);
    $headWarehouse = phaseSevenUser('HEAD_WAREHOUSE');
    $round = phaseSevenRound($location, $warehouse, $hod, 'PB-PDF-DOWNLOAD', 5, 5);

    $this->actingAs($hod)->post(route('user.rounds.sign', $round));

    $pdf = PdfExport::query()->where('inspection_id', $round->inspection_id)->firstOrFail();

    $this->actingAs($warehouse)
        ->get(route('files.pdf-exports.show', $pdf))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('shows final PDF download link on Warehouse dashboard and round detail after completion', function () {
    Storage::fake('public');

    $location = phaseSevenLocation();
    $warehouse = phaseSevenUser('WAREHOUSE', $location);
    $hod = phaseSevenUser('USER', $location);
    $headWarehouse = phaseSevenUser('HEAD_WAREHOUSE');
    $round = phaseSevenRound($location, $warehouse, $hod, 'PB-PDF-HW-LINK', 5, 5);

    $this->actingAs($hod)->post(route('user.rounds.sign', $round));

    $pdf = PdfExport::query()->where('inspection_id', $round->inspection_id)->firstOrFail();

    $this->actingAs($warehouse)
        ->get(route('dashboard.warehouse'))
        ->assertInertia(fn ($page) => $page
            ->where('active_rounds.0.pdf_download_url', route('files.pdf-exports.show', $pdf))
        );

    $this->actingAs($warehouse)
        ->get(route('warehouse.rounds.show', $round))
        ->assertInertia(fn ($page) => $page
            ->where('round.pdf_download_url', route('files.pdf-exports.show', $pdf))
        );
});
