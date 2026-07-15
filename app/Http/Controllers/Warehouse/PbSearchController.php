<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\SearchPbRequest;
use App\Models\GoodsReceiptInspection;
use App\Models\GoodsReceiptRound;
use App\Services\AccurateReceiveItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class PbSearchController extends Controller
{
    public function __construct(private readonly AccurateReceiveItemService $accurateReceiveItems) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('dashboard.warehouse');
    }

    public function store(SearchPbRequest $request): RedirectResponse
    {
        $activeRound = $this->activeRoundForPb($request->pbNumber(), $request);

        if ($activeRound !== null) {
            return redirect()->route('warehouse.rounds.show', $activeRound);
        }

        return redirect()->route('warehouse.pb-search.result', [
            'location_id' => $request->integer('location_id'),
            'pb_number' => $request->pbNumber(),
        ]);
    }

    public function result(Request $request): Response|RedirectResponse
    {
        $locationId = $request->integer('location_id');
        $pbNumber = trim((string) $request->query('pb_number', ''));

        if ($locationId === 0 || $pbNumber === '') {
            return redirect()->route('dashboard.warehouse');
        }

        $location = $request->user()
            ?->locations()
            ->where('locations.id', $locationId)
            ->where('is_active', true)
            ->first();

        if ($location === null) {
            return redirect()->route('dashboard.warehouse');
        }

        $activeRound = $this->activeRoundForPb($pbNumber, $request);

        if ($activeRound !== null) {
            return redirect()->route('warehouse.rounds.show', $activeRound);
        }

        try {
            $result = $this->accurateReceiveItems->findReceiveItemDetail($location, $pbNumber);
        } catch (RuntimeException $exception) {
            return Inertia::render('dashboards/warehouse', [
                'locations' => $this->locationOptions($request),
                'selected_location_id' => $location->id,
                'pb_number' => $pbNumber,
                'errors' => ['pb_number' => $exception->getMessage()],
            ]);
        }

        return Inertia::render('warehouse/pb-search/result', [
            'location' => [
                'id' => $location->id,
                'name' => $location->name,
            ],
            'result' => $result,
            'hods' => $location->users()
                ->where('role', 'HOD')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['users.id', 'users.name'])
                ->map(fn ($user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                ])
                ->all(),
        ]);
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function locationOptions(Request $request): array
    {
        return $request->user()
            ->locations()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['locations.id', 'locations.name'])
            ->map(fn ($location): array => [
                'id' => $location->id,
                'name' => $location->name,
            ])
            ->all();
    }

    private function activeRoundForPb(string $pbNumber, Request $request): ?GoodsReceiptRound
    {
        $inspection = GoodsReceiptInspection::query()
            ->with(['rounds' => fn ($query) => $query->where('round_status', '!=', 'SIGNED')])
            ->where('pb_number', $pbNumber)
            ->first();

        if ($inspection === null || $inspection->rounds->isEmpty()) {
            return null;
        }

        $hasAccess = $request->user()
            ?->locations()
            ->where('locations.id', $inspection->location_id)
            ->exists() ?? false;

        if (! $hasAccess) {
            abort(403);
        }

        return $inspection->rounds->first();
    }
}
