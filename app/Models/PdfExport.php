<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $inspection_id
 * @property int|null $generated_by
 * @property string $r2_key
 * @property string $file_name
 * @property int|null $file_size
 * @property Carbon $generated_at
 */
#[Fillable([
    'inspection_id',
    'generated_by',
    'r2_key',
    'file_name',
    'file_size',
    'generated_at',
])]
final class PdfExport extends Model
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
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }
}
