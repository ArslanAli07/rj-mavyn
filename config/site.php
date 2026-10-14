<?php

return [

    'name' => env('APP_NAME', 'Store'),
    'tagline' => env('SITE_TAGLINE', ''),
    'email' => env('SITE_EMAIL', ''),
    'phone' => env('SITE_PHONE', ''),
    'whatsapp' => env('SITE_WHATSAPP', ''),
    'address' => env('SITE_ADDRESS', ''),
    'city' => env('SITE_CITY', ''),
    'country_code' => env('SITE_COUNTRY_CODE', 'PK'),
    'country' => 'Pakistan',
    'currency' => 'PKR',
    'currency_symbol' => 'Rs.',
    'timezone' => 'Asia/Karachi',

    'business_hours' => [
        'Monday - Friday' => '9:00 AM – 9:00 PM',
        'Saturday' => '10:00 AM – 8:00 PM',
        'Sunday' => '11:00 AM – 6:00 PM',
    ],

    'social' => [
        'instagram' => env('SITE_INSTAGRAM', ''),
        'whatsapp' => env('SITE_WHATSAPP', ''),
    ],

    'payment' => [
        'easypaisa' => [
            'account' => env('EASYPAISA_ACCOUNT', ''),
            'name' => env('EASYPAISA_NAME', ''),
        ],
        'jazzcash' => [
            'account' => env('JAZZCASH_ACCOUNT', ''),
            'name' => env('JAZZCASH_NAME', ''),
        ],
        'bank' => [
            'bank_name' => env('BANK_NAME', ''),
            'account' => env('BANK_ACCOUNT', ''),
            'iban' => env('BANK_IBAN', ''),
            'title' => env('BANK_TITLE', ''),
        ],
    ],

    'delivery' => [
        'min_order' => 500,
        'free_above' => 2000,
        'standard_fee' => 300,
        'express_fee' => 400,
        'same_day_cutoff' => '13:00', // 1 PM
        'advance_notice_hours' => 24,
    ],

    'tax_rate' => 0, // 0% — included in price

    'per_page' => 12, // products per page on shop
];
