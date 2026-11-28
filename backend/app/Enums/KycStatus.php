<?php

namespace App\Enums;

enum KycStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
}
