<?php

namespace App\Models;

use App\Enums\GoodsReceiptInspectionStatus;
use App\Enums\PdfStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $location_id
 * @property int $created_by
 * @property string|null $accurate_receive_item_id
 * @property string $pb_number
 * @property string|null $po_number
 * @property string|null $supplier_name
 * @property Carbon|null $pb_date
 * @property array<string, mixed> $accurate_raw_json
 * @property GoodsReceiptInspectionStatus $pb_status
 * @property PdfStatus $pdf_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property PdfExport|null $pdfExport
 */
#[Fillable([
    'location_id',
    'created_by',
    'accurate_receive_item_id',
    'pb_number',
    'po_number',
    'supplier_name',
    'pb_date',
    'accurate_raw_json',
    'pb_status',
    'pdf_status',
])]
final class GoodsReceiptInspection extends Model
{
    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<GoodsReceiptRound, $this>
     */
    public function rounds(): HasMany
    {
        return $this->hasMany(GoodsReceiptRound::class, 'inspection_id')->orderBy('round_number');
    }

    /**
     * @return HasOne<PdfExport, $this>
     */
    public function pdfExport(): HasOne
    {
        return $this->hasOne(PdfExport::class, 'inspection_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pb_date' => 'date',
            'accurate_raw_json' => 'array',
            'pb_status' => GoodsReceiptInspectionStatus::class,
            'pdf_status' => PdfStatus::class,
        ];
    }
}
