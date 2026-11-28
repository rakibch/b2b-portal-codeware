<?php

namespace App\Enums;

enum OrderPaymentStatus: string
{
    use EnumHelpers;

    case Unpaid = 'unpaid';
    case PendingVerification = 'pending_verification';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Refunded = 'refunded';
}
