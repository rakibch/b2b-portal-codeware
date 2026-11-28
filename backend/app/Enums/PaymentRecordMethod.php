<?php

namespace App\Enums;

enum PaymentRecordMethod: string
{
    use EnumHelpers;

    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';
    case Cash = 'cash';
    case Gateway = 'gateway';
}
