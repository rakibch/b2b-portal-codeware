<?php

namespace App\Enums;

enum CartItemSource: string
{
    use EnumHelpers;

    case Catalog = 'catalog';
    case QuickOrder = 'quick_order';
    case Import = 'import';
    case Reorder = 'reorder';
}
