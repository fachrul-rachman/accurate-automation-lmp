<?php

namespace App\Support;

use App\Enums\RoundStatus;
use App\Models\GoodsReceiptRound;

final class RoundItemQuantity
{
    /**
     * @return array<int, float>
     */
    public static function remainingByRoundItem(GoodsReceiptRound $round): array
    {
        $round->loadMissing('inspection.rounds.items', 'items');

        $requiredByItem = self::requiredQuantities($round);
        $goodByItem = [];

        foreach ($round->inspection->rounds as $inspectionRound) {
            $shouldCountRound = $inspectionRound->is($round)
                || $inspectionRound->round_status === RoundStatus::Signed;

            if (! $shouldCountRound) {
                continue;
            }

            foreach ($inspectionRound->items as $item) {
                $key = self::itemKey($item->accurate_detail_item_id, $item->item_code, $item->line_number);
                $goodByItem[$key] = ($goodByItem[$key] ?? 0)
                    + max(0, (float) $item->received_qty - (float) $item->damaged_qty);
            }
        }

        $remainingByRoundItem = [];

        foreach ($round->items as $item) {
            $key = self::itemKey($item->accurate_detail_item_id, $item->item_code, $item->line_number);
            $requiredQty = $requiredByItem[$key] ?? (float) $item->qty;
            $remainingByRoundItem[$item->id] = max(0, $requiredQty - ($goodByItem[$key] ?? 0));
        }

        return $remainingByRoundItem;
    }

    private static function itemKey(?string $accurateDetailItemId, ?string $itemCode, int $lineNumber): string
    {
        return $accurateDetailItemId ?? $itemCode ?? (string) $lineNumber;
    }

    /**
     * @return array<string, float>
     */
    private static function requiredQuantities(GoodsReceiptRound $round): array
    {
        $items = $round->inspection->accurate_raw_json['detailItem'] ?? null;

        if (! is_array($items)) {
            return [];
        }

        $requiredByItem = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $key = isset($item['id'])
                ? (string) $item['id']
                : (string) ($item['item']['no'] ?? $index + 1);

            $quantity = $item['quantity'] ?? null;

            if (is_numeric($quantity)) {
                $requiredByItem[$key] = max($requiredByItem[$key] ?? 0, (float) $quantity);
            }
        }

        return $requiredByItem;
    }
}
