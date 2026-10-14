<?php

return [
    'client' => env('MAVYN_CLIENT_ID'),
    'bootstrap_admin' => [
        'name' => env('MAVYN_ADMIN_NAME', 'Store Owner'),
        'email' => env('MAVYN_ADMIN_EMAIL'),
        'password' => env('MAVYN_ADMIN_PASSWORD'),
    ],
    'manifest' => env('MAVYN_RELEASE_MANIFEST', base_path('bootstrap/cache/release.json')),
    'backup_max_age_hours' => 24,
];
