<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    use EnumHelpers;

    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Void = 'void';
}
