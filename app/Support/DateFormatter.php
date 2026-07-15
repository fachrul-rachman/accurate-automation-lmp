<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class DateFormatter
{
    public static function wib(?CarbonInterface $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return $date->copy()->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB';
    }
}
