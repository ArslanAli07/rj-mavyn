<?php

return [
    'client' => env('MAVYN_CLIENT_ID'),
    'manifest' => env('MAVYN_RELEASE_MANIFEST', base_path('bootstrap/cache/release.json')),
    'backup_max_age_hours' => 24,
];
