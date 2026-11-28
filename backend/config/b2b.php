<?php

return [
    'supported_locales' => ['en', 'bn'],

    'currency' => [
        'code' => 'BDT',
        'symbol' => '৳',
    ],

    // Document number prefixes: PREFIX-YYYY-000001
    'sequences' => [
        'order' => 'ORD',
        'invoice' => 'INV',
        'challan' => 'DC',
        'rfq' => 'RFQ',
        'payment' => 'PAY',
        'credit_note' => 'CN',
    ],

    'kyc' => [
        'disk' => env('KYC_DISK', 'local'), // must be a PRIVATE disk
        'max_file_kb' => (int) env('KYC_MAX_FILE_KB', 5120),
        'allowed_mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
    ],

    'payments' => [
        'slip_disk' => env('PAYMENT_SLIP_DISK', 'local'),
        'default_gateway' => env('PAYMENT_GATEWAY', 'mock'),
    ],

    'invitations' => [
        'expires_hours' => (int) env('INVITATION_EXPIRES_HOURS', 72),
    ],

    'rfq' => [
        'default_validity_days' => (int) env('RFQ_VALIDITY_DAYS', 7),
    ],

    'import' => [
        'max_rows' => (int) env('QUICK_ORDER_IMPORT_MAX_ROWS', 500),
        'max_file_kb' => (int) env('QUICK_ORDER_IMPORT_MAX_KB', 2048),
    ],
];
