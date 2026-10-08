<?php

namespace Mavyn\Contracts;

/** Storage adapter boundary. Values are scalars, arrays, or null. */
interface SettingsStore
{
    public function get(string $key, mixed $default = null): mixed;
}
