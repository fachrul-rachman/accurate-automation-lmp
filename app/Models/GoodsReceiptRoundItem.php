<?php

namespace App\Models;

use App\Enums\QualityStatus;
use App\Enums\QuantityStatus;
use App\Enums\RoundItemStatus;
use App\Enums\RoundStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $round_id
 * @property int|null $selected_user_id
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
 * @property RoundItemStatus $head_status
 * @property RoundItemStatus $user_status
 */
#[Fillable([
    'round_id',
    'selected_user_id',
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
    'head_status',
    'head_signed_by',
    'head_signed_at',
    'user_status',
    'user_signed_by',
    'user_signed_at',
    'rejection_reason',
])]
final class GoodsReceiptRoundItem extends Model
{
    protected static function booted(): void
    {
        self::creating(function (GoodsReceiptRoundItem $item): void {
            $round = $item->round()->first();

            if ($round === null) {
                return;
            }

            $item->selected_user_id ??= $round->selected_user_id;

            if ($round->round_status === RoundStatus::WaitingUserSignature || $round->round_status === RoundStatus::Signed) {
                $item->head_status = RoundItemStatus::Approved;
                $item->head_signed_by ??= $round->head_warehouse_signed_by;
                $item->head_signed_at ??= $round->head_warehouse_signed_at;
            } elseif ($round->round_status === RoundStatus::NeedsRevision) {
                $item->head_status = RoundItemStatus::Rejected;
                $item->rejection_reason ??= $round->rejection_reason;
            }

            if ($round->round_status === RoundStatus::Signed) {
                $item->user_status = RoundItemStatus::Signed;
                $item->user_signed_by ??= $round->user_signed_by;
                $item->user_signed_at ??= $round->user_signed_at;
            }
        });
    }

    /**
     * @return BelongsTo<GoodsReceiptRound, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptRound::class, 'round_id');
    }

    /** @return BelongsTo<User, $this> */
    public function selectedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function headSigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_signed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function userSigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_signed_by');
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
            'head_status' => RoundItemStatus::class,
            'head_signed_at' => 'datetime',
            'user_status' => RoundItemStatus::class,
            'user_signed_at' => 'datetime',
        ];
    }
}
