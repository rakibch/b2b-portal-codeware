<?php

namespace App\Enums;

enum PriceSource: string
{
    use EnumHelpers;

    case Base = 'base';
    case Tier = 'tier';
    case Group = 'group';
    case Contract = 'contract';
    case Rfq = 'rfq';
}
