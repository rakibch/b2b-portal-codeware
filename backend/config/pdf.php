<?php

/*
| mPDF (carlos-meneses/laravel-mpdf). Bangla requires OpenType Layout (useOTL) so conjuncts
| (যুক্তবর্ণ), reph and pre-base vowel signs shape correctly. DejaVu Sans covers Latin and is
| used as the substitution font for any glyph missing from Noto Sans Bengali.
*/

return [
    'mode' => 'utf-8',
    'format' => 'A4',
    'default_font_size' => '10',
    'default_font' => 'dejavusans',
    'margin_left' => 12,
    'margin_right' => 12,
    'margin_top' => 14,
    'margin_bottom' => 18,
    'margin_header' => 6,
    'margin_footer' => 8,
    'orientation' => 'P',
    'title' => env('APP_NAME', 'B2B Wholesale Portal'),
    'subject' => '',
    'author' => env('APP_NAME', 'B2B Wholesale Portal'),
    'watermark' => '',
    'show_watermark' => false,
    'show_watermark_image' => false,
    'watermark_font' => 'dejavusans',
    'display_mode' => 'fullpage',
    'watermark_text_alpha' => 0.08,
    'watermark_image_path' => '',
    'watermark_image_alpha' => 0.2,
    'watermark_image_size' => 'D',
    'watermark_image_position' => 'P',
    'custom_font_dir' => resource_path('fonts/'),
    'custom_font_data' => [
        'notobengali' => [
            'R' => 'NotoSansBengali-Regular.ttf',
            'B' => 'NotoSansBengali-Bold.ttf',
            'useOTL' => 0xFF,
            'useKashida' => 0,
        ],
    ],
    'auto_language_detection' => false,
    'temp_dir' => storage_path('app/mpdf'),
    'pdfa' => false,
    'pdfaauto' => false,
    'use_active_forms' => false,
];
