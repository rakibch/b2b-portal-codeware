<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    use EnumHelpers;

    case Packing = 'packing';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';
    case Returned = 'returned';
}
