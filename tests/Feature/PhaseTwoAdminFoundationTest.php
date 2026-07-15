<?php

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function adminUser(): User
{
    return User::factory()->create([
        'role' => 'ADMIN',
        'is_active' => true,
    ]);
}

it('allows admin to open admin foundation pages', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/users/index'));

    $this->actingAs($admin)
        ->get(route('admin.locations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/locations/index')
            ->missing('locations.0.code'));

    $this->actingAs($admin)
        ->get(route('admin.monitoring.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/monitoring/index'));

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('roles.0.value', 'WAREHOUSE')
            ->where('roles.0.label', 'Warehouse'));
});

it('allows admin to create and update users', function () {
    $admin = adminUser();
    $locations = Location::factory()->count(3)->create();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Warehouse User',
            'email' => 'warehouse@example.test',
            'password' => 'password',
            'role' => 'WAREHOUSE',
            'location_ids' => [$locations[0]->id, $locations[1]->id],
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.users.index'));

    $user = User::query()->where('email', 'warehouse@example.test')->firstOrFail();

    expect($user->role->value)->toBe('WAREHOUSE')
        ->and($user->locations()->pluck('locations.id')->all())->toBe([$locations[0]->id, $locations[1]->id])
        ->and($user->is_active)->toBeTrue();

    $this->actingAs($admin)
        ->put(route('admin.users.update', $user), [
            'name' => 'Inactive HoD',
            'email' => 'hod@example.test',
            'role' => 'HOD',
            'location_ids' => [$locations[2]->id],
            'is_active' => false,
        ])
        ->assertRedirect(route('admin.users.index'));

    $user->refresh();

    expect($user->name)->toBe('Inactive HoD')
        ->and($user->email)->toBe('hod@example.test')
        ->and($user->role->value)->toBe('HOD')
        ->and($user->locations()->pluck('locations.id')->all())->toBe([$locations[2]->id])
        ->and($user->is_active)->toBeFalse();
});

it('allows admin to delete another user', function () {
    $admin = adminUser();
    $user = User::factory()->create(['role' => 'WAREHOUSE']);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $user))
        ->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

it('prevents admin from deleting their own account', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $admin))
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

it('rejects incomplete email domains when admin manages users', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'HoD Karawang',
            'email' => 'hod@karawang',
            'password' => 'password',
            'role' => 'HOD',
            'location_ids' => [],
            'is_active' => true,
        ])
        ->assertSessionHasErrors('email');
});

it('allows admin to create and update locations', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.locations.store'), [
            'name' => 'Jakarta Warehouse',
            'accurate_host_url' => 'https://zeus.accurate.id',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.locations.index'));

    $location = Location::query()->where('name', 'Jakarta Warehouse')->firstOrFail();

    expect($location->name)->toBe('Jakarta Warehouse')
        ->and($location->accurate_host_url)->toBe('https://zeus.accurate.id')
        ->and($location->is_active)->toBeTrue();

    $this->actingAs($admin)
        ->put(route('admin.locations.update', $location), [
            'name' => 'Jakarta DC',
            'accurate_host_url' => 'https://iris.accurate.id',
            'is_active' => false,
        ])
        ->assertRedirect(route('admin.locations.index'));

    $location->refresh();

    expect($location->name)->toBe('Jakarta DC')
        ->and($location->accurate_host_url)->toBe('https://iris.accurate.id')
        ->and($location->is_active)->toBeFalse();
});

it('uses hod user location as the MVP hod mapping', function () {
    $admin = adminUser();
    $locations = Location::factory()->count(2)->create();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'HoD Jakarta',
            'email' => 'hod-jakarta@example.test',
            'password' => 'password',
            'role' => 'HOD',
            'location_ids' => [$locations[0]->id, $locations[1]->id],
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.users.index'));

    $hod = User::query()->where('email', 'hod-jakarta@example.test')->firstOrFail();

    expect($hod->role->value)->toBe('HOD')
        ->and($hod->locations()->pluck('locations.id')->all())->toBe([$locations[0]->id, $locations[1]->id]);
});

it('blocks non admin users from admin foundation endpoints', function () {
    $warehouse = User::factory()->create(['role' => 'WAREHOUSE', 'is_active' => true]);

    $this->actingAs($warehouse)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($warehouse)->post(route('admin.locations.store'), [
        'name' => 'Blocked',
        'accurate_host_url' => 'https://zeus.accurate.id',
        'is_active' => true,
    ])->assertForbidden();
});
