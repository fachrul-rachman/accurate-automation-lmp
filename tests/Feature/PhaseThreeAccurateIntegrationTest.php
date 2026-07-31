<?php

use App\Models\GoodsReceiptInspection;
use App\Models\Location;
use App\Models\User;
use App\Services\AccurateReceiveItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function warehouseWithLocations(Location ...$locations): User
{
    $warehouse = User::factory()->create([
        'role' => 'WAREHOUSE',
        'is_active' => true,
    ]);

    $warehouse->locations()->sync(collect($locations)->pluck('id')->all());

    return $warehouse;
}

function configureAccurateCredential(): void
{
    Config::set('services.accurate.locations', []);
    Config::set('services.accurate.api_token', 'test-token');
    Config::set('services.accurate.signature_secret', 'test-secret');
    Config::set('services.accurate.timeout', 5);
}

function locationWithAccurateHost(string $name, string $host): Location
{
    return Location::factory()->create([
        'name' => $name,
        'accurate_host_url' => $host,
        'is_active' => true,
    ]);
}

function fakeLocationAccurateSuccess(string $host, string $pbNumber = 'PB-001'): void
{
    Http::fake([
        "{$host}/accurate/api/receive-item/list.do*" => Http::response([
            's' => true,
            'd' => [
                [
                    'id' => 16201,
                    'number' => $pbNumber,
                ],
            ],
        ]),
        "{$host}/accurate/api/receive-item/detail.do*" => Http::response([
            's' => true,
            'd' => [
                'id' => 16201,
                'number' => $pbNumber,
                'poNumber' => 'PO-77',
                'vendor' => ['name' => 'PT Sumber Barang'],
                'transDateView' => '2026-07-09',
                'detailItem' => [
                    [
                        'id' => 9001,
                        'item' => ['no' => 'ITM-1', 'name' => 'Item One'],
                        'detailName' => 'Item One Detail',
                        'quantity' => 5,
                        'itemUnit' => ['name' => 'PCS'],
                    ],
                ],
            ],
        ]),
    ]);
}

it('searches PB against the selected user location host without storing a snapshot', function () {
    configureAccurateCredential();
    $karawang = locationWithAccurateHost('Karawang', 'https://zeus.accurate.id');
    fakeLocationAccurateSuccess('https://zeus.accurate.id');

    $result = app(AccurateReceiveItemService::class)->findReceiveItemDetail($karawang, 'PB-001');

    expect($result['number'])->toBe('PB-001')
        ->and($result['po_number'])->toBe('PO-77')
        ->and($result['supplier_name'])->toBe('PT Sumber Barang')
        ->and($result['items'][0]['item_no'])->toBe('ITM-1')
        ->and(GoodsReceiptInspection::query()->count())->toBe(0);

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://zeus.accurate.id/accurate/api/receive-item/')
        && $request->hasHeader('Authorization', 'Bearer test-token')
        && $request->hasHeader('X-Api-Timestamp')
        && $request->hasHeader('X-Api-Signature'));
});

it('uses location specific Accurate credentials for each location', function () {
    Config::set('services.accurate.locations.karawang.api_token', 'karawang-token');
    Config::set('services.accurate.locations.karawang.signature_secret', 'karawang-secret');
    Config::set('services.accurate.locations.tangerang.api_token', 'tangerang-token');
    Config::set('services.accurate.locations.tangerang.signature_secret', 'tangerang-secret');
    Config::set('services.accurate.timeout', 5);

    $karawang = locationWithAccurateHost('Karawang', 'https://zeus.accurate.id');
    $tangerang = locationWithAccurateHost('Tangerang', 'https://iris.accurate.id');
    fakeLocationAccurateSuccess('https://zeus.accurate.id', 'PB-KRW');
    fakeLocationAccurateSuccess('https://iris.accurate.id', 'PB-TGR');

    app(AccurateReceiveItemService::class)->findReceiveItemDetail($karawang, 'PB-KRW');
    app(AccurateReceiveItemService::class)->findReceiveItemDetail($tangerang, 'PB-TGR');

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://zeus.accurate.id/')
        && $request->hasHeader('Authorization', 'Bearer karawang-token'));
    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://iris.accurate.id/')
        && $request->hasHeader('Authorization', 'Bearer tangerang-token'));
});

it('shows a clear error when a location specific Accurate credential is missing', function () {
    Config::set('services.accurate.api_token', null);
    Config::set('services.accurate.signature_secret', null);
    Config::set('services.accurate.locations.karawang.api_token', null);
    Config::set('services.accurate.locations.karawang.signature_secret', null);

    $karawang = locationWithAccurateHost('Karawang', 'https://zeus.accurate.id');

    app(AccurateReceiveItemService::class)->findReceiveItemDetail($karawang, 'PB-001');
})->throws(RuntimeException::class, 'Konfigurasi Accurate untuk lokasi Karawang belum lengkap.');

it('never calls the account host discovery endpoint during PB search', function () {
    configureAccurateCredential();
    $karawang = locationWithAccurateHost('Karawang', 'https://zeus.accurate.id');
    fakeLocationAccurateSuccess('https://zeus.accurate.id');

    app(AccurateReceiveItemService::class)->findReceiveItemDetail($karawang, 'PB-001');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'account.accurate.id'));
});

it('blocks warehouse from searching PB in a location they cannot access', function () {
    configureAccurateCredential();
    $karawang = locationWithAccurateHost('Karawang', 'https://zeus.accurate.id');
    $tangerang = locationWithAccurateHost('Tangerang', 'https://iris.accurate.id');
    $warehouse = warehouseWithLocations($karawang);

    $this->actingAs($warehouse)
        ->post(route('warehouse.pb-search.store'), [
            'location_id' => $tangerang->id,
            'pb_number' => 'PB-001',
        ])
        ->assertSessionHasErrors('location_id');

    Http::assertNothingSent();
});

it('lets warehouse search PB from an allowed location and shows read only results', function () {
    configureAccurateCredential();
    $karawang = locationWithAccurateHost('Karawang', 'https://zeus.accurate.id');
    fakeLocationAccurateSuccess('https://zeus.accurate.id');
    $warehouse = warehouseWithLocations($karawang);

    $response = $this->actingAs($warehouse)
        ->post(route('warehouse.pb-search.store'), [
            'location_id' => $karawang->id,
            'pb_number' => 'PB-001',
        ]);

    $response->assertRedirect(route('warehouse.pb-search.result', [
        'location_id' => $karawang->id,
        'pb_number' => 'PB-001',
    ]));

    $this->actingAs($warehouse)
        ->get(route('warehouse.pb-search.result', [
            'location_id' => $karawang->id,
            'pb_number' => 'PB-001',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('warehouse/pb-search/result')
            ->where('location.name', 'Karawang')
            ->where('result.number', 'PB-001')
            ->where('result.items.0.item_name', 'Item One Detail'));

    expect(GoodsReceiptInspection::query()->count())->toBe(0);
});

it('redirects direct GET access to PB search back to warehouse dashboard', function () {
    $karawang = locationWithAccurateHost('Karawang', 'https://zeus.accurate.id');
    $warehouse = warehouseWithLocations($karawang);

    $this->actingAs($warehouse)
        ->get('/warehouse/pb-search')
        ->assertRedirect(route('dashboard.warehouse'));
});

it('uses Accurate detailName as the displayed item name when item name is generic', function () {
    configureAccurateCredential();
    $karawang = locationWithAccurateHost('Karawang', 'https://zeus.accurate.id');
    Http::fake([
        'https://zeus.accurate.id/accurate/api/receive-item/list.do*' => Http::response([
            's' => true,
            'd' => [
                [
                    'id' => 16201,
                    'number' => 'PB-TRAVO',
                ],
            ],
        ]),
        'https://zeus.accurate.id/accurate/api/receive-item/detail.do*' => Http::response([
            's' => true,
            'd' => [
                'id' => 16201,
                'number' => 'PB-TRAVO',
                'detailItem' => [
                    [
                        'id' => 9001,
                        'item' => ['no' => 'UMUM-PCS', 'name' => 'Umum-PCS'],
                        'detailName' => 'Travo ( balast ) merk Phillip seri BHL250L-200..9137101004',
                        'quantity' => 1,
                        'itemUnit' => ['name' => 'PCS'],
                    ],
                ],
            ],
        ]),
    ]);

    $result = app(AccurateReceiveItemService::class)->findReceiveItemDetail($karawang, 'PB-TRAVO');

    expect($result['items'][0]['item_name'])->toBe('Travo ( balast ) merk Phillip seri BHL250L-200..9137101004');
});

it('uses Accurate purchase order number as No Pesanan when poNumber is absent', function () {
    configureAccurateCredential();
    $karawang = locationWithAccurateHost('Karawang', 'https://zeus.accurate.id');
    Http::fake([
        'https://zeus.accurate.id/accurate/api/receive-item/list.do*' => Http::response([
            's' => true,
            'd' => [
                [
                    'id' => 16201,
                    'number' => 'PB-PO',
                ],
            ],
        ]),
        'https://zeus.accurate.id/accurate/api/receive-item/detail.do*' => Http::response([
            's' => true,
            'd' => [
                'id' => 16201,
                'number' => 'PB-PO',
                'purchaseOrder' => ['number' => 'PO-KRW-001'],
                'detailItem' => [
                    [
                        'id' => 9001,
                        'detailName' => 'Cable Tray',
                        'quantity' => 1,
                    ],
                ],
            ],
        ]),
    ]);

    $result = app(AccurateReceiveItemService::class)->findReceiveItemDetail($karawang, 'PB-PO');

    expect($result['po_number'])->toBe('PO-KRW-001');
});

it('uses item level purchase order number when receive item header has no PO number', function () {
    configureAccurateCredential();
    $karawang = locationWithAccurateHost('Karawang', 'https://zeus.accurate.id');
    Http::fake([
        'https://zeus.accurate.id/accurate/api/receive-item/list.do*' => Http::response([
            's' => true,
            'd' => [
                [
                    'id' => 16201,
                    'number' => 'PB-ITEM-PO',
                ],
            ],
        ]),
        'https://zeus.accurate.id/accurate/api/receive-item/detail.do*' => Http::response([
            's' => true,
            'd' => [
                'id' => 16201,
                'number' => 'PB-ITEM-PO',
                'detailItem' => [
                    [
                        'id' => 9001,
                        'detailName' => 'Cable Tray',
                        'purchaseOrder' => ['number' => 'PO-ITEM-001'],
                        'quantity' => 1,
                    ],
                ],
            ],
        ]),
    ]);

    $result = app(AccurateReceiveItemService::class)->findReceiveItemDetail($karawang, 'PB-ITEM-PO');

    expect($result['po_number'])->toBe('PO-ITEM-001')
        ->and($result['items'][0]['po_number'])->toBe('PO-ITEM-001');
});

it('passes accessible locations to the warehouse dashboard', function () {
    $karawang = locationWithAccurateHost('Karawang', 'https://zeus.accurate.id');
    $tangerang = locationWithAccurateHost('Tangerang', 'https://iris.accurate.id');
    $warehouse = warehouseWithLocations($karawang, $tangerang);

    $this->actingAs($warehouse)
        ->get(route('dashboard.warehouse'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboards/warehouse')
            ->where('locations.0.name', 'Karawang')
            ->where('locations.1.name', 'Tangerang'));
});
