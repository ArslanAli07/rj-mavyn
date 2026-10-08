<?php

namespace Mavyn\Contracts\Plugins;

use InvalidArgumentException;

final readonly class ProductDisplay
{
    /** @param list<string> $notices */
    public function __construct(public int $productId, public array $notices = [])
    {
        if ($productId < 1 || ! array_is_list($notices) || count($notices) > 20) {
            throw new InvalidArgumentException('Invalid display payload.');
        }
        foreach ($notices as $notice) {
            if (! is_string($notice) || strlen($notice) > 2000) {
                throw new InvalidArgumentException('Invalid notice.');
            }
        }
    }
}
