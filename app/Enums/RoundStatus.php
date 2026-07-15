<?php

namespace App\Enums;

enum RoundStatus: string
{
    case Draft = 'DRAFT';
    case WaitingHodSignature = 'WAITING_HOD_SIGNATURE';
    case WaitingHeadWarehouseSignature = 'WAITING_HEAD_WAREHOUSE_SIGNATURE';
    case Signed = 'SIGNED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::WaitingHodSignature => 'Menunggu tanda tangan user',
            self::WaitingHeadWarehouseSignature => 'Menunggu tanda tangan kepala gudang',
            self::Signed => 'Selesai',
        };
    }
}
