<?php

namespace App\Enums;

enum DocumentStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
}
