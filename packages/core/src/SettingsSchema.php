<?php

namespace Mavyn\Core;

final class SettingsSchema
{
    /** @return array<string, array{type: string, default: mixed, rules: list<string>, group: string, permission: string, secret: bool}> */
    public function definitions(): array
    {
        return json_decode(file_get_contents(__DIR__.'/settings.json'), true, 512, JSON_THROW_ON_ERROR);
    }
}
