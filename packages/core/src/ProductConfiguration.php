<?php

namespace Mavyn\Core;

/** Canonical configuration identity, independent of persistence and catalog models. */
final class ProductConfiguration
{
    public static function normalizeSpecialRequest(?string $request): ?string
    {
        $value = trim((string) $request);

        return $value === '' ? null : mb_substr($value, 0, 255);
    }

    /** @param array<int, int|string> $optionValueIds */
    public static function signature(int $productId, ?int $variantId, array $optionValueIds = [], ?string $specialRequest = null): string
    {
        $ids = array_values(array_unique(array_filter(array_map(intval(...), $optionValueIds))));
        sort($ids, SORT_NUMERIC);

        return hash('sha256', json_encode([
            'product_id' => $productId,
            'variant_id' => $variantId,
            'option_ids' => $ids,
            'special_request' => self::normalizeSpecialRequest($specialRequest) ?? '',
        ], JSON_THROW_ON_ERROR));
    }
}
