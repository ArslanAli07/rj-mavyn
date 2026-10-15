<?php

namespace Mavyn\Core\Pricing;

/** SQL JSON columns may reorder object keys; list order and scalar types remain significant. */
final class QuoteFingerprint
{
    public static function of(array $quote): string
    {
        return hash('sha256', json_encode(self::canonical($quote), JSON_THROW_ON_ERROR));
    }

    private static function canonical(array $data): array
    {
        foreach ($data as &$value) {
            if (is_array($value)) {
                $value = self::canonical($value);
            }
        }
        unset($value);
        if (! array_is_list($data)) {
            ksort($data, SORT_STRING);
        }

        return $data;
    }
}
