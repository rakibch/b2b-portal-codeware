<?php

namespace App\Enums;

enum AddressType: string
{
    use EnumHelpers;

    case Billing = 'billing';
    case Shipping = 'shipping';
}
