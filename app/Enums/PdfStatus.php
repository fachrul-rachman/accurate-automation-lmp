<?php

namespace App\Enums;

enum PdfStatus: string
{
    case NotReady = 'NOT_READY';
    case Generated = 'GENERATED';

    public function label(): string
    {
        return match ($this) {
            self::NotReady => 'Belum tersedia',
            self::Generated => 'Tersedia',
        };
    }
}
