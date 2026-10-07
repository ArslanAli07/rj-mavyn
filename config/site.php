<?php

return [

    'name'         => env('APP_NAME', 'Happy Tea'),
    'tagline'      => 'Crafted with love, served with soul',
    'email'        => env('SITE_EMAIL', 'hello@happyteaskt.com'),
    'phone'        => env('SITE_PHONE', '03328437863'),
    'whatsapp'     => env('SITE_WHATSAPP', '+923328437863'),
    'address'      => env('SITE_ADDRESS', 'Main Boulevard, Model Town, Sialkot, Pakistan'),
    'city'         => 'Sialkot',
    'country'      => 'Pakistan',
    'currency'     => 'PKR',
    'currency_symbol' => 'Rs.',
    'timezone'     => 'Asia/Karachi',

    'business_hours' => [
        'Monday - Friday' => '9:00 AM – 9:00 PM',
        'Saturday'        => '10:00 AM – 8:00 PM',
        'Sunday'          => '11:00 AM – 6:00 PM',
    ],

    'social' => [
        'instagram' => env('SITE_INSTAGRAM', 'https://www.instagram.com/happyteaskt'),
        'whatsapp'  => env('SITE_WHATSAPP', '+923328437863'),
    ],

    'payment' => [
        'easypaisa' => [
            'account' => env('EASYPAISA_ACCOUNT', '0327-9004305'),
            'name'    => env('EASYPAISA_NAME', 'Happy Tea'),
        ],
        'jazzcash' => [
            'account' => env('JAZZCASH_ACCOUNT', '0327-9004305'),
            'name'    => env('JAZZCASH_NAME', 'Happy Tea'),
        ],
        'bank' => [
            'bank_name' => env('BANK_NAME', 'Meezan Bank'),
            'account'   => env('BANK_ACCOUNT', '0123456789012345'),
            'iban'      => env('BANK_IBAN', 'PK12MEZN0001234567890123'),
            'title'     => env('BANK_TITLE', 'Happy Tea'),
        ],
    ],

    'delivery' => [
        'min_order'      => 500,
        'free_above'     => 2000,
        'standard_fee'   => 300,
        'express_fee'    => 400,
        'same_day_cutoff' => '13:00', // 1 PM
        'advance_notice_hours' => 24,
    ],

    'tax_rate' => 0, // 0% — included in price

    'per_page' => 12, // products per page on shop
];
