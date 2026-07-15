<?php

use App\Models\AuditLog;
use App\Models\GoodsReceiptInspection;
use App\Models\GoodsReceiptRound;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function phaseElevenAdmin(): User
{
    return User::factory()->create([
        'role' => 'ADMIN',
        'is_active' => true,
    ]);
}

function phaseElevenLocation(): Location
{
    return Location::factory()->create([
        'name' => 'Karawang',
        'accurate_host_url' => 'https://zeus.accurate.id',
        'is_active' => true,
    ]);
}

function phaseElevenUser(string $role, ?Location $location = null): User
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

function fakePhaseElevenAccurate(string $pbNumber = 'PB-011'): void
{
    Config::set('services.accurate.api_token', 'test-token');
    Config::set('services.accurate.signature_secret', 'test-secret');
    Config::set('services.accurate.timeout', 5);

    Http::fake([
        'https://zeus.accurate.id/accurate/api/receive-item/list.do*' => Http::response([
            's' => true,
            'd' => [
                [
                    'id' => 17201,
                    'number' => $pbNumber,
                ],
            ],
        ]),
        'https://zeus.accurate.id/accurate/api/receive-item/detail.do*' => Http::response([
            's' => true,
            'd' => [
                'id' => 17201,
                'number' => $pbNumber,
                'purchaseOrder' => ['number' => 'PO-011'],
                'vendor' => ['name' => 'PT Testing'],
                'transDateView' => '2026-07-09',
                'detailItem' => [
                    [
                        'id' => 9901,
                        'seq' => 1,
                        'item' => ['no' => 'ITM-11', 'name' => 'Barang Test'],
                        'quantity' => 3,
                        'itemUnit' => ['name' => 'PCS'],
                    ],
                ],
            ],
        ]),
    ]);
}

it('writes audit logs for admin user and location management', function () {
    $admin = phaseElevenAdmin();
    $locations = Location::factory()->count(2)->create();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'User Karawang',
            'email' => 'user-karawang@example.test',
            'password' => 'password',
            'role' => 'HOD',
            'location_ids' => [$locations[0]->id],
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.users.index'));

    $managedUser = User::query()->where('email', 'user-karawang@example.test')->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.users.update', $managedUser), [
            'name' => 'User Tangerang',
            'email' => 'user-tangerang@example.test',
            'role' => 'HOD',
            'location_ids' => [$locations[1]->id],
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.users.index'));

    $this->actingAs($admin)
        ->post(route('admin.locations.store'), [
            'name' => 'Jakarta',
            'accurate_host_url' => 'https://iris.accurate.id',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.locations.index'));

    $managedLocation = Location::query()->where('name', 'Jakarta')->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.locations.update', $managedLocation), [
            'name' => 'Jakarta DC',
            'accurate_host_url' => 'https://iris.accurate.id',
            'is_active' => false,
        ])
        ->assertRedirect(route('admin.locations.index'));

    $events = AuditLog::query()->pluck('event')->all();

    expect($events)
        ->toContain('USER_CREATED')
        ->toContain('USER_UPDATED')
        ->toContain('HOD_MAPPING_UPDATED')
        ->toContain('LOCATION_CREATED')
        ->toContain('LOCATION_UPDATED');
});

it('writes audit logs for PB import and draft update', function () {
    Storage::fake('public');
    $location = phaseElevenLocation();
    $warehouse = phaseElevenUser('WAREHOUSE', $location);
    $user = phaseElevenUser('HOD', $location);
    fakePhaseElevenAccurate();

    $this->actingAs($warehouse)
        ->post(route('warehouse.rounds.store'), [
            'pb_number' => 'PB-011',
            'location_id' => $location->id,
            'selected_hod_id' => $user->id,
            'specification_matches_po' => true,
            'notes' => null,
            'delivery_note_photo' => UploadedFile::fake()->image('sj.jpg'),
            'receiver_photo' => UploadedFile::fake()->image('receiver.jpg'),
            'items' => [
                [
                    'accurate_detail_item_id' => '9901',
                    'quality_status' => 'GOOD',
                    'quantity_status' => 'MATCH',
                    'received_qty' => null,
                    'damaged_qty' => 0,
                    'item_photo' => UploadedFile::fake()->image('item.jpg'),
                ],
            ],
        ]);

    $inspection = GoodsReceiptInspection::query()->firstOrFail();
    $round = GoodsReceiptRound::query()->firstOrFail();

    $this->actingAs($warehouse)
        ->put(route('warehouse.rounds.update', $round), [
            'notes' => 'Catatan draft diperbarui',
        ])
        ->assertRedirect(route('warehouse.rounds.show', $round));

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'PB_IMPORTED',
        'auditable_type' => GoodsReceiptInspection::class,
        'auditable_id' => (string) $inspection->id,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'ROUND_UPDATED',
        'auditable_type' => GoodsReceiptRound::class,
        'auditable_id' => (string) $round->id,
    ]);
});
