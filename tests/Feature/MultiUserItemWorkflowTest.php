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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function multiUserWorkflowUser(UserRole $role, ?Location $location = null): User
{
    $user = User::factory()->create(['role' => $role, 'is_active' => true]);

    if ($location !== null) {
        $user->locations()->sync([$location->id]);
    }

    return $user;
}

/** @return array{0: GoodsReceiptRound, 1: GoodsReceiptRoundItem, 2: GoodsReceiptRoundItem} */
function multiUserWorkflowRound(Location $location, User $warehouse, User $firstUser, User $secondUser): array
{
    $inspection = GoodsReceiptInspection::query()->create([
        'location_id' => $location->id,
        'created_by' => $warehouse->id,
        'pb_number' => 'PB-MULTI-USER',
        'po_number' => 'PO-100',
        'supplier_name' => 'PT Supplier',
        'pb_date' => now()->toDateString(),
        'accurate_raw_json' => ['detailItem' => [
            ['id' => 'ROW-A', 'item' => ['no' => 'A'], 'quantity' => 2],
            ['id' => 'ROW-B', 'item' => ['no' => 'B'], 'quantity' => 3],
        ]],
        'pb_status' => GoodsReceiptInspectionStatus::OpenPartial,
        'pdf_status' => PdfStatus::NotReady,
    ]);

    $round = GoodsReceiptRound::query()->create([
        'inspection_id' => $inspection->id,
        'round_number' => 1,
        'created_by' => $warehouse->id,
        'selected_user_id' => $firstUser->id,
        'round_status' => RoundStatus::WaitingHeadWarehouseSignature,
        'specification_matches_po' => true,
        'delivery_note_number' => 'SJ-100',
        'submitted_at' => now(),
    ]);

    $items = collect([
        ['ROW-A', 'A', 'Item A', 2, $firstUser->id],
        ['ROW-B', 'B', 'Item B', 3, $secondUser->id],
    ])->map(fn (array $item, int $index): GoodsReceiptRoundItem => GoodsReceiptRoundItem::query()->create([
        'round_id' => $round->id,
        'line_number' => $index + 1,
        'accurate_detail_item_id' => $item[0],
        'item_code' => $item[1],
        'item_name' => $item[2],
        'qty' => $item[3],
        'unit' => 'PCS',
        'quality_status' => 'GOOD',
        'quantity_status' => 'MATCH',
        'received_qty' => $item[3],
        'damaged_qty' => 0,
        'selected_user_id' => $item[4],
        'head_status' => 'PENDING',
        'user_status' => 'PENDING',
    ]));

    return [$round, $items[0], $items[1]];
}

it('lets Head Warehouse approve some items and reject selected items only', function () {
    $location = Location::factory()->create();
    $warehouse = multiUserWorkflowUser(UserRole::Warehouse, $location);
    $head = multiUserWorkflowUser(UserRole::HeadWarehouse);
    $firstUser = multiUserWorkflowUser(UserRole::User, $location);
    $secondUser = multiUserWorkflowUser(UserRole::User, $location);
    [$round, $firstItem, $secondItem] = multiUserWorkflowRound($location, $warehouse, $firstUser, $secondUser);

    $this->actingAs($head)->post(route('head-warehouse.rounds.reject', $round), [
        'item_ids' => [$secondItem->id],
        'notes' => 'Foto Item B harus diperbaiki.',
    ])->assertRedirect();

    expect($firstItem->refresh()->head_status->value)->toBe('APPROVED')
        ->and($firstItem->user_status->value)->toBe('PENDING')
        ->and($secondItem->refresh()->head_status->value)->toBe('REJECTED')
        ->and($round->refresh()->round_status)->toBe(RoundStatus::NeedsRevision)
        ->and(AuditLog::query()->where('event', 'HEAD_WAREHOUSE_REJECTED')->value('metadata'))
        ->toMatchArray(['notes' => 'Foto Item B harus diperbaiki.', 'item_ids' => [$secondItem->id]]);

    $this->actingAs($firstUser)->get(route('user.rounds.show', $round))
        ->assertInertia(fn (Assert $page) => $page
            ->has('round.items', 1)
            ->where('round.items.0.id', $firstItem->id)
            ->where('round.can_sign', true));
});

it('finishes a round only after every assigned User signs their approved items', function () {
    Storage::fake('public');
    $location = Location::factory()->create();
    $warehouse = multiUserWorkflowUser(UserRole::Warehouse, $location);
    $head = multiUserWorkflowUser(UserRole::HeadWarehouse);
    $firstUser = multiUserWorkflowUser(UserRole::User, $location);
    $secondUser = multiUserWorkflowUser(UserRole::User, $location);
    [$round, $firstItem, $secondItem] = multiUserWorkflowRound($location, $warehouse, $firstUser, $secondUser);

    $round->update(['round_status' => RoundStatus::WaitingUserSignature]);
    $round->items()->update([
        'head_status' => 'APPROVED',
        'head_signed_by' => $head->id,
        'head_signed_at' => now(),
    ]);

    $this->actingAs($firstUser)->post(route('user.rounds.sign', $round), ['notes' => 'Item A diterima.'])
        ->assertRedirect();

    expect($firstItem->refresh()->user_status->value)->toBe('SIGNED')
        ->and($secondItem->refresh()->user_status->value)->toBe('PENDING')
        ->and($round->refresh()->round_status)->toBe(RoundStatus::WaitingUserSignature)
        ->and(PdfExport::query()->count())->toBe(0);

    $this->actingAs($secondUser)->post(route('user.rounds.sign', $round), ['notes' => 'Item B diterima.'])
        ->assertRedirect();

    expect($secondItem->refresh()->user_status->value)->toBe('SIGNED')
        ->and($round->refresh()->round_status)->toBe(RoundStatus::Signed)
        ->and($round->completion_result)->toBe('COMPLETE')
        ->and(PdfExport::query()->where('inspection_id', $round->inspection_id)->exists())->toBeTrue();
});

it('shows every rejection note as application history without replacing earlier notes', function () {
    $location = Location::factory()->create();
    $warehouse = multiUserWorkflowUser(UserRole::Warehouse, $location);
    $head = multiUserWorkflowUser(UserRole::HeadWarehouse);
    $firstUser = multiUserWorkflowUser(UserRole::User, $location);
    $secondUser = multiUserWorkflowUser(UserRole::User, $location);
    [$round, $firstItem, $secondItem] = multiUserWorkflowRound($location, $warehouse, $firstUser, $secondUser);

    foreach ([
        [$head, 'HEAD_WAREHOUSE_REJECTED', 'Foto Item A buram.', [$firstItem->id]],
        [$firstUser, 'USER_REJECTED', 'Jumlah Item A tidak sesuai.', [$firstItem->id]],
    ] as [$actor, $event, $note, $itemIds]) {
        AuditLog::query()->create([
            'user_id' => $actor->id,
            'event' => $event,
            'auditable_type' => GoodsReceiptRound::class,
            'auditable_id' => (string) $round->id,
            'description' => 'Round dikembalikan ke Warehouse.',
            'metadata' => ['notes' => $note, 'item_ids' => $itemIds],
            'created_at' => now(),
        ]);
    }

    $this->actingAs($warehouse)->get(route('warehouse.rounds.show', $round))
        ->assertInertia(fn (Assert $page) => $page
            ->has('round.notes_history', 2)
            ->where('round.notes_history.0.note', 'Jumlah Item A tidak sesuai.')
            ->where('round.notes_history.1.note', 'Foto Item A buram.'));
});

it('keeps the requested compact PDF layout rules in the template', function () {
    $template = file_get_contents(resource_path('views/pdf/goods-receipt-final.blade.php'));

    expect($template)
        ->not->toContain('No. Surat Jalan:')
        ->toContain("attachments->chunk(2)", "photos->chunk(2)", 'signatureUsers->chunk(3)')
        ->toContain('Lokasi Penerima:', 'No. Checklist:', 'Supplier:', 'Tanggal Checklist:', 'No. PO:');
});
