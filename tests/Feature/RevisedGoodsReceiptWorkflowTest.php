<?php

use App\Enums\GoodsReceiptInspectionStatus;
use App\Enums\PdfStatus;
use App\Enums\RoundStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\GoodsReceiptInspection;
use App\Models\GoodsReceiptRound;
use App\Models\GoodsReceiptRoundItem;
use App\Models\GoodsReceiptRoundItemPhoto;
use App\Models\Location;
use App\Models\PdfExport;
use App\Models\RoundAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function revisedWorkflowUser(UserRole $role, ?Location $location = null): User
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

function revisedWorkflowRound(
    Location $location,
    User $warehouse,
    User $selectedUser,
    RoundStatus $status,
    string $number,
): GoodsReceiptRound {
    $inspection = GoodsReceiptInspection::query()->create([
        'location_id' => $location->id,
        'created_by' => $warehouse->id,
        'accurate_receive_item_id' => "ACC-{$number}",
        'pb_number' => $number,
        'po_number' => "PO-{$number}",
        'supplier_name' => 'Supplier Test',
        'pb_date' => now()->toDateString(),
        'accurate_raw_json' => [
            'number' => $number,
            'detailItem' => [[
                'id' => 1,
                'item' => ['no' => 'ITEM-1'],
                'quantity' => 5,
            ]],
        ],
        'pb_status' => GoodsReceiptInspectionStatus::OpenPartial,
        'pdf_status' => PdfStatus::NotReady,
    ]);

    $round = GoodsReceiptRound::query()->create([
        'inspection_id' => $inspection->id,
        'round_number' => 1,
        'created_by' => $warehouse->id,
        'selected_user_id' => $selectedUser->id,
        'round_status' => $status,
        'specification_matches_po' => true,
    ]);

    GoodsReceiptRoundItem::query()->create([
        'round_id' => $round->id,
        'line_number' => 1,
        'accurate_detail_item_id' => '1',
        'item_code' => 'ITEM-1',
        'item_name' => 'Barang Test',
        'qty' => 5,
        'unit' => 'PCS',
        'quality_status' => 'GOOD',
        'quantity_status' => 'MATCH',
        'received_qty' => 5,
        'damaged_qty' => 0,
    ]);

    return $round->load('inspection');
}

it('uses Warehouse Head Warehouse User and Admin roles', function () {
    expect(UserRole::cases())
        ->toHaveCount(4)
        ->and(UserRole::User->value)->toBe('USER')
        ->and(UserRole::User->dashboardRoute())->toBe('dashboard.user');
});

it('routes a submitted round through Head Warehouse before User', function () {
    $location = Location::factory()->create();
    $warehouse = revisedWorkflowUser(UserRole::Warehouse, $location);
    $headWarehouse = revisedWorkflowUser(UserRole::HeadWarehouse);
    $user = revisedWorkflowUser(UserRole::User, $location);
    $round = revisedWorkflowRound($location, $warehouse, $user, RoundStatus::Draft, 'RCV-FLOW');

    $this->actingAs($warehouse)
        ->post(route('warehouse.rounds.submit', $round))
        ->assertRedirect();

    expect($round->refresh()->round_status)->toBe(RoundStatus::WaitingHeadWarehouseSignature);

    $this->actingAs($headWarehouse)
        ->post(route('head-warehouse.rounds.sign', $round), ['notes' => 'Pemeriksaan Head Warehouse sesuai.'])
        ->assertRedirect();

    expect($round->refresh()->round_status)->toBe(RoundStatus::WaitingUserSignature)
        ->and($round->head_warehouse_signed_by)->toBe($headWarehouse->id)
        ->and($round->head_warehouse_notes)->toBe('Pemeriksaan Head Warehouse sesuai.');
});

it('requires a reason when Head Warehouse rejects and returns the round to Warehouse', function () {
    $location = Location::factory()->create();
    $warehouse = revisedWorkflowUser(UserRole::Warehouse, $location);
    $headWarehouse = revisedWorkflowUser(UserRole::HeadWarehouse);
    $user = revisedWorkflowUser(UserRole::User, $location);
    $round = revisedWorkflowRound($location, $warehouse, $user, RoundStatus::WaitingHeadWarehouseSignature, 'RCV-HEAD-REJECT');

    $this->actingAs($headWarehouse)
        ->post(route('head-warehouse.rounds.reject', $round), ['notes' => ''])
        ->assertSessionHasErrors('notes');

    $this->actingAs($headWarehouse)
        ->post(route('head-warehouse.rounds.reject', $round), ['notes' => 'Foto surat jalan tidak jelas.'])
        ->assertRedirect();

    expect($round->refresh()->round_status)->toBe(RoundStatus::NeedsRevision)
        ->and($round->rejection_reason)->toBe('Foto surat jalan tidak jelas.')
        ->and($round->head_warehouse_notes)->toBe('Foto surat jalan tidak jelas.')
        ->and($round->rejected_by)->toBe($headWarehouse->id);
});

it('cancels the Head Warehouse signature when User rejects', function () {
    $location = Location::factory()->create();
    $warehouse = revisedWorkflowUser(UserRole::Warehouse, $location);
    $headWarehouse = revisedWorkflowUser(UserRole::HeadWarehouse);
    $user = revisedWorkflowUser(UserRole::User, $location);
    $round = revisedWorkflowRound($location, $warehouse, $user, RoundStatus::WaitingUserSignature, 'RCV-USER-REJECT');
    $round->update([
        'head_warehouse_signed_by' => $headWarehouse->id,
        'head_warehouse_signed_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('user.rounds.reject', $round), ['notes' => 'Foto barang perlu diulang.'])
        ->assertRedirect();

    expect($round->refresh()->round_status)->toBe(RoundStatus::NeedsRevision)
        ->and($round->head_warehouse_signed_by)->toBeNull()
        ->and($round->head_warehouse_signed_at)->toBeNull()
        ->and($round->rejection_reason)->toBe('Foto barang perlu diulang.')
        ->and($round->user_notes)->toBe('Foto barang perlu diulang.');
});

it('lets Admin remove a final goods receipt from operations', function () {
    Storage::fake('public');

    $location = Location::factory()->create();
    $warehouse = revisedWorkflowUser(UserRole::Warehouse, $location);
    $admin = revisedWorkflowUser(UserRole::Admin);
    $user = revisedWorkflowUser(UserRole::User, $location);
    $round = revisedWorkflowRound($location, $warehouse, $user, RoundStatus::Signed, 'RCV-REMOVE');
    $inspection = $round->inspection;
    $inspection->update([
        'pb_status' => GoodsReceiptInspectionStatus::PdfGenerated,
        'pdf_status' => PdfStatus::Generated,
    ]);

    Storage::disk('public')->put('goods-receipts/RCV-REMOVE/round-1/delivery.jpg', 'photo');
    Storage::disk('public')->put('goods-receipts/RCV-REMOVE/pdf/final.pdf', 'pdf');

    RoundAttachment::query()->create([
        'round_id' => $round->id,
        'attachment_type' => 'DELIVERY_NOTE',
        'r2_key' => 'goods-receipts/RCV-REMOVE/round-1/delivery.jpg',
    ]);
    PdfExport::query()->create([
        'inspection_id' => $inspection->id,
        'generated_by' => $admin->id,
        'r2_key' => 'goods-receipts/RCV-REMOVE/pdf/final.pdf',
        'file_name' => 'RCV-REMOVE.pdf',
        'file_size' => 3,
        'generated_at' => now(),
    ]);

    $this->actingAs($warehouse)
        ->delete(route('admin.monitoring.destroy', $inspection), [
            'confirmation_number' => 'RCV-REMOVE',
            'reason' => 'Tidak berwenang.',
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('admin.monitoring.destroy', $inspection), [
            'confirmation_number' => 'SALAH',
            'reason' => 'Penerimaan Barang dibuat ulang di Accurate.',
        ])
        ->assertSessionHasErrors('confirmation_number');

    expect(GoodsReceiptInspection::query()->whereKey($inspection->id)->exists())->toBeTrue();

    $this->actingAs($admin)
        ->delete(route('admin.monitoring.destroy', $inspection), [
            'confirmation_number' => 'RCV-REMOVE',
            'reason' => 'Penerimaan Barang dibuat ulang di Accurate.',
        ])
        ->assertRedirect(route('admin.monitoring.index'));

    expect(GoodsReceiptInspection::query()->whereKey($inspection->id)->exists())->toBeFalse()
        ->and(Storage::disk('public')->exists('goods-receipts/RCV-REMOVE/round-1/delivery.jpg'))->toBeFalse()
        ->and(Storage::disk('public')->exists('goods-receipts/RCV-REMOVE/pdf/final.pdf'))->toBeFalse()
        ->and(AuditLog::query()->where('event', 'GOODS_RECEIPT_REMOVED')->exists())->toBeTrue();
});

it('shows Round Penerimaan Barang submenus and business terminology', function () {
    $warehouseShell = file_get_contents(resource_path('js/components/warehouse-shell.tsx'));
    $adminShell = file_get_contents(resource_path('js/components/admin-shell.tsx'));
    $warehouseDashboard = file_get_contents(resource_path('js/pages/dashboards/warehouse.tsx'));
    $pbResult = file_get_contents(resource_path('js/pages/warehouse/pb-search/result.tsx'));
    $headWarehouseDashboard = file_get_contents(resource_path('js/pages/dashboards/head-warehouse.tsx'));
    $userDashboard = file_get_contents(resource_path('js/pages/dashboards/user.tsx'));

    expect($warehouseShell)->toContain('Round Penerimaan Barang', 'Perlu Perbaikan', 'Dalam Proses', 'Parsial', 'Selesai')
        ->and($adminShell)->toContain('Round Penerimaan Barang')
        ->and($warehouseShell)->toContain('roundCounts.process', 'roundCounts.partial', 'roundCounts.complete')
        ->and($warehouseDashboard)->toContain('isInView(round, activeView)')
        ->and($pbResult)->toContain('item.is_complete', "? 'Selesai'")
        ->and($headWarehouseDashboard)->toContain('Round Penerimaan Barang', 'Menunggu Saya')
        ->and($userDashboard)->toContain('Round Penerimaan Barang', 'Menunggu Saya');
});

it('uses the remaining quantity as the next round limit and marks completed items', function () {
    Storage::fake('public');
    Config::set('services.accurate.api_token', 'test-token');
    Config::set('services.accurate.signature_secret', 'test-secret');

    $location = Location::factory()->create(['name' => 'Karawang']);
    $warehouse = revisedWorkflowUser(UserRole::Warehouse, $location);
    $user = revisedWorkflowUser(UserRole::User, $location);
    $round = revisedWorkflowRound($location, $warehouse, $user, RoundStatus::Signed, 'RCV-REMAINING');
    $round->items()->update(['received_qty' => 4, 'quantity_status' => 'PARTIAL']);
    $round->inspection->update(['accurate_raw_json' => [
        'number' => 'RCV-REMAINING',
        'detailItem' => [[
            'id' => 1,
            'item' => ['no' => 'ITEM-1', 'name' => 'Barang Test'],
            'quantity' => 5,
        ]],
    ]]);

    Http::fake([
        '*/accurate/api/receive-item/list.do*' => Http::response(['s' => true, 'd' => [['id' => 1]]]),
        '*/accurate/api/receive-item/detail.do*' => Http::response(['s' => true, 'd' => $round->inspection->accurate_raw_json]),
    ]);

    $this->actingAs($warehouse)
        ->post(route('warehouse.rounds.store'), [
            'pb_number' => 'RCV-REMAINING',
            'location_id' => $location->id,
            'selected_user_id' => $user->id,
            'specification_matches_po' => true,
            'delivery_note_number' => 'SJ-2',
            'delivery_note_photo' => UploadedFile::fake()->image('sj.jpg'),
            'receiver_photo' => UploadedFile::fake()->image('receiver.jpg'),
            'items' => [[
                'is_checked' => true,
                'accurate_detail_item_id' => '1',
                'quality_status' => 'GOOD',
                'quantity_status' => 'MATCH',
                'damaged_qty' => 0,
                'item_photo' => UploadedFile::fake()->image('item.jpg'),
            ]],
        ])
        ->assertRedirect();

    expect((float) GoodsReceiptRound::query()->latest('id')->firstOrFail()->items()->firstOrFail()->received_qty)->toBe(1.0);

    GoodsReceiptRound::query()->latest('id')->firstOrFail()->update([
        'round_status' => RoundStatus::Signed,
        'completion_result' => 'COMPLETE',
    ]);

    $this->actingAs($warehouse)
        ->get(route('warehouse.pb-search.result', [
            'location_id' => $location->id,
            'pb_number' => 'RCV-REMAINING',
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('result.items.0.qty_remaining', 0)
            ->where('result.items.0.is_complete', true));
});

it('rejects a new round quantity above the accumulated remaining quantity', function () {
    Storage::fake('public');
    Config::set('services.accurate.api_token', 'test-token');
    Config::set('services.accurate.signature_secret', 'test-secret');

    $location = Location::factory()->create(['name' => 'Karawang']);
    $warehouse = revisedWorkflowUser(UserRole::Warehouse, $location);
    $user = revisedWorkflowUser(UserRole::User, $location);
    $round = revisedWorkflowRound($location, $warehouse, $user, RoundStatus::Signed, 'RCV-LIMIT');
    $round->items()->update(['received_qty' => 4, 'quantity_status' => 'PARTIAL']);
    $round->inspection->update(['accurate_raw_json' => [
        'number' => 'RCV-LIMIT',
        'detailItem' => [['id' => 1, 'item' => ['no' => 'ITEM-1'], 'quantity' => 5]],
    ]]);

    Http::fake([
        '*/accurate/api/receive-item/list.do*' => Http::response(['s' => true, 'd' => [['id' => 1]]]),
        '*/accurate/api/receive-item/detail.do*' => Http::response(['s' => true, 'd' => $round->inspection->accurate_raw_json]),
    ]);

    $this->actingAs($warehouse)
        ->post(route('warehouse.rounds.store'), [
            'pb_number' => 'RCV-LIMIT',
            'location_id' => $location->id,
            'selected_user_id' => $user->id,
            'specification_matches_po' => true,
            'delivery_note_number' => 'SJ-2',
            'delivery_note_photo' => UploadedFile::fake()->image('sj.jpg'),
            'receiver_photo' => UploadedFile::fake()->image('receiver.jpg'),
            'items' => [[
                'accurate_detail_item_id' => '1',
                'quality_status' => 'GOOD',
                'quantity_status' => 'PARTIAL',
                'received_qty' => 2,
                'damaged_qty' => 0,
                'item_photo' => UploadedFile::fake()->image('item.jpg'),
            ]],
        ])
        ->assertSessionHasErrors('pb_number');

    expect(GoodsReceiptRound::query()->count())->toBe(1);
});

it('keeps revision data and only deletes selected photos', function () {
    Storage::fake('public');
    Config::set('services.accurate.api_token', 'test-token');
    Config::set('services.accurate.signature_secret', 'test-secret');

    $location = Location::factory()->create(['name' => 'Karawang']);
    $warehouse = revisedWorkflowUser(UserRole::Warehouse, $location);
    $user = revisedWorkflowUser(UserRole::User, $location);
    $round = revisedWorkflowRound($location, $warehouse, $user, RoundStatus::NeedsRevision, 'RCV-REVISION');
    $item = $round->items()->firstOrFail();
    $round->update(['delivery_note_number' => 'SJ-SALAH']);
    $round->inspection->update(['accurate_raw_json' => [
        'number' => 'RCV-REVISION',
        'detailItem' => [[
            'id' => 1,
            'item' => ['no' => 'ITEM-1', 'name' => 'Barang Test'],
            'detailName' => 'Barang Test',
            'quantity' => 5,
            'itemUnit' => ['name' => 'PCS'],
        ]],
    ]]);

    foreach (['hapus.jpg', 'tetap.jpg'] as $file) {
        Storage::disk('public')->put("goods-receipts/RCV-REVISION/{$file}", $file);
        GoodsReceiptRoundItemPhoto::query()->create([
            'round_item_id' => $item->id,
            'photo_type' => 'ITEM',
            'r2_key' => "goods-receipts/RCV-REVISION/{$file}",
        ]);
    }

    foreach ([['DELIVERY_NOTE', 'sj.jpg'], ['RECEIVER_PHOTO', 'receiver.jpg']] as [$type, $file]) {
        Storage::disk('public')->put("goods-receipts/RCV-REVISION/{$file}", $file);
        RoundAttachment::query()->create([
            'round_id' => $round->id,
            'attachment_type' => $type,
            'r2_key' => "goods-receipts/RCV-REVISION/{$file}",
        ]);
    }

    $deletedPhoto = $item->photos()->where('r2_key', 'like', '%hapus.jpg')->firstOrFail();
    Http::fake([
        '*/accurate/api/receive-item/list.do*' => Http::response(['s' => true, 'd' => [['id' => 1]]]),
        '*/accurate/api/receive-item/detail.do*' => Http::response(['s' => true, 'd' => $round->inspection->accurate_raw_json]),
    ]);

    $response = $this->actingAs($warehouse)
        ->put(route('warehouse.rounds.update', $round), [
            'pb_number' => 'RCV-REVISION',
            'location_id' => $location->id,
            'selected_user_id' => $user->id,
            'specification_matches_po' => true,
            'delivery_note_number' => 'SJ-BENAR',
            'delete_photo_ids' => [$deletedPhoto->id],
            'items' => [[
                'is_checked' => true,
                'accurate_detail_item_id' => '1',
                'quality_status' => 'GOOD',
                'quantity_status' => 'MATCH',
                'damaged_qty' => 0,
            ]],
        ]);

    $response->assertRedirect(route('warehouse.rounds.show', $round));

    expect($round->refresh()->delivery_note_number)->toBe('SJ-BENAR')
        ->and($round->items()->firstOrFail()->id)->toBe($item->id)
        ->and($item->photos()->count())->toBe(1)
        ->and($round->attachments()->count())->toBe(2)
        ->and(Storage::disk('public')->exists('goods-receipts/RCV-REVISION/hapus.jpg'))->toBeFalse()
        ->and(Storage::disk('public')->exists('goods-receipts/RCV-REVISION/tetap.jpg'))->toBeTrue();
});

it('lets involved users and Head Warehouse download the final PDF', function () {
    Storage::fake('public');
    $location = Location::factory()->create();
    $warehouse = revisedWorkflowUser(UserRole::Warehouse, $location);
    $involvedUser = revisedWorkflowUser(UserRole::User, $location);
    $otherUser = revisedWorkflowUser(UserRole::User, $location);
    $headWarehouse = revisedWorkflowUser(UserRole::HeadWarehouse);
    $round = revisedWorkflowRound($location, $warehouse, $involvedUser, RoundStatus::Signed, 'RCV-PDF-ROLES');

    Storage::disk('public')->put('goods-receipts/RCV-PDF-ROLES/pdf/final.pdf', '%PDF test');
    $pdf = PdfExport::query()->create([
        'inspection_id' => $round->inspection_id,
        'generated_by' => $involvedUser->id,
        'r2_key' => 'goods-receipts/RCV-PDF-ROLES/pdf/final.pdf',
        'file_name' => 'RCV-PDF-ROLES.pdf',
        'file_size' => 9,
        'generated_at' => now(),
    ]);

    $this->actingAs($involvedUser)->get(route('files.pdf-exports.show', $pdf))->assertOk();
    $this->actingAs($headWarehouse)->get(route('files.pdf-exports.show', $pdf))->assertOk();
    $this->actingAs($otherUser)->get(route('files.pdf-exports.show', $pdf))->assertForbidden();
});
