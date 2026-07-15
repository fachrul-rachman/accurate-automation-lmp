<?php

namespace App\Enums;

enum QuantityStatus: string
{
    case Match = 'MATCH';
    case NotMatch = 'NOT_MATCH';
    case Partial = 'PARTIAL';

    public function label(): string
    {
        return match ($this) {
            self::Match => 'Sesuai',
            self::NotMatch => 'Tidak Sesuai',
            self::Partial => 'Sebagian',
        };
    }
}
