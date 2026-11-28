<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    use EnumHelpers;

    case OpeningBalance = 'opening_balance';
    case Invoice = 'invoice';
    case Payment = 'payment';
    case CreditNote = 'credit_note';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';
}
