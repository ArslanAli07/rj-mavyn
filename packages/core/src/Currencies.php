<?php

namespace Mavyn\Core;

use InvalidArgumentException;

final class Currencies
{
    public const SYMBOLS = ['PKR' => 'Rs.', 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'AUD' => 'A$', 'CAD' => 'C$', 'AED' => 'AED', 'SAR' => 'SAR', 'INR' => '₹'];

    // Match the legacy DECIMAL(10,2) item/order columns and bound total arithmetic.
    public const MAX_MINOR = 9999999999;

    public static function symbol(string $currency): string
    {
        return self::SYMBOLS[$currency] ?? throw new InvalidArgumentException('Unsupported two-decimal currency.');
    }

    public static function amount(mixed $value, bool $signed = false, bool $nullable = false): ?int
    {
        if ($nullable && $value === null) {
            return null;
        }
        if (! is_int($value) || $value > self::MAX_MINOR || $value < ($signed ? -self::MAX_MINOR : 0)) {
            throw new InvalidArgumentException('Use integer minor units within the supported money bounds.');
        }

        return $value;
    }
}
