<?php

use App\Enums\GoodsReceiptInspectionStatus;
use App\Enums\PdfStatus;
use App\Enums\RoundStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\GoodsReceiptInspection;
use App\Models\GoodsReceiptRound;
use App\Models\GoodsReceiptRoundItem;
use App\Models\Location;
use App\Models\PdfExport;
use App\Models\RoundAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

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
    $headWarehouseDashboard = file_get_contents(resource_path('js/pages/dashboards/head-warehouse.tsx'));
    $userDashboard = file_get_contents(resource_path('js/pages/dashboards/user.tsx'));

    expect($warehouseShell)->toContain('Round Penerimaan Barang', 'Perlu Perbaikan', 'Dalam Proses', 'Parsial', 'Selesai')
        ->and($adminShell)->toContain('Round Penerimaan Barang')
        ->and($headWarehouseDashboard)->toContain('Round Penerimaan Barang', 'Menunggu Saya')
        ->and($userDashboard)->toContain('Round Penerimaan Barang', 'Menunggu Saya');
});
