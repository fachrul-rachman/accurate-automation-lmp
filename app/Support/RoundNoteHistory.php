<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\GoodsReceiptRound;

final class RoundNoteHistory
{
    /** @return array<int, array<string, mixed>> */
    public static function for(GoodsReceiptRound $round): array
    {
        $round->loadMissing('items');
        $items = $round->items->keyBy('id');

        return AuditLog::query()
            ->with('user:id,name,role')
            ->where('auditable_type', GoodsReceiptRound::class)
            ->where('auditable_id', (string) $round->id)
            ->latest('created_at')
            ->latest('id')
            ->get()
            ->filter(fn (AuditLog $log): bool => filled($log->metadata['notes'] ?? null))
            ->map(function (AuditLog $log) use ($items): array {
                $itemNames = [];

                if (is_array($log->metadata['item_ids'] ?? null)) {
                    foreach ($log->metadata['item_ids'] as $id) {
                        if (! is_int($id) && ! is_string($id)) {
                            continue;
                        }

                        $item = $items->get((int) $id);

                        if ($item !== null) {
                            $itemNames[] = $item->item_name;
                        }
                    }
                }

                return [
                    'id' => $log->id,
                    'note' => $log->metadata['notes'],
                    'event' => str($log->event)->lower()->replace('_', ' ')->title()->toString(),
                    'user_name' => $log->user?->name,
                    'role' => $log->user?->role?->label(),
                    'item_names' => $itemNames,
                    'created_at' => DateFormatter::wib($log->created_at),
                ];
            })
            ->values()
            ->all();
    }
}
