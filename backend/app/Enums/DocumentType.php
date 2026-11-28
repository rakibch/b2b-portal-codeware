<?php

namespace App\Enums;

enum DocumentType: string
{
    use EnumHelpers;

    case TradeLicense = 'trade_license';
    case Nid = 'nid';
    case TinCertificate = 'tin_certificate';
    case BinCertificate = 'bin_certificate';
    case Other = 'other';
}
