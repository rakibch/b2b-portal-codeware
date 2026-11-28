<?php

namespace App\Enums;

enum PaymentMethod: string
{
    use EnumHelpers;

    case Credit = 'credit';
    case Cod = 'cod';
    case BankTransfer = 'bank_transfer';
    case Gateway = 'gateway';
}
