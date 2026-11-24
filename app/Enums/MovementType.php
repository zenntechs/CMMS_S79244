<?php

namespace App\Enums;

enum MovementType: string
{
    case Issue = 'issue';
    case Return = 'return';
    case Receipt = 'receipt';
    case Adjustment = 'adjustment';
}
