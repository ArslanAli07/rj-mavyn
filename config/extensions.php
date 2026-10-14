<?php

return [
    'catalog' => env('MAVYN_EXTENSION_CATALOG', base_path('bootstrap/cache/extensions.json')),
    // Source metadata refresh is restricted to APP_ENV=local; production uses built catalogs.
    'live_manifests' => env('MAVYN_PLUGIN_DEV', true),
];
