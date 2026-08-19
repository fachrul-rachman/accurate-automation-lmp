<?php

namespace App\Enums;

enum RoundItemStatus: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Signed = 'SIGNED';
    case Rejected = 'REJECTED';
}
