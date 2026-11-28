<?php

namespace App\Enums;

enum UserType: string
{
    use EnumHelpers;

    case Staff = 'staff';
    case Buyer = 'buyer';
}
