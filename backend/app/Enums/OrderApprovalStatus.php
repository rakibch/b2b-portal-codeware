<?php

namespace App\Enums;

enum OrderApprovalStatus: string
{
    use EnumHelpers;

    case NotRequired = 'not_required';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
