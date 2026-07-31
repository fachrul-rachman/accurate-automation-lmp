<?php

namespace App\Enums;

enum RoundStatus: string
{
    case Draft = 'DRAFT';
    case WaitingHeadWarehouseSignature = 'WAITING_HEAD_WAREHOUSE_SIGNATURE';
    case WaitingUserSignature = 'WAITING_USER_SIGNATURE';
    case NeedsRevision = 'NEEDS_REVISION';
    case Signed = 'SIGNED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::WaitingHeadWarehouseSignature => 'Menunggu tanda tangan Head Warehouse',
            self::WaitingUserSignature => 'Menunggu tanda tangan user',
            self::NeedsRevision => 'Perlu perbaikan Warehouse',
            self::Signed => 'Selesai',
        };
    }
}
