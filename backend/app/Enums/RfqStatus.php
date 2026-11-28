<?php

namespace App\Enums;

enum RfqStatus: string
{
    use EnumHelpers;

    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Quoted = 'quoted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Converted = 'converted';
    case Cancelled = 'cancelled';
}
