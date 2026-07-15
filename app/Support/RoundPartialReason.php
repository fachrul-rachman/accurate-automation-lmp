<?php

namespace App\Support;

use App\Enums\RoundStatus;
use App\Models\GoodsReceiptRound;

final class RoundPartialReason
{
    /**
     * @return array<int, string>
     */
    public static function forRound(GoodsReceiptRound $round): array
    {
        $round->loadMissing('inspection.rounds.items', 'items');

        $requiredByItem = self::requiredQuantities($round);

        if ($requiredByItem === []) {
            return self::currentRoundReasons($round);
        }

        $seenByItem = [];
        $goodByItem = [];
        $hasDamagedItem = false;

        foreach ($round->inspection->rounds as $inspectionRound) {
            $shouldCountRound = $inspectionRound->is($round)
                || $inspectionRound->round_status === RoundStatus::Signed;

            if (! $shouldCountRound) {
                continue;
            }

            foreach ($inspectionRound->items as $item) {
                $key = $item->accurate_detail_item_id ?? $item->item_code ?? (string) $item->line_number;
                $seenByItem[$key] = true;

                $receivedQty = (float) $item->received_qty;
                $damagedQty = (float) $item->damaged_qty;

                if ($inspectionRound->is($round) && $damagedQty > 0) {
                    $hasDamagedItem = true;
                }

                $goodByItem[$key] = ($goodByItem[$key] ?? 0)
                    + max(0, $receivedQty - $damagedQty);
            }
        }

        $hasMissingItem = false;
        $hasShortQuantity = false;

        foreach ($requiredByItem as $key => $requiredQty) {
            if (! isset($seenByItem[$key])) {
                $hasMissingItem = true;

                continue;
            }

            if (($goodByItem[$key] ?? 0) < $requiredQty) {
                $hasShortQuantity = true;
            }
        }

        return self::messages($hasMissingItem, $hasShortQuantity, $hasDamagedItem);
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

    /**
     * @return array<int, string>
     */
    private static function currentRoundReasons(GoodsReceiptRound $round): array
    {
        $hasShortQuantity = false;
        $hasDamagedItem = false;

        foreach ($round->items as $item) {
            $receivedQty = (float) $item->received_qty;
            $damagedQty = (float) $item->damaged_qty;

            if ($receivedQty < (float) $item->qty) {
                $hasShortQuantity = true;
            }

            if ($damagedQty > 0) {
                $hasDamagedItem = true;
            }
        }

        return self::messages(false, $hasShortQuantity, $hasDamagedItem);
    }

    /**
     * @return array<int, string>
     */
    private static function messages(bool $hasMissingItem, bool $hasShortQuantity, bool $hasDamagedItem): array
    {
        $messages = [];

        if ($hasMissingItem) {
            $messages[] = 'Ada item yang belum datang.';
        }

        if ($hasShortQuantity) {
            $messages[] = 'Ada quantity item yang belum lengkap.';
        }

        if ($hasDamagedItem) {
            $messages[] = 'Ada item rusak.';
        }

        return $messages;
    }
}
