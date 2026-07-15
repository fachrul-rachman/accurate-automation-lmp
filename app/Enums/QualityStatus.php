<?php

namespace App\Enums;

enum QualityStatus: string
{
    case Good = 'GOOD';
    case Damaged = 'DAMAGED';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Baik',
            self::Damaged => 'Rusak',
        };
    }
}
