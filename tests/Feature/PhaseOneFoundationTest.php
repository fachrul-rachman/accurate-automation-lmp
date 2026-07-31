<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('redirects guests from dashboard to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('allows active users to log in', function () {
    $user = User::factory()->create([
        'email' => 'warehouse@example.test',
        'password' => 'password',
        'role' => 'WAREHOUSE',
        'is_active' => true,
    ]);

    $this->post(route('login.store'), [
        'email' => 'warehouse@example.test',
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('allows authenticated users to log out', function () {
    $user = User::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});

it('redirects authenticated users to the dashboard for their role', function (string $role, string $routeName, string $component) {
    $user = User::factory()->create([
        'role' => $role,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route($routeName));

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'warehouse' => ['WAREHOUSE', 'dashboard.warehouse', 'dashboards/warehouse'],
    'hod' => ['USER', 'dashboard.user', 'dashboards/user'],
    'head warehouse' => ['HEAD_WAREHOUSE', 'dashboard.head-warehouse', 'dashboards/head-warehouse'],
    'admin' => ['ADMIN', 'dashboard.admin', 'dashboards/admin'],
]);

it('exposes pwa manifest and service worker assets', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('rel="manifest"', false)
        ->assertSee('/manifest.webmanifest', false)
        ->assertSee('/service-worker.js', false);

    $this->get('/manifest.webmanifest')
        ->assertOk()
        ->assertJsonPath('name', 'Accurate Check');

    $this->get('/service-worker.js')
        ->assertOk()
        ->assertSee('accurate-check-static');

    $this->get('/offline')->assertOk();
});
