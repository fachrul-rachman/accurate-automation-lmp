<?php

namespace App\Http\Controllers\HeadWarehouse;

use App\Enums\RoundStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\GoodsReceiptRound;
use App\Models\User;
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
    public function __construct(private readonly GoodsReceiptRoundService $rounds) {}

    public function show(Request $request, GoodsReceiptRound $round): Response
    {
        $this->authorizeHeadWarehouseAccess($request);
        $this->authorizeHeadWarehouseRoundVisibility($round);

        $round->load(['inspection.location', 'items.photos', 'attachments']);
        $remainingByItem = RoundItemQuantity::remainingByRoundItem($round);

        return Inertia::render('head-warehouse/rounds/show', [
            'round' => [
                'id' => $round->id,
                'round_number' => $round->round_number,
                'round_status' => $this->roundStatusLabel($round),
                'round_status_value' => $round->round_status->value,
                'completion_result' => $round->completion_result,
                'specification_matches_po' => $round->specification_matches_po,
                'notes' => $round->notes,
                'submitted_at' => DateFormatter::wib($round->submitted_at),
                'head_warehouse_signed_at' => DateFormatter::wib($round->head_warehouse_signed_at),
                'user_signed_at' => DateFormatter::wib($round->user_signed_at),
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
                    'photos' => $item->photos->map(fn ($photo): array => [
                        'id' => $photo->id,
                        'type' => $photo->photo_type->value,
                        'type_label' => $photo->photo_type->value === 'DAMAGE' ? 'Foto Kerusakan' : 'Foto Item',
                        'url' => route('files.round-item-photos.show', $photo),
                    ])->all(),
                ])->all(),
                'attachments' => $round->attachments->map(fn ($attachment): array => [
                    'id' => $attachment->id,
                    'type' => $attachment->attachment_type->value,
                    'type_label' => $attachment->attachment_type->value === 'RECEIVER_PHOTO' ? 'Foto Penerima' : 'Foto Surat Jalan',
                    'url' => route('files.round-attachments.show', $attachment),
                ])->all(),
                'partial_reasons' => RoundPartialReason::forRound($round),
                'can_sign' => $round->round_status === RoundStatus::WaitingHeadWarehouseSignature,
                'can_reject' => $round->round_status === RoundStatus::WaitingHeadWarehouseSignature,
            ],
        ]);
    }

    public function sign(Request $request, GoodsReceiptRound $round): RedirectResponse
    {
        $this->authorizeHeadWarehouseAccess($request);

        try {
            $this->rounds->signByHeadWarehouse($round, $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['round' => $exception->getMessage()]);
        }

        return redirect()->route('head-warehouse.rounds.show', $round);
    }

    public function reject(Request $request, GoodsReceiptRound $round): RedirectResponse
    {
        $this->authorizeHeadWarehouseAccess($request);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:1000']])['reason'];

        try {
            $this->rounds->rejectByHeadWarehouse($round, $request->user(), $reason);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['round' => $exception->getMessage()]);
        }

        return redirect()->route('head-warehouse.rounds.show', $round);
    }

    private function authorizeHeadWarehouseAccess(Request $request): void
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->role === UserRole::HeadWarehouse, 403);
    }

    private function authorizeHeadWarehouseRoundVisibility(GoodsReceiptRound $round): void
    {
        abort_unless(
            in_array($round->round_status, [
                RoundStatus::WaitingHeadWarehouseSignature,
                RoundStatus::WaitingUserSignature,
                RoundStatus::NeedsRevision,
                RoundStatus::Signed,
            ], true),
            403
        );
    }

    private function roundStatusLabel(GoodsReceiptRound $round): string
    {
        if ($round->round_status === RoundStatus::Signed && $round->completion_result === 'PARTIAL') {
            return 'Parsial';
        }

        return $round->round_status->label();
    }
}
