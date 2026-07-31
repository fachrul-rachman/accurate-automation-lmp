<?php

use App\Enums\RoundStatus;
use App\Models\GoodsReceiptInspection;
use App\Models\GoodsReceiptRound;
use App\Models\GoodsReceiptRoundItem;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function phaseFourLocation(): Location
{
    return Location::factory()->create([
        'name' => 'Karawang',
        'accurate_host_url' => 'https://zeus.accurate.id',
        'is_active' => true,
    ]);
}

function phaseFourWarehouse(Location $location): User
{
    $warehouse = User::factory()->create([
        'role' => 'WAREHOUSE',
        'is_active' => true,
    ]);

    $warehouse->locations()->sync([$location->id]);

    return $warehouse;
}

function phaseFourHod(Location $location): User
{
    $hod = User::factory()->create([
        'role' => 'USER',
        'is_active' => true,
    ]);

    $hod->locations()->sync([$location->id]);

    return $hod;
}

function fakePhaseFourAccurate(string $pbNumber = 'PB-004'): void
{
    Config::set('services.accurate.api_token', 'test-token');
    Config::set('services.accurate.signature_secret', 'test-secret');
    Config::set('services.accurate.timeout', 5);

    Http::fake([
        'https://zeus.accurate.id/accurate/api/receive-item/list.do*' => Http::response([
            's' => true,
            'd' => [
                [
                    'id' => 16201,
                    'number' => $pbNumber,
                ],
            ],
        ]),
        'https://zeus.accurate.id/accurate/api/receive-item/detail.do*' => Http::response([
            's' => true,
            'd' => [
                'id' => 16201,
                'number' => $pbNumber,
                'purchaseOrder' => ['number' => 'PO-004'],
                'vendor' => ['name' => 'PT Sumber Barang'],
                'transDateView' => '2026-07-09',
                'detailItem' => [
                    [
                        'id' => 9001,
                        'seq' => 1,
                        'itemId' => 501,
                        'item' => ['no' => 'ITM-1', 'name' => 'Umum-PCS'],
                        'detailName' => 'Travo BHL250L',
                        'purchaseOrder' => ['number' => 'PO-004'],
                        'quantity' => 5,
                        'itemUnit' => ['name' => 'PCS'],
                    ],
                ],
            ],
        ]),
    ]);
}

function fakePhaseFourAccurateWithTwoItems(string $pbNumber = 'PB-004-TWO'): void
{
    Config::set('services.accurate.api_token', 'test-token');
    Config::set('services.accurate.signature_secret', 'test-secret');
    Config::set('services.accurate.timeout', 5);

    Http::fake([
        'https://zeus.accurate.id/accurate/api/receive-item/list.do*' => Http::response([
            's' => true,
            'd' => [
                [
                    'id' => 16202,
                    'number' => $pbNumber,
                ],
            ],
        ]),
        'https://zeus.accurate.id/accurate/api/receive-item/detail.do*' => Http::response([
            's' => true,
            'd' => [
                'id' => 16202,
                'number' => $pbNumber,
                'purchaseOrder' => ['number' => 'PO-004'],
                'vendor' => ['name' => 'PT Sumber Barang'],
                'transDateView' => '2026-07-09',
                'detailItem' => [
                    [
                        'id' => 9001,
                        'seq' => 1,
                        'item' => ['no' => 'ITM-1', 'name' => 'Barang Datang'],
                        'quantity' => 20,
                        'itemUnit' => ['name' => 'PCS'],
                    ],
                    [
                        'id' => 9002,
                        'seq' => 2,
                        'item' => ['no' => 'ITM-2', 'name' => 'Barang Belum Datang'],
                        'quantity' => 10,
                        'itemUnit' => ['name' => 'PCS'],
                    ],
                ],
            ],
        ]),
    ]);
}

function phaseFourPayload(User $hod): array
{
    return [
        'pb_number' => 'PB-004',
        'location_id' => phaseFourLocation()->id,
        'selected_user_id' => $hod->id,
        'specification_matches_po' => true,
        'notes' => null,
        'delivery_note_number' => 'SJ-004',
        'delivery_note_photo' => UploadedFile::fake()->image('sj.jpg'),
        'receiver_photo' => UploadedFile::fake()->image('receiver.jpg'),
        'items' => [
            [
                'accurate_detail_item_id' => '9001',
                'quality_status' => 'GOOD',
                'quantity_status' => 'MATCH',
                'received_qty' => null,
                'damaged_qty' => 0,
                'item_photo' => UploadedFile::fake()->image('item.jpg'),
            ],
        ],
    ];
}

it('creates inspection and draft round when warehouse saves the first checklist', function () {
    Storage::fake('public');
    $location = phaseFourLocation();
    $warehouse = phaseFourWarehouse($location);
    $hod = phaseFourHod($location);
    fakePhaseFourAccurate();

    $payload = phaseFourPayload($hod);
    $payload['location_id'] = $location->id;

    $response = $this->actingAs($warehouse)
        ->post(route('warehouse.rounds.store'), $payload);

    $round = GoodsReceiptRound::query()->with(['inspection', 'items', 'attachments'])->firstOrFail();

    $response->assertRedirect(route('warehouse.rounds.show', $round));

    expect(GoodsReceiptInspection::query()->count())->toBe(1)
        ->and($round->round_status)->toBe(RoundStatus::Draft)
        ->and($round->round_number)->toBe(1)
        ->and($round->selected_user_id)->toBe($hod->id)
        ->and($round->delivery_note_number)->toBe('SJ-004')
        ->and($round->inspection->accurate_raw_json['number'])->toBe('PB-004')
        ->and($round->items)->toHaveCount(1)
        ->and($round->items[0]->item_name)->toBe('Travo BHL250L')
        ->and((float) $round->items[0]->received_qty)->toBe(5.0)
        ->and($round->attachments)->toHaveCount(2);
});

it('stores multiple item and damage photos for one checked item', function () {
    Storage::fake('public');
    $location = phaseFourLocation();
    $warehouse = phaseFourWarehouse($location);
    $hod = phaseFourHod($location);
    fakePhaseFourAccurate();

    $payload = phaseFourPayload($hod);
    $payload['location_id'] = $location->id;
    $payload['items'][0]['quality_status'] = 'DAMAGED';
    $payload['items'][0]['quantity_status'] = 'NOT_MATCH';
    $payload['items'][0]['received_qty'] = 5;
    $payload['items'][0]['damaged_qty'] = 2;
    unset($payload['items'][0]['item_photo']);
    $payload['items'][0]['item_photos'] = [
        UploadedFile::fake()->image('item-1.jpg'),
        UploadedFile::fake()->image('item-2.jpg'),
    ];
    $payload['items'][0]['damage_photos'] = [
        UploadedFile::fake()->image('damage-1.jpg'),
        UploadedFile::fake()->image('damage-2.jpg'),
    ];

    $this->actingAs($warehouse)
        ->post(route('warehouse.rounds.store'), $payload)
        ->assertRedirect();

    $round = GoodsReceiptRound::query()->with('items.photos')->firstOrFail();

    expect($round->items[0]->photos)->toHaveCount(4);
});

it('creates a draft round only for checked arrived items', function () {
    Storage::fake('public');
    $location = phaseFourLocation();
    $warehouse = phaseFourWarehouse($location);
    $hod = phaseFourHod($location);
    fakePhaseFourAccurateWithTwoItems();

    $this->actingAs($warehouse)
        ->post(route('warehouse.rounds.store'), [
            'pb_number' => 'PB-004-TWO',
            'location_id' => $location->id,
            'selected_user_id' => $hod->id,
            'specification_matches_po' => true,
            'notes' => null,
            'delivery_note_number' => 'SJ-004-TWO',
            'delivery_note_photo' => UploadedFile::fake()->image('sj.jpg'),
            'receiver_photo' => UploadedFile::fake()->image('receiver.jpg'),
            'items' => [
                [
                    'is_checked' => true,
                    'accurate_detail_item_id' => '9001',
                    'quality_status' => 'GOOD',
                    'quantity_status' => 'MATCH',
                    'damaged_qty' => 0,
                    'item_photo' => UploadedFile::fake()->image('item.jpg'),
                ],
                [
                    'is_checked' => false,
                    'accurate_detail_item_id' => '9002',
                    'quality_status' => 'GOOD',
                    'quantity_status' => 'MATCH',
                    'damaged_qty' => 0,
                ],
            ],
        ])
        ->assertRedirect();

    $round = GoodsReceiptRound::query()->with('items')->firstOrFail();

    expect($round->items)->toHaveCount(1)
        ->and($round->items[0]->accurate_detail_item_id)->toBe('9001');
});

it('submits a complete draft round to HoD and locks further edits', function () {
    Storage::fake('public');
    $location = phaseFourLocation();
    $warehouse = phaseFourWarehouse($location);
    $hod = phaseFourHod($location);
    fakePhaseFourAccurate();

    $payload = phaseFourPayload($hod);
    $payload['location_id'] = $location->id;

    $this->actingAs($warehouse)->post(route('warehouse.rounds.store'), $payload);

    $round = GoodsReceiptRound::query()->firstOrFail();

    $this->actingAs($warehouse)
        ->post(route('warehouse.rounds.submit', $round))
        ->assertRedirect(route('warehouse.rounds.show', $round));

    $round->refresh();

    expect($round->round_status)->toBe(RoundStatus::WaitingHeadWarehouseSignature)
        ->and($round->submitted_at)->not->toBeNull();

    $this->actingAs($warehouse)
        ->put(route('warehouse.rounds.update', $round), [
            'notes' => 'late edit',
        ])
        ->assertForbidden();
});

it('opens the active round when warehouse searches the same PB again', function () {
    Storage::fake('public');
    $location = phaseFourLocation();
    $warehouse = phaseFourWarehouse($location);
    $hod = phaseFourHod($location);
    fakePhaseFourAccurate();

    $payload = phaseFourPayload($hod);
    $payload['location_id'] = $location->id;

    $this->actingAs($warehouse)->post(route('warehouse.rounds.store'), $payload);
    $round = GoodsReceiptRound::query()->firstOrFail();

    $this->actingAs($warehouse)
        ->post(route('warehouse.pb-search.store'), [
            'location_id' => $location->id,
            'pb_number' => 'PB-004',
        ])
        ->assertRedirect(route('warehouse.rounds.show', $round));

    expect(GoodsReceiptRound::query()->count())->toBe(1);
});

it('keeps accessible active rounds visible after an Accurate search error', function () {
    $location = phaseFourLocation();
    $otherLocation = Location::factory()->create(['name' => 'Tangerang']);
    $warehouse = phaseFourWarehouse($location);
    $hod = phaseFourHod($location);

    $inspection = GoodsReceiptInspection::query()->create([
        'location_id' => $location->id,
        'created_by' => $warehouse->id,
        'accurate_receive_item_id' => '16201',
        'pb_number' => 'PB-VISIBLE',
        'po_number' => 'PO-VISIBLE',
        'supplier_name' => 'PT Visible',
        'pb_date' => now()->toDateString(),
        'accurate_raw_json' => ['number' => 'PB-VISIBLE'],
        'pb_status' => 'OPEN_PARTIAL',
        'pdf_status' => 'NOT_READY',
    ]);

    $visibleRound = GoodsReceiptRound::query()->create([
        'inspection_id' => $inspection->id,
        'round_number' => 1,
        'created_by' => $warehouse->id,
        'selected_user_id' => $hod->id,
        'round_status' => RoundStatus::WaitingHeadWarehouseSignature,
        'specification_matches_po' => true,
        'submitted_at' => now(),
    ]);

    $otherInspection = GoodsReceiptInspection::query()->create([
        'location_id' => $otherLocation->id,
        'created_by' => $warehouse->id,
        'accurate_receive_item_id' => '16202',
        'pb_number' => 'PB-HIDDEN',
        'po_number' => 'PO-HIDDEN',
        'supplier_name' => 'PT Hidden',
        'pb_date' => now()->toDateString(),
        'accurate_raw_json' => ['number' => 'PB-HIDDEN'],
        'pb_status' => 'OPEN_PARTIAL',
        'pdf_status' => 'NOT_READY',
    ]);

    GoodsReceiptRound::query()->create([
        'inspection_id' => $otherInspection->id,
        'round_number' => 1,
        'created_by' => $warehouse->id,
        'round_status' => RoundStatus::Draft,
        'specification_matches_po' => true,
    ]);

    $this->actingAs($warehouse)
        ->get(route('dashboard.warehouse'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboards/warehouse')
            ->has('active_rounds', 1)
            ->where('active_rounds.0.id', $visibleRound->id)
            ->where('active_rounds.0.pb_number', 'PB-VISIBLE')
            ->where('active_rounds.0.status', 'WAITING_HEAD_WAREHOUSE_SIGNATURE')
        );

    Config::set('services.accurate.api_token', 'test-token');
    Config::set('services.accurate.signature_secret', 'test-secret');
    Http::fake([
        'https://zeus.accurate.id/accurate/api/receive-item/list.do*' => Http::response([
            's' => true,
            'd' => [],
        ]),
    ]);

    $this->actingAs($warehouse)
        ->followingRedirects()
        ->get(route('warehouse.pb-search.result', [
            'location_id' => $location->id,
            'pb_number' => 'PB-MISSING',
        ]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboards/warehouse')
            ->has('active_rounds', 1)
            ->where('active_round_count', 1)
            ->where('selected_location_id', $location->id)
            ->where('pb_number', 'PB-MISSING')
            ->where('errors.pb_number', 'Nomor Penerimaan Barang tidak ditemukan di Accurate.')
        );
});

it('shows signed partial rounds as partial and lets warehouse continue with a new round', function () {
    $location = phaseFourLocation();
    $warehouse = phaseFourWarehouse($location);
    $hod = phaseFourHod($location);
    fakePhaseFourAccurate('PB-PARTIAL-CONTINUE');

    $inspection = GoodsReceiptInspection::query()->create([
        'location_id' => $location->id,
        'created_by' => $warehouse->id,
        'accurate_receive_item_id' => '16201',
        'pb_number' => 'PB-PARTIAL-CONTINUE',
        'po_number' => 'PO-004',
        'supplier_name' => 'PT Partial',
        'pb_date' => now()->toDateString(),
        'accurate_raw_json' => ['number' => 'PB-PARTIAL-CONTINUE'],
        'pb_status' => 'OPEN_PARTIAL',
        'pdf_status' => 'NOT_READY',
    ]);

    GoodsReceiptRound::query()->create([
        'inspection_id' => $inspection->id,
        'round_number' => 1,
        'created_by' => $warehouse->id,
        'selected_user_id' => $hod->id,
        'round_status' => RoundStatus::Signed,
        'completion_result' => 'PARTIAL',
        'specification_matches_po' => true,
        'submitted_at' => now(),
        'user_signed_by' => $hod->id,
        'user_signed_at' => now(),
        'head_warehouse_signed_at' => now(),
    ]);

    $this->actingAs($warehouse)
        ->get(route('dashboard.warehouse'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboards/warehouse')
            ->where('active_rounds.0.status_label', 'Parsial')
            ->where('active_rounds.0.completion_result', 'PARTIAL')
        );

    $this->actingAs($warehouse)
        ->post(route('warehouse.pb-search.store'), [
            'location_id' => $location->id,
            'pb_number' => 'PB-PARTIAL-CONTINUE',
        ])
        ->assertRedirect(route('warehouse.pb-search.result', [
            'location_id' => $location->id,
            'pb_number' => 'PB-PARTIAL-CONTINUE',
        ]));
});

it('shows remaining quantity when warehouse continues a partial PB', function () {
    $location = phaseFourLocation();
    $warehouse = phaseFourWarehouse($location);
    $hod = phaseFourHod($location);
    fakePhaseFourAccurateWithTwoItems('PB-PARTIAL-REMAINING');

    $inspection = GoodsReceiptInspection::query()->create([
        'location_id' => $location->id,
        'created_by' => $warehouse->id,
        'accurate_receive_item_id' => '16202',
        'pb_number' => 'PB-PARTIAL-REMAINING',
        'po_number' => 'PO-004',
        'supplier_name' => 'PT Partial',
        'pb_date' => now()->toDateString(),
        'accurate_raw_json' => ['number' => 'PB-PARTIAL-REMAINING'],
        'pb_status' => 'OPEN_PARTIAL',
        'pdf_status' => 'NOT_READY',
    ]);

    $round = GoodsReceiptRound::query()->create([
        'inspection_id' => $inspection->id,
        'round_number' => 1,
        'created_by' => $warehouse->id,
        'selected_user_id' => $hod->id,
        'round_status' => RoundStatus::Signed,
        'completion_result' => 'PARTIAL',
        'specification_matches_po' => true,
        'submitted_at' => now(),
        'user_signed_by' => $hod->id,
        'user_signed_at' => now(),
        'head_warehouse_signed_at' => now(),
    ]);

    GoodsReceiptRoundItem::query()->create([
        'round_id' => $round->id,
        'line_number' => 1,
        'accurate_detail_item_id' => '9001',
        'item_code' => 'ITM-1',
        'item_name' => 'Barang Datang',
        'qty' => 20,
        'unit' => 'PCS',
        'quality_status' => 'GOOD',
        'quantity_status' => 'PARTIAL',
        'received_qty' => 15,
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
        'quality_status' => 'DAMAGED',
        'quantity_status' => 'MATCH',
        'received_qty' => 10,
        'damaged_qty' => 2,
    ]);

    $this->actingAs($warehouse)
        ->get(route('warehouse.pb-search.result', [
            'location_id' => $location->id,
            'pb_number' => 'PB-PARTIAL-REMAINING',
        ]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('warehouse/pb-search/result')
            ->where('result.items.0.qty_remaining', 5)
            ->where('result.items.1.qty_remaining', 2)
        );
});

it('shows one warehouse history row per PB and hides continue action when PB is complete', function () {
    $location = phaseFourLocation();
    $warehouse = phaseFourWarehouse($location);
    $hod = phaseFourHod($location);

    $inspection = GoodsReceiptInspection::query()->create([
        'location_id' => $location->id,
        'created_by' => $warehouse->id,
        'accurate_receive_item_id' => '16201',
        'pb_number' => 'PB-COMPLETE-HISTORY',
        'po_number' => 'PO-COMPLETE',
        'supplier_name' => 'PT Complete',
        'pb_date' => now()->toDateString(),
        'accurate_raw_json' => ['number' => 'PB-COMPLETE-HISTORY'],
        'pb_status' => 'PDF_GENERATED',
        'pdf_status' => 'GENERATED',
    ]);

    GoodsReceiptRound::query()->create([
        'inspection_id' => $inspection->id,
        'round_number' => 1,
        'created_by' => $warehouse->id,
        'selected_user_id' => $hod->id,
        'round_status' => RoundStatus::Signed,
        'completion_result' => 'PARTIAL',
        'specification_matches_po' => true,
        'submitted_at' => now()->subDay(),
        'user_signed_by' => $hod->id,
        'user_signed_at' => now()->subDay(),
        'head_warehouse_signed_at' => now()->subDay(),
    ]);

    $finalRound = GoodsReceiptRound::query()->create([
        'inspection_id' => $inspection->id,
        'round_number' => 2,
        'created_by' => $warehouse->id,
        'selected_user_id' => $hod->id,
        'round_status' => RoundStatus::Signed,
        'completion_result' => 'COMPLETE',
        'specification_matches_po' => true,
        'submitted_at' => now(),
        'user_signed_by' => $hod->id,
        'user_signed_at' => now(),
        'head_warehouse_signed_at' => now(),
    ]);

    $this->actingAs($warehouse)
        ->get(route('dashboard.warehouse'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboards/warehouse')
            ->has('active_rounds', 1)
            ->where('active_rounds.0.id', $finalRound->id)
            ->where('active_rounds.0.pb_number', 'PB-COMPLETE-HISTORY')
            ->where('active_rounds.0.status_label', 'Selesai')
            ->where('active_rounds.0.continue_url', null)
        );
});

it('keeps validation errors on the PB result page when draft form is incomplete', function () {
    $location = phaseFourLocation();
    $warehouse = phaseFourWarehouse($location);
    $hod = phaseFourHod($location);
    fakePhaseFourAccurate();

    $resultUrl = route('warehouse.pb-search.result', [
        'location_id' => $location->id,
        'pb_number' => 'PB-004',
    ]);

    $this->actingAs($warehouse)
        ->from($resultUrl)
        ->post(route('warehouse.rounds.store'), [
            'pb_number' => 'PB-004',
            'location_id' => $location->id,
            'selected_user_id' => $hod->id,
            'specification_matches_po' => true,
            'items' => [
                [
                    'accurate_detail_item_id' => '9001',
                    'quality_status' => 'GOOD',
                    'quantity_status' => 'MATCH',
                    'damaged_qty' => 0,
                ],
            ],
        ])
        ->assertRedirect($resultUrl)
        ->assertSessionHasErrors([
            'delivery_note_number' => 'Nomor surat jalan wajib diisi.',
            'delivery_note_photo' => 'Foto surat jalan wajib diupload.',
            'receiver_photo' => 'Foto penerima wajib diupload.',
            'items.0.item_photo' => 'Foto item wajib diupload.',
        ]);
});

it('rejects received quantity greater than Accurate quantity', function () {
    Storage::fake('public');
    $location = phaseFourLocation();
    $warehouse = phaseFourWarehouse($location);
    $hod = phaseFourHod($location);
    fakePhaseFourAccurate();

    $payload = phaseFourPayload($hod);
    $payload['location_id'] = $location->id;
    $payload['items'][0]['quantity_status'] = 'NOT_MATCH';
    $payload['items'][0]['received_qty'] = 6;

    $this->actingAs($warehouse)
        ->post(route('warehouse.rounds.store'), $payload)
        ->assertSessionHasErrors('pb_number');

    expect(GoodsReceiptRound::query()->count())->toBe(0);
});

it('rejects damaged quantity greater than received quantity', function () {
    Storage::fake('public');
    $location = phaseFourLocation();
    $warehouse = phaseFourWarehouse($location);
    $hod = phaseFourHod($location);
    fakePhaseFourAccurate();

    $payload = phaseFourPayload($hod);
    $payload['location_id'] = $location->id;
    $payload['items'][0]['quality_status'] = 'DAMAGED';
    $payload['items'][0]['damaged_qty'] = 6;
    $payload['items'][0]['damage_photo'] = UploadedFile::fake()->image('damage.jpg');

    $this->actingAs($warehouse)
        ->post(route('warehouse.rounds.store'), $payload)
        ->assertSessionHasErrors('pb_number');

    expect(GoodsReceiptRound::query()->count())->toBe(0);
});
