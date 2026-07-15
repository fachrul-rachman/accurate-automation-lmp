<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LocationRequest;
use App\Models\Location;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class LocationController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogs) {}

    public function index(): Response
    {
        return Inertia::render('admin/locations/index', [
            'locations' => Location::query()
                ->latest()
                ->get(['id', 'name', 'accurate_host_url', 'is_active']),
        ]);
    }

    public function store(LocationRequest $request): RedirectResponse
    {
        $location = Location::query()->create($request->validated());

        $this->auditLogs->write(
            'LOCATION_CREATED',
            $request->user(),
            $location,
            "Lokasi {$location->name} dibuat.",
            newValues: $location->only(['name', 'accurate_host_url', 'is_active']),
            request: $request,
        );

        return redirect()->route('admin.locations.index');
    }

    public function update(LocationRequest $request, Location $location): RedirectResponse
    {
        $oldValues = $location->only(['name', 'accurate_host_url', 'is_active']);

        $location->update($request->validated());
        $location->refresh();

        $this->auditLogs->write(
            'LOCATION_UPDATED',
            $request->user(),
            $location,
            "Lokasi {$location->name} diperbarui.",
            oldValues: $oldValues,
            newValues: $location->only(['name', 'accurate_host_url', 'is_active']),
            request: $request,
        );

        return redirect()->route('admin.locations.index');
    }
}
