<?php

namespace App\Enums;

enum CreditNoteStatus: string
{
    use EnumHelpers;

    case Issued = 'issued';
    case Void = 'void';
}
