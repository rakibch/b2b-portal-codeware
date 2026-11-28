<?php

namespace App\Enums;

enum BusinessType: string
{
    use EnumHelpers;

    case Retailer = 'retailer';
    case Wholesaler = 'wholesaler';
    case Distributor = 'distributor';
    case SuperShop = 'super_shop';
    case Pharmacy = 'pharmacy';
    case Restaurant = 'restaurant';
    case Corporate = 'corporate';
    case Other = 'other';
}
