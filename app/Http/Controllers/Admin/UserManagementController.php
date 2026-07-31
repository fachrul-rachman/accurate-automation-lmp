<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Location;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

final class UserManagementController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogs) {}

    public function index(): Response
    {
        return Inertia::render('admin/users/index', [
            'users' => User::query()
                ->with('locations:id,name')
                ->latest()
                ->get(['id', 'name', 'email', 'role', 'is_active']),
            'locations' => Location::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'roles' => array_map(fn (UserRole $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ], UserRole::cases()),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $locationIds = $data['location_ids'] ?? [];

        unset($data['location_ids']);

        $data['password'] = Hash::make((string) $data['password']);

        $user = User::query()->create($data);
        $user->locations()->sync($locationIds);

        $this->auditLogs->write(
            'USER_CREATED',
            $request->user(),
            $user,
            "User {$user->name} dibuat.",
            newValues: [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'location_ids' => array_values($locationIds),
                'is_active' => $user->is_active,
            ],
            request: $request,
        );

        if ($user->role === UserRole::User) {
            $this->auditLogs->write(
                'USER_LOCATION_ACCESS_UPDATED',
                $request->user(),
                $user,
                "Akses lokasi user {$user->name} diatur.",
                newValues: ['location_ids' => array_values($locationIds)],
                request: $request,
            );
        }

        return redirect()->route('admin.users.index');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $oldValues = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'location_ids' => $user->locations()->pluck('locations.id')->all(),
            'is_active' => $user->is_active,
        ];

        $data = $request->validated();
        $locationIds = $data['location_ids'] ?? [];

        unset($data['location_ids']);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make((string) $data['password']);
        }

        $user->update($data);
        $user->locations()->sync($locationIds);
        $user->refresh();

        $newValues = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'location_ids' => array_values($locationIds),
            'is_active' => $user->is_active,
        ];

        $this->auditLogs->write(
            'USER_UPDATED',
            $request->user(),
            $user,
            "User {$user->name} diperbarui.",
            oldValues: $oldValues,
            newValues: $newValues,
            request: $request,
        );

        if ($oldValues['location_ids'] !== $newValues['location_ids'] && $user->role === UserRole::User) {
            $this->auditLogs->write(
                'USER_LOCATION_ACCESS_UPDATED',
                $request->user(),
                $user,
                "Akses lokasi user {$user->name} diperbarui.",
                oldValues: ['location_ids' => $oldValues['location_ids']],
                newValues: ['location_ids' => $newValues['location_ids']],
                request: $request,
            );
        }

        return redirect()->route('admin.users.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->is(auth()->user()), 403);

        $user->delete();

        return redirect()->route('admin.users.index');
    }
}
