<?php

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('serves an installable PWA manifest with required icons', function () {
    $response = $this->get('/manifest.webmanifest')->assertOk();
    $manifest = $response->json();
    $icons = collect($manifest['icons']);

    expect($manifest['name'])->toBe('Accurate Check')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['start_url'])->toBe('/');

    expect($icons->contains(fn (array $icon): bool => $icon['src'] === '/pwa-192.png' && $icon['sizes'] === '192x192'))->toBeTrue()
        ->and($icons->contains(fn (array $icon): bool => $icon['src'] === '/pwa-512.png' && $icon['sizes'] === '512x512'))->toBeTrue();

    $this->get('/pwa-192.png')->assertOk()->assertHeader('content-type', 'image/png');
    $this->get('/pwa-512.png')->assertOk()->assertHeader('content-type', 'image/png');
});

it('keeps service worker asset cache away from business data routes', function () {
    $serviceWorker = (string) file_get_contents(public_path('service-worker.js'));

    expect($serviceWorker)->not->toContain("    '/',")
        ->and($serviceWorker)->toContain('BUSINESS_PATH_PREFIXES')
        ->and($serviceWorker)->toContain('/warehouse')
        ->and($serviceWorker)->toContain('/hod')
        ->and($serviceWorker)->toContain('/head-warehouse')
        ->and($serviceWorker)->toContain('/admin')
        ->and($serviceWorker)->toContain('/files')
        ->and($serviceWorker)->toContain("request.mode === 'navigate'")
        ->and($serviceWorker)->toContain("caches.match('/offline')");
});

it('marks authenticated business pages and file downloads as no-store', function () {
    $location = Location::factory()->create(['is_active' => true]);
    $warehouse = User::factory()->create(['role' => 'WAREHOUSE', 'is_active' => true]);
    $warehouse->locations()->sync([$location->id]);

    $this->actingAs($warehouse)
        ->get(route('dashboard.warehouse'))
        ->assertOk()
        ->assertHeader('cache-control', 'no-store, private');

    $manifestResponse = $this->get('/manifest.webmanifest')->assertOk();

    expect($manifestResponse->headers->get('cache-control'))->not->toBe('no-store, private');
});
