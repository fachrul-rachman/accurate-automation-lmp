<?php

namespace App\Models;

use App\Enums\RoundItemPhotoType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $round_item_id
 * @property RoundItemPhotoType $photo_type
 * @property string $r2_key
 * @property string|null $original_filename
 * @property string|null $mime_type
 * @property int|null $file_size
 * @property GoodsReceiptRoundItem $roundItem
 */
#[Fillable(['round_item_id', 'photo_type', 'r2_key', 'original_filename', 'mime_type', 'file_size'])]
final class GoodsReceiptRoundItemPhoto extends Model
{
    /**
     * @return BelongsTo<GoodsReceiptRoundItem, $this>
     */
    public function roundItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptRoundItem::class, 'round_item_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'photo_type' => RoundItemPhotoType::class,
        ];
    }
}
