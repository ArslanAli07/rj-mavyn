<?php

return [
    'client' => env('MAVYN_CLIENT_ID'),
    'demo_seed' => env('MAVYN_DEMO_SEED', false),
    'manifest' => env('MAVYN_RELEASE_MANIFEST', base_path('bootstrap/cache/release.json')),
    'backup_max_age_hours' => 24,
];
