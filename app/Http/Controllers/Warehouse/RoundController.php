<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\RoundStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\StoreRoundRequest;
use App\Models\GoodsReceiptRound;
use App\Models\User;
use App\Services\AccurateReceiveItemService;
use App\Services\GoodsReceiptRoundService;
use App\Support\DateFormatter;
use App\Support\RoundItemQuantity;
use App\Support\RoundPartialReason;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class RoundController extends Controller
{
    public function __construct(
        private readonly GoodsReceiptRoundService $rounds,
        private readonly AccurateReceiveItemService $accurateReceiveItems,
    ) {}

    public function store(StoreRoundRequest $request): RedirectResponse
    {
        try {
            $round = $this->rounds->createDraftRound($request->location(), $request->user(), $request->validated());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['pb_number' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('warehouse.rounds.show', $round);
    }

    public function show(Request $request, GoodsReceiptRound $round): Response
    {
        $this->authorizeWarehouseAccess($request, $round);

        $round->load(['inspection.location', 'inspection.pdfExport', 'items.photos', 'attachments']);
        $remainingByItem = RoundItemQuantity::remainingByRoundItem($round);

        return Inertia::render('warehouse/rounds/show', [
            'round' => [
                'id' => $round->id,
                'round_number' => $round->round_number,
                'round_status' => $this->roundStatusLabel($round),
                'round_status_value' => $round->round_status->value,
                'completion_result' => $round->completion_result,
                'specification_matches_po' => $round->specification_matches_po,
                'notes' => $round->notes,
                'submitted_at' => DateFormatter::wib($round->submitted_at),
                'selected_user_id' => $round->selected_user_id,
                'rejection_reason' => $round->rejection_reason,
                'inspection' => [
                    'pb_number' => $round->inspection->pb_number,
                    'po_number' => $round->inspection->po_number,
                    'supplier_name' => $round->inspection->supplier_name,
                    'location_name' => $round->inspection->location?->name,
                ],
                'items' => $round->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'item_code' => $item->item_code,
                    'item_name' => $item->item_name,
                    'po_number' => $item->po_number,
                    'qty' => (float) $item->qty,
                    'unit' => $item->unit,
                    'quality_status' => $item->quality_status->label(),
                    'quantity_status' => $item->quantity_status->label(),
                    'received_qty' => $item->received_qty === null ? null : (float) $item->received_qty,
                    'damaged_qty' => (float) $item->damaged_qty,
                    'qty_remaining' => $remainingByItem[$item->id] ?? 0.0,
                    'photo_count' => $item->photos->count(),
                ])->all(),
                'partial_reasons' => RoundPartialReason::forRound($round),
                'can_edit' => in_array($round->round_status, [RoundStatus::Draft, RoundStatus::NeedsRevision], true),
                'edit_url' => $round->round_status === RoundStatus::NeedsRevision
                    ? route('warehouse.rounds.edit', $round)
                    : null,
                'pdf_download_url' => $round->inspection->pdfExport === null
                    ? null
                    : route('files.pdf-exports.show', $round->inspection->pdfExport),
                'continue_url' => $round->round_status === RoundStatus::Signed && $round->completion_result === 'PARTIAL'
                    ? route('warehouse.pb-search.result', [
                        'location_id' => $round->inspection->location_id,
                        'pb_number' => $round->inspection->pb_number,
                    ])
                    : null,
            ],
        ]);
    }

    public function edit(Request $request, GoodsReceiptRound $round): Response|RedirectResponse
    {
        $this->authorizeWarehouseAccess($request, $round);
        abort_unless($round->round_status === RoundStatus::NeedsRevision, 403);
        $round->load(['inspection.location', 'items']);

        try {
            $result = $this->accurateReceiveItems->findReceiveItemDetail($round->inspection->location, $round->inspection->pb_number);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['round' => $exception->getMessage()]);
        }

        return Inertia::render('warehouse/pb-search/result', [
            'location' => [
                'id' => $round->inspection->location->id,
                'name' => $round->inspection->location->name,
            ],
            'result' => [
                ...$result,
                'items' => array_map(fn (array $item): array => [...$item, 'qty_remaining' => null], $result['items']),
            ],
            'users' => $round->inspection->location->users()
                ->where('role', UserRole::User->value)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['users.id', 'users.name'])
                ->map(fn ($user): array => ['id' => $user->id, 'name' => $user->name])
                ->all(),
            'editing_round' => [
                'id' => $round->id,
                'selected_user_id' => $round->selected_user_id,
                'specification_matches_po' => $round->specification_matches_po,
                'notes' => $round->notes,
                'rejection_reason' => $round->rejection_reason,
                'items' => $round->items->map(fn ($item): array => [
                    'accurate_detail_item_id' => $item->accurate_detail_item_id,
                    'quality_status' => $item->quality_status->value,
                    'quantity_status' => $item->quantity_status->value,
                    'received_qty' => $item->received_qty,
                    'damaged_qty' => $item->damaged_qty,
                ])->all(),
            ],
        ]);
    }

    public function update(StoreRoundRequest $request, GoodsReceiptRound $round): RedirectResponse
    {
        $this->authorizeWarehouseAccess($request, $round);
        abort_unless($round->round_status === RoundStatus::NeedsRevision, 403);

        try {
            $this->rounds->reviseRound($round, $request->location(), $request->user(), $request->validated());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['round' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('warehouse.rounds.show', $round);
    }

    public function submit(Request $request, GoodsReceiptRound $round): RedirectResponse
    {
        $this->authorizeWarehouseAccess($request, $round);

        try {
            $this->rounds->submitToHeadWarehouse($round);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['round' => $exception->getMessage()]);
        }

        return redirect()->route('warehouse.rounds.show', $round);
    }

    private function authorizeWarehouseAccess(Request $request, GoodsReceiptRound $round): void
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->role === UserRole::Warehouse, 403);

        $round->loadMissing('inspection');

        $allowed = $user->locations()
            ->where('locations.id', $round->inspection->location_id)
            ->exists();

        abort_unless($allowed, 403);
    }

    private function roundStatusLabel(GoodsReceiptRound $round): string
    {
        if ($round->round_status === RoundStatus::Signed && $round->completion_result === 'PARTIAL') {
            return 'Parsial';
        }

        return $round->round_status->label();
    }
}
