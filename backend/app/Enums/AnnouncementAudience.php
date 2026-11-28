<?php

namespace App\Enums;

enum AnnouncementAudience: string
{
    use EnumHelpers;

    case All = 'all';
    case Buyers = 'buyers';
    case Staff = 'staff';
}
