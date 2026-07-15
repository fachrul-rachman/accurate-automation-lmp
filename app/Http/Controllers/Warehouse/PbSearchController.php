<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\SearchPbRequest;
use App\Models\GoodsReceiptInspection;
use App\Models\GoodsReceiptRound;
use App\Models\Location;
use App\Services\AccurateReceiveItemService;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $user = $request->user();
        abort_unless($user !== null, 403);

        $locationId = $request->integer('location_id');
        $pbNumber = trim((string) $request->query('pb_number', ''));

        if ($locationId === 0 || $pbNumber === '') {
            return redirect()->route('dashboard.warehouse');
        }

        $location = $user
            ->locations()
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
            $locations = $this->locationModels($request);

            return Inertia::render('dashboards/warehouse', [
                'user_summary' => UserDisplay::summary($user, $locations),
                'locations' => $this->locationOptions($locations),
                'active_rounds' => [],
                'active_round_count' => 0,
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
            'result' => $this->withRemainingQuantities($result, $location->id),
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
     * @param  Collection<int, Location>  $locations
     * @return array<int, array{id: int, name: string}>
     */
    private function locationOptions(Collection $locations): array
    {
        return $locations
            ->map(fn ($location): array => [
                'id' => $location->id,
                'name' => $location->name,
            ])
            ->all();
    }

    /**
     * @return Collection<int, Location>
     */
    private function locationModels(Request $request): Collection
    {
        return $request->user()
            ->locations()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['locations.id', 'locations.name']);
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

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function withRemainingQuantities(array $result, int $locationId): array
    {
        $inspection = GoodsReceiptInspection::query()
            ->with('rounds.items')
            ->where('location_id', $locationId)
            ->where('pb_number', (string) $result['number'])
            ->first();

        if ($inspection === null) {
            return $this->withEmptyRemainingQuantities($result);
        }

        $goodByItem = [];

        foreach ($inspection->rounds as $round) {
            if ($round->round_status->value !== 'SIGNED') {
                continue;
            }

            foreach ($round->items as $item) {
                $key = $item->accurate_detail_item_id ?? $item->item_code ?? (string) $item->line_number;
                $goodByItem[$key] = ($goodByItem[$key] ?? 0)
                    + max(0, (float) $item->received_qty - (float) $item->damaged_qty);
            }
        }

        $items = is_array($result['items'] ?? null) ? $result['items'] : [];
        $mappedItems = [];

        foreach ($items as $index => $item) {
            if (is_array($item)) {
                $key = $item['accurate_item_row_id'] ?? $item['item_no'] ?? (string) ($index + 1);
                $quantity = is_numeric($item['quantity'] ?? null) ? (float) $item['quantity'] : null;

                $mappedItems[] = [
                    ...$item,
                    'qty_remaining' => $quantity === null ? null : max(0, $quantity - ($goodByItem[$key] ?? 0)),
                ];
            }
        }

        $result['items'] = $mappedItems;

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function withEmptyRemainingQuantities(array $result): array
    {
        $items = is_array($result['items'] ?? null) ? $result['items'] : [];
        $mappedItems = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                $mappedItems[] = [...$item, 'qty_remaining' => null];
            }
        }

        $result['items'] = $mappedItems;

        return $result;
    }
}
