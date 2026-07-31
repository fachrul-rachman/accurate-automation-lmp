<?php

namespace App\Http\Controllers;

use App\Enums\RoundStatus;
use App\Enums\UserRole;
use App\Models\GoodsReceiptRound;
use App\Support\DateFormatter;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $role = $request->user()?->role;

        return redirect()->route(
            $role instanceof UserRole ? $role->dashboardRoute() : 'dashboard.warehouse'
        );
    }

    public function warehouse(): Response
    {
        $user = request()->user();
        abort_unless($user !== null && $user->role === UserRole::Warehouse, 403);

        $locationsCollection = $user
            ->locations()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['locations.id', 'locations.name']);

        $locations = $locationsCollection->map(fn ($location): array => [
            'id' => $location->id,
            'name' => $location->name,
        ])
            ->all();

        $locationIds = $locationsCollection->pluck('id')->all();
        $latestRoundsByInspection = GoodsReceiptRound::query()
            ->with(['inspection.location', 'inspection.pdfExport'])
            ->whereHas('inspection', fn ($query) => $query->whereIn('location_id', $locationIds))
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get()
            ->groupBy('inspection_id')
            ->map(fn (Collection $rounds): GoodsReceiptRound => $rounds->sortByDesc('round_number')->first())
            ->sortByDesc(fn (GoodsReceiptRound $round): int => $round->updated_at?->getTimestamp() ?? 0)
            ->take(20)
            ->values();

        $activeRounds = $latestRoundsByInspection
            ->map(fn (GoodsReceiptRound $round): array => [
                'id' => $round->id,
                'pb_number' => $round->inspection->pb_number,
                'po_number' => $round->inspection->po_number,
                'supplier_name' => $round->inspection->supplier_name,
                'location_name' => $round->inspection->location?->name,
                'round_number' => $round->round_number,
                'status' => $round->round_status->value,
                'status_label' => $this->warehouseRoundStatusLabel($round),
                'completion_result' => $round->completion_result,
                'updated_at' => DateFormatter::wib($round->updated_at),
                'pdf_download_url' => $round->inspection->pdfExport === null
                    ? null
                    : route('files.pdf-exports.show', $round->inspection->pdfExport),
                'continue_url' => $this->canContinuePartialRound($round)
                    ? route('warehouse.pb-search.result', [
                        'location_id' => $round->inspection->location_id,
                        'pb_number' => $round->inspection->pb_number,
                    ])
                    : null,
            ])
            ->all();

        return Inertia::render('dashboards/warehouse', [
            'user_summary' => UserDisplay::summary($user, $locationsCollection),
            'locations' => $locations,
            'active_rounds' => $activeRounds,
            'active_round_count' => count($activeRounds),
        ]);
    }

    private function warehouseRoundStatusLabel(GoodsReceiptRound $round): string
    {
        if ($round->round_status === RoundStatus::Signed && $round->completion_result === 'PARTIAL') {
            return 'Parsial';
        }

        return $round->round_status->label();
    }

    private function canContinuePartialRound(GoodsReceiptRound $round): bool
    {
        $hasActiveRound = $round->inspection
            ->rounds()
            ->where('round_status', '!=', RoundStatus::Signed->value)
            ->exists();

        return $round->round_status === RoundStatus::Signed
            && $round->completion_result === 'PARTIAL'
            && $round->inspection->pb_status->value === 'OPEN_PARTIAL'
            && ! $hasActiveRound;
    }

    private function signedRoundStatusLabel(GoodsReceiptRound $round): string
    {
        return $round->completion_result === 'PARTIAL' ? 'Parsial' : $round->round_status->label();
    }

    public function user(): Response
    {
        $user = request()->user();
        abort_unless($user !== null && $user->role === UserRole::User, 403);

        $pendingRounds = GoodsReceiptRound::query()
            ->with(['inspection.location'])
            ->where('selected_user_id', $user->id)
            ->where('round_status', RoundStatus::WaitingUserSignature)
            ->latest('head_warehouse_signed_at')
            ->limit(20)
            ->get()
            ->map(fn (GoodsReceiptRound $round): array => [
                'id' => $round->id,
                'pb_number' => $round->inspection->pb_number,
                'po_number' => $round->inspection->po_number,
                'supplier_name' => $round->inspection->supplier_name,
                'location_name' => $round->inspection->location?->name,
                'round_number' => $round->round_number,
                'submitted_at' => DateFormatter::wib($round->submitted_at),
            ])
            ->all();

        $historyRounds = GoodsReceiptRound::query()
            ->with(['inspection.location'])
            ->where('selected_user_id', $user->id)
            ->whereIn('round_status', [RoundStatus::NeedsRevision, RoundStatus::Signed])
            ->latest('updated_at')
            ->limit(20)
            ->get()
            ->map(fn (GoodsReceiptRound $round): array => [
                'id' => $round->id,
                'pb_number' => $round->inspection->pb_number,
                'po_number' => $round->inspection->po_number,
                'supplier_name' => $round->inspection->supplier_name,
                'location_name' => $round->inspection->location?->name,
                'round_number' => $round->round_number,
                'status' => $this->signedRoundStatusLabel($round),
                'completion_result' => $round->completion_result,
                'updated_at' => DateFormatter::wib($round->updated_at),
            ])
            ->all();

        return Inertia::render('dashboards/user', [
            'user_summary' => UserDisplay::summary($user),
            'pending_rounds' => $pendingRounds,
            'pending_round_count' => count($pendingRounds),
            'history_rounds' => $historyRounds,
            'history_round_count' => count($historyRounds),
        ]);
    }

    public function headWarehouse(): Response
    {
        $user = request()->user();
        abort_unless($user !== null && $user->role === UserRole::HeadWarehouse, 403);

        $pendingRounds = GoodsReceiptRound::query()
            ->with(['inspection.location'])
            ->where('round_status', RoundStatus::WaitingHeadWarehouseSignature)
            ->latest('submitted_at')
            ->limit(20)
            ->get()
            ->map(fn (GoodsReceiptRound $round): array => [
                'id' => $round->id,
                'pb_number' => $round->inspection->pb_number,
                'po_number' => $round->inspection->po_number,
                'supplier_name' => $round->inspection->supplier_name,
                'location_name' => $round->inspection->location?->name,
                'round_number' => $round->round_number,
                'submitted_at' => DateFormatter::wib($round->submitted_at),
            ])
            ->all();

        $historyRounds = GoodsReceiptRound::query()
            ->with(['inspection.location'])
            ->whereIn('round_status', [RoundStatus::WaitingUserSignature, RoundStatus::NeedsRevision, RoundStatus::Signed])
            ->latest('updated_at')
            ->limit(20)
            ->get()
            ->map(fn (GoodsReceiptRound $round): array => [
                'id' => $round->id,
                'pb_number' => $round->inspection->pb_number,
                'po_number' => $round->inspection->po_number,
                'supplier_name' => $round->inspection->supplier_name,
                'location_name' => $round->inspection->location?->name,
                'round_number' => $round->round_number,
                'status' => $this->signedRoundStatusLabel($round),
                'completion_result' => $round->completion_result,
                'updated_at' => DateFormatter::wib($round->head_warehouse_signed_at),
            ])
            ->all();

        return Inertia::render('dashboards/head-warehouse', [
            'user_summary' => UserDisplay::summary($user),
            'pending_rounds' => $pendingRounds,
            'pending_round_count' => count($pendingRounds),
            'history_rounds' => $historyRounds,
            'history_round_count' => count($historyRounds),
        ]);
    }

    public function admin(): Response
    {
        $user = request()->user();
        abort_unless($user !== null && $user->role === UserRole::Admin, 403);

        return Inertia::render('dashboards/admin', [
            'user_summary' => UserDisplay::summary($user),
        ]);
    }
}
