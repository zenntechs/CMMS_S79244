<?php

namespace App\Enums;

enum WorkOrderEventType: string
{
    case Created = 'created';
    case Started = 'started';
    case Submitted = 'submitted';
    case Returned = 'returned';
    case Closed = 'closed';
    case Corrected = 'corrected';
}
