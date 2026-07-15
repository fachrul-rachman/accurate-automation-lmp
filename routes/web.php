<?php

use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\MonitoringController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HeadWarehouse\RoundController as HeadWarehouseRoundController;
use App\Http\Controllers\Hod\RoundController as HodRoundController;
use App\Http\Controllers\PdfExportController;
use App\Http\Controllers\RoundFileController;
use App\Http\Controllers\Warehouse\InspectionController;
use App\Http\Controllers\Warehouse\PbSearchController;
use App\Http\Controllers\Warehouse\RoundController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::get('/manifest.webmanifest', function () {
    $contents = file_get_contents(public_path('manifest.webmanifest'));

    abort_if($contents === false, 404);

    return response($contents, 200, ['Content-Type' => 'application/manifest+json']);
});

Route::get('/service-worker.js', function () {
    $contents = file_get_contents(public_path('service-worker.js'));

    abort_if($contents === false, 404);

    return response($contents, 200, ['Content-Type' => 'application/javascript']);
});

Route::get('/pwa-{size}.png', function (string $size) {
    abort_unless(in_array($size, ['192', '512'], true), 404);

    $contents = file_get_contents(public_path("pwa-{$size}.png"));

    abort_if($contents === false, 404);

    return response($contents, 200, ['Content-Type' => 'image/png']);
})->where('size', '192|512');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/offline', fn () => inertia('offline'))->name('offline');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'redirect'])->name('dashboard');
    Route::get('/dashboard/warehouse', [DashboardController::class, 'warehouse'])->name('dashboard.warehouse');
    Route::get('/dashboard/hod', [DashboardController::class, 'hod'])->name('dashboard.hod');
    Route::get('/dashboard/head-warehouse', [DashboardController::class, 'headWarehouse'])->name('dashboard.head-warehouse');
    Route::get('/dashboard/admin', [DashboardController::class, 'admin'])->name('dashboard.admin');

    Route::get('/files/round-attachments/{attachment}', [RoundFileController::class, 'attachment'])->name('files.round-attachments.show');
    Route::get('/files/round-item-photos/{photo}', [RoundFileController::class, 'itemPhoto'])->name('files.round-item-photos.show');
    Route::get('/files/pdf-exports/{pdfExport}', [PdfExportController::class, 'show'])->name('files.pdf-exports.show');

    Route::prefix('warehouse')->name('warehouse.')->group(function (): void {
        Route::get('/pb-search', [PbSearchController::class, 'index'])->name('pb-search.index');
        Route::get('/pb-search/result', [PbSearchController::class, 'result'])->name('pb-search.result');
        Route::post('/pb-search', [PbSearchController::class, 'store'])->name('pb-search.store');
        Route::get('/inspections/{inspection}', [InspectionController::class, 'show'])->name('inspections.show');
        Route::post('/rounds', [RoundController::class, 'store'])->name('rounds.store');
        Route::get('/rounds/{round}', [RoundController::class, 'show'])->name('rounds.show');
        Route::put('/rounds/{round}', [RoundController::class, 'update'])->name('rounds.update');
        Route::post('/rounds/{round}/submit', [RoundController::class, 'submit'])->name('rounds.submit');
    });

    Route::prefix('hod')->name('hod.')->group(function (): void {
        Route::get('/rounds/{round}', [HodRoundController::class, 'show'])->name('rounds.show');
        Route::post('/rounds/{round}/sign', [HodRoundController::class, 'sign'])->name('rounds.sign');
    });

    Route::prefix('head-warehouse')->name('head-warehouse.')->group(function (): void {
        Route::get('/rounds/{round}', [HeadWarehouseRoundController::class, 'show'])->name('rounds.show');
        Route::post('/rounds/{round}/sign', [HeadWarehouseRoundController::class, 'sign'])->name('rounds.sign');
    });

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');

        Route::get('/locations', [LocationController::class, 'index'])->name('locations.index');
        Route::post('/locations', [LocationController::class, 'store'])->name('locations.store');
        Route::put('/locations/{location}', [LocationController::class, 'update'])->name('locations.update');

        Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');
        Route::get('/monitoring/{inspection}', [MonitoringController::class, 'show'])->name('monitoring.show');
    });
});
