<?php

namespace App\Services\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Derives colour and gender facets for the catalogue from the product's own
 * title. There is no colour or gender column, but titles carry the hints the
 * workshop already writes ("Black & Brown Lambskin...", "Chappal for Mens"),
 * so the same wording powers both the dropdown options and the filtering.
 */
final class ProductTitleAttributes
{
    /**
     * Curated palette for a luxury leather store. Keyword matching runs over a
     * normalised, space-padded, apostrophe-free title, so selections like
     * "Men's", "MENS" or "Black & Tan" all match.
     */
    private const PALETTE = [
        'off-white' => ['off white'],
        'mustard' => ['mustard', 'yellow', 'camel'],
        'dark brown' => ['dark brown', 'espresso', 'chocolate'],
        'beige' => ['beige', 'cream', 'ivory', 'sand'],
        'grey' => ['grey', 'gray', 'charcoal', 'graphite'],
        'blue' => ['navy', 'blue'],
        'red' => ['red', 'maroon', 'burgundy', 'wine', 'crimson'],
        'green' => ['green', 'olive'],
        'orange' => ['orange', 'rust'],
        'brown' => ['brown', 'mocha', 'coffee'],
        'white' => ['white'],
        'tan' => ['tan'],
        'black' => ['black'],
    ];

    private const MALE_WORDS = [
        'mens', 'men', 'man', 'male', 'gents', 'gentlemen', 'him', 'masculine',
    ];

    private const FEMALE_WORDS = [
        'womens', 'women', 'woman', 'ladies', 'lady', 'female', 'her', 'feminine',
    ];

    /** Normalised, space-padded, apostrophe-free copy of a title. */
    public static function normalize(string $title): string
    {
        $normalized = trim((string) preg_replace("/[^\p{L}\p{N}]+/u", ' ', Str::lower(str_replace("'", '', $title))));

        return ' '.$normalized.' ';
    }

    /** Every colour in the palette found in the title, best museum names first. */
    /** @return list<string> */
    public static function colorsFor(string $title): array
    {
        $normalized = self::normalize($title);

        // Longest phrases first: "off white" and "dark brown" beat "white"
        // and "brown" where both could match.
        $keywords = collect(self::PALETTE)
            ->flatMap(fn (array $words, string $label) => collect($words)->map(fn (string $word) => ['label' => $label, 'word' => $word]))
            ->sortByDesc(fn (array $entry) => mb_strlen($entry['word']))
            ->values();

        $matched = [];
        foreach ($keywords as $keyword) {
            if (str_contains($normalized, ' '.$keyword['word'].' ') && ! in_array($keyword['label'], $matched, true)) {
                $matched[] = $keyword['label'];
            }
        }

        return $matched;
    }

    /** Men, women or unisex — unstated titles read as unisex. */
    public static function genderFor(string $title): string
    {
        $normalized = self::normalize($title);

        $matches = static fn (array $words): bool => collect($words)->contains(
            fn (string $word) => str_contains($normalized, ' '.$word.' ')
        );

        if ($matches(self::FEMALE_WORDS)) {
            return 'women';
        }

        if ($matches(self::MALE_WORDS)) {
            return 'men';
        }

        return 'unisex';
    }

    /** Distinct colour labels across a set of titles, display order. */
    /** @param Collection<int, string> $titles */
    public static function colors(Collection $titles): Collection
    {
        return $titles
            ->flatMap(fn (string $title) => self::colorsFor($title))
            ->unique()
            ->sortBy(fn (string $label) => array_search($label, array_keys(self::PALETTE), true))
            ->values();
    }

    /** Distinct genders across a set of titles ('men', 'women', 'unisex'). */
    /** @param Collection<int, string> $titles */
    public static function genders(Collection $titles): Collection
    {
        return $titles
            ->map(fn (string $title) => self::genderFor($title))
            ->unique()
            ->values();
    }

    /**
     * Applies colour and gender filters to a product query by resolving to the
     * matching product IDs (works across database engines; the app runs on
     * sqlite where word-boundary SQL matching is not available).
     */
    public static function apply(Builder $query, ?string $color, ?string $gender): void
    {
        if ($color === null && $gender === null) {
            return;
        }

        $rows = (clone $query)->select('id', 'title')->get();

        $ids = $rows
            ->filter(fn ($product) => self::matches($product->title, $color, $gender))
            ->pluck('id')
            ->all();

        // An impossible colour/gender pairing simply returns no products.
        $query->whereIn('id', $ids === [] ? [0] : $ids);
    }

    private static function matches(string $title, ?string $color, ?string $gender): bool
    {
        if ($color !== null && $color !== '') {
            if (! in_array($color, self::colorsFor($title), true)) {
                return false;
            }
        }

        if ($gender !== null && $gender !== '' && self::genderFor($title) !== $gender) {
            return false;
        }

        return true;
    }
}
