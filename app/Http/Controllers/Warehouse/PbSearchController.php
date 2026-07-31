<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\UserRole;
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
            return redirect()
                ->route('dashboard.warehouse')
                ->withErrors(['pb_number' => $exception->getMessage()])
                ->withInput([
                    'location_id' => $location->id,
                    'pb_number' => $pbNumber,
                ]);
        }

        return Inertia::render('warehouse/pb-search/result', [
            'location' => [
                'id' => $location->id,
                'name' => $location->name,
            ],
            'result' => $this->withRemainingQuantities($result, $location->id),
            'users' => $location->users()
                ->where('role', UserRole::User->value)
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
