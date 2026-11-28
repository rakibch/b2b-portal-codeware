<?php

namespace App\Enums;

enum Role: string
{
    use EnumHelpers;

    case SuperAdmin = 'super_admin';
    case SalesManager = 'sales_manager';
    case WarehouseStaff = 'warehouse_staff';
    case BuyerAdmin = 'buyer_admin';
    case BuyerStaff = 'buyer_staff';

    /** @return list<self> */
    public static function staffRoles(): array
    {
        return [self::SuperAdmin, self::SalesManager, self::WarehouseStaff];
    }

    /** @return list<self> */
    public static function buyerRoles(): array
    {
        return [self::BuyerAdmin, self::BuyerStaff];
    }

    public function isStaff(): bool
    {
        return in_array($this, self::staffRoles(), true);
    }
}
