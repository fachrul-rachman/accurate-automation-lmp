<?php

namespace App\Models;

use App\Enums\RoundStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $inspection_id
 * @property int $round_number
 * @property int $created_by
 * @property int|null $selected_user_id
 * @property RoundStatus $round_status
 * @property string|null $completion_result
 * @property bool $specification_matches_po
 * @property string|null $notes
 * @property Carbon|null $submitted_at
 * @property int|null $user_signed_by
 * @property Carbon|null $user_signed_at
 * @property int|null $head_warehouse_signed_by
 * @property Carbon|null $head_warehouse_signed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property GoodsReceiptInspection $inspection
 */
#[Fillable([
    'inspection_id',
    'round_number',
    'created_by',
    'selected_user_id',
    'round_status',
    'completion_result',
    'specification_matches_po',
    'notes',
    'submitted_at',
    'user_signed_by',
    'user_signed_at',
    'head_warehouse_signed_by',
    'head_warehouse_signed_at',
    'rejected_by',
    'rejected_at',
    'rejection_reason',
])]
final class GoodsReceiptRound extends Model
{
    /**
     * @return BelongsTo<GoodsReceiptInspection, $this>
     */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptInspection::class, 'inspection_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function selectedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function userSigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_signed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function headWarehouseSigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_warehouse_signed_by');
    }

    /**
     * @return HasMany<GoodsReceiptRoundItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptRoundItem::class, 'round_id')->orderBy('line_number');
    }

    /**
     * @return HasMany<RoundAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(RoundAttachment::class, 'round_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'round_status' => RoundStatus::class,
            'specification_matches_po' => 'boolean',
            'submitted_at' => 'datetime',
            'user_signed_at' => 'datetime',
            'head_warehouse_signed_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }
}
