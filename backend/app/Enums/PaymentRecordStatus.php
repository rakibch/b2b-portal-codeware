<?php

namespace App\Enums;

enum PaymentRecordStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Failed = 'failed';
    case Refunded = 'refunded';
}
