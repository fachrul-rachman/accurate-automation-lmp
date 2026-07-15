<?php

namespace App\Models;

use App\Enums\QualityStatus;
use App\Enums\QuantityStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $round_id
 * @property int $line_number
 * @property string|null $accurate_detail_item_id
 * @property string|null $accurate_item_id
 * @property string|null $item_code
 * @property string $item_name
 * @property string $qty
 * @property string|null $unit
 * @property string|null $po_number
 * @property QualityStatus $quality_status
 * @property QuantityStatus $quantity_status
 * @property string|null $received_qty
 * @property string $damaged_qty
 */
#[Fillable([
    'round_id',
    'line_number',
    'accurate_detail_item_id',
    'accurate_item_id',
    'item_code',
    'item_name',
    'qty',
    'unit',
    'po_number',
    'quality_status',
    'quantity_status',
    'received_qty',
    'damaged_qty',
])]
final class GoodsReceiptRoundItem extends Model
{
    /**
     * @return BelongsTo<GoodsReceiptRound, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptRound::class, 'round_id');
    }

    /**
     * @return HasMany<GoodsReceiptRoundItemPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(GoodsReceiptRoundItemPhoto::class, 'round_item_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quality_status' => QualityStatus::class,
            'quantity_status' => QuantityStatus::class,
            'qty' => 'decimal:4',
            'received_qty' => 'decimal:4',
            'damaged_qty' => 'decimal:4',
        ];
    }
}
