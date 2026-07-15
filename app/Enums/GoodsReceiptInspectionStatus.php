<?php

namespace App\Enums;

enum GoodsReceiptInspectionStatus: string
{
    case OpenPartial = 'OPEN_PARTIAL';
    case Complete = 'COMPLETE';
    case PdfGenerated = 'PDF_GENERATED';

    public function label(): string
    {
        return match ($this) {
            self::OpenPartial => 'Masih sebagian',
            self::Complete => 'Lengkap',
            self::PdfGenerated => 'PDF final tersedia',
        };
    }
}
