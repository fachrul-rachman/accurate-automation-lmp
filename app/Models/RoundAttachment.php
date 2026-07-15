<?php

namespace App\Models;

use App\Enums\RoundAttachmentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $round_id
 * @property RoundAttachmentType $attachment_type
 * @property string $r2_key
 * @property string|null $original_filename
 * @property string|null $mime_type
 * @property int|null $file_size
 * @property GoodsReceiptRound $round
 */
#[Fillable(['round_id', 'attachment_type', 'r2_key', 'original_filename', 'mime_type', 'file_size'])]
final class RoundAttachment extends Model
{
    /**
     * @return BelongsTo<GoodsReceiptRound, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptRound::class, 'round_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attachment_type' => RoundAttachmentType::class,
        ];
    }
}
