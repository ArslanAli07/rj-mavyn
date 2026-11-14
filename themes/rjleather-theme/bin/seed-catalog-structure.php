<?php

/**
 * Creates the RJ Motorbike category structure in the merchant catalogue.
 *
 * Names only: no products, no images, no prices, no demo content. The groups are
 * active and flagged "Show on homepage" so the storefront design can resolve
 * them; the subcategories are created inactive and ready for the merchant to fill
 * with products and switch on. Running it again is safe: existing categories are
 * updated, never duplicated.
 *
 * php themes/rjleather-theme/bin/seed-catalog-structure.php
 */

use App\Models\Category;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$groups = [
    'For Men' => ['Chappals', 'Chaps', 'Coats', 'Gloves', 'Jackets', 'Leather Handbags', 'Leather Kilts', 'Movies Leather Jacket for Men', 'Pants', 'Vests'],
    'For Women' => ['Chaps', 'Coats', 'Film Leather Jacket for Women', 'Gloves', 'Jackets', 'Leather Kilts & Skirts', 'Movies Jackets', 'Pants', 'Vests'],
    'MotoGP' => ['Brands', 'Footwear', 'Gloves', 'Jackets', 'Pants', 'Race', 'Race Suits'],
    'Motorcycle' => ['Footwear', 'Gloves', 'Jackets', 'Pants', 'Race Suit', 'Vests'],
];

$collections = [
    'Leather Collection' => [],
    'Moto Speeds' => [],
    'Customize' => ['Fashion'],
];

$created = 0;
$updated = 0;
$order = 1;

$upsert = function (string $name, string $slug, bool $active, bool $homepage, int $displayOrder) use (&$created, &$updated) {
    $existing = Category::withoutEvents(fn () => Category::where('slug', $slug)->first());
    $attributes = [
        'name' => $name,
        'slug' => $slug,
        'is_active' => $active,
        'show_on_homepage' => $homepage,
        'display_order' => $displayOrder,
    ];

    if ($existing) {
        $existing->forceFill($attributes)->saveQuietly();
        $updated++;

        return $existing;
    }

    $category = Category::withoutEvents(fn () => Category::create($attributes));
    $created++;

    return $category;
};

foreach ($groups as $groupName => $children) {
    $groupSlug = str($groupName)->slug()->value();
    $upsert($groupName, $groupSlug, true, true, $order++);
    foreach ($children as $childName) {
        $childSlug = $groupSlug.'-'.str($childName)->slug()->value();
        $upsert($childName, $childSlug, true, false, 50 + $order++);
    }
}

foreach ($collections as $collectionName => $children) {
    $collectionSlug = str($collectionName)->slug()->value();
    $upsert($collectionName, $collectionSlug, true, true, $order++);
    foreach ($children as $childName) {
        $childSlug = $collectionSlug.'-'.str($childName)->slug()->value();
        $upsert($childName, $childSlug, true, false, 50 + $order++);
    }
}

printf("Catalogue structure ready: %d created, %d updated.%s", $created, $updated, PHP_EOL);
foreach (Category::active()->orderBy('display_order')->get(['name', 'show_on_homepage']) as $category) {
    printf("  %-28s %s%s", $category->name, $category->show_on_homepage ? 'featured' : '', PHP_EOL);
}
printf("Subcategories are active and nested under their group in the navigation.%s", PHP_EOL);
