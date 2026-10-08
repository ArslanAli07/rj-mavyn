<?php

namespace Tests\Fixtures\Plugins;

final class FixtureController
{
    public static int $executed = 0;

    public function __invoke()
    {
        self::$executed++;

        return response()->json(['ok' => true]);
    }
}
