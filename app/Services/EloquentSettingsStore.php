<?php

namespace App\Services;

use App\Models\Setting;
use Mavyn\Contracts\SettingsStore;

final class EloquentSettingsStore implements SettingsStore
{
    public function get(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }
}
