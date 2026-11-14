<?php

/**
 * Seeds the canonical product option matrix for the RJ Motorbike catalogue,
 * mirroring the option fields observed on leathercollection.com product pages
 * (verified against live product pages + their Help Center article):
 *
 * | Class (classified by category chain + title) | Groups                                                                                                  |
 * |----------------------------------------------|---------------------------------------------------------------------------------------------------------|
 * | moto-suit   (race suits / 1pc-2pc leathers)  | Leather (single) + Speed Hump (single) + Perforation (single) + Add-ons (multi) + Matching Gear (multi)  |
 * | moto-jacket (racing jackets)                 | Speed Hump + Perforation + Add-ons (jacket pricing) + Matching Gear (Gloves)                            |
 * | moto-pants  (racing pants)                   | Leather (pants pricing) + Add-ons                                                                       |
 * | fashion     (fashion jackets, coats, movies) | Lining Options (single) + Material Upgrade (single)                                                     |
 * | vest                                         | Lining Options (single)                                                                                 |
 * | fashion-pants                                | Leather (pants pricing)                                                                                 |
 * | none        (gloves, boots, chappals, bags,  | none — size only                                                                                       |
 * |              kilts, handbags, chaps)         |                                                                                                         |
 *
 * LC prices (USD): suit Leather Monaco +$99 / Kangaroo +$400; pants Leather Monaco +$50 /
 * Kangaroo +$200; Hump Ventilated +$30 / Hydro +$45; Perforation Full +$30; Add-ons
 * CE Level 2 $60 suit/$35 jacket, Back +$40, Chest +$40, Removable Lining $25/$15,
 * Thermal $45/$25, Knee Slider +$15; Matching Gloves +$125, Matching Boots +$175.
 *
 * Custom Size variants are re-priced per LC product type: suits +$125, racing
 * jackets +$75, everything else keeps +$95 (LC fashion jackets/vests/pants).
 *
 * Running it again is safe: each product's option groups are replaced with the
 * matrix definition. Note: replacing values invalidates cart rows that
 * reference the old option value ids.
 *
 * php themes/rjleather-theme/bin/seed-product-options.php [--dry]
 */

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dry = in_array('--dry', $argv, true);

/* ------------------------------------------------------------------ *
 *  Canonical matrices per product class (LC-faithful naming/prices).  *
 * ------------------------------------------------------------------ */
$matrices = [
    'moto-suit' => [
        ['name' => 'Leather', 'type' => 'single', 'values' => [
            ['Cowhide Leather', 0], ['Monaco Leather', 99], ['Kangaroo Leather', 400],
        ]],
        ['name' => 'Speed Hump', 'type' => 'single', 'values' => [
            ['No Hump', 0], ['Standard Hump', 0], ['Ventilated Hump', 30], ['Hydro Hump', 45],
        ]],
        ['name' => 'Perforation', 'type' => 'single', 'values' => [
            ['No Perforation', 0], ['Perforated Chest', 0], ['Perforated Front', 0], ['Full Perforated', 30],
        ]],
        ['name' => 'Add-ons', 'type' => 'multiple', 'values' => [
            ['CE Level 2 (EN 1621-1:2012)', 60],
            ['Back Protector (EN 1621-2:2014)', 40],
            ['Chest Protector (EN 1621-3:2018)', 40],
            ['Removable Lining', 25],
            ['Thermal Removable Lining', 45],
            ['Extra Knee Slider', 15],
        ]],
        ['name' => 'Matching Gear', 'type' => 'multiple', 'values' => [
            ['Matching Gloves', 125], ['Matching Boots', 175],
        ]],
    ],
    'moto-jacket' => [
        ['name' => 'Speed Hump', 'type' => 'single', 'values' => [
            ['No Hump', 0], ['Standard Hump', 0], ['Ventilated Hump', 30], ['Hydro Hump', 45],
        ]],
        ['name' => 'Perforation', 'type' => 'single', 'values' => [
            ['No Perforation', 0], ['Perforated Chest', 0], ['Perforated Front', 0], ['Full Perforated', 30],
        ]],
        ['name' => 'Add-ons', 'type' => 'multiple', 'values' => [
            ['CE Level 2 (EN 1621-1:2012)', 35],
            ['Back Protector (EN 1621-2:2014)', 40],
            ['Chest Protector (EN 1621-3:2018)', 40],
            ['Removable Lining', 15],
            ['Thermal Removable Lining', 25],
        ]],
        ['name' => 'Matching Gear', 'type' => 'multiple', 'values' => [
            ['Matching Gloves', 125],
        ]],
    ],
    'moto-pants' => [
        ['name' => 'Leather', 'type' => 'single', 'values' => [
            ['Cowhide Leather', 0], ['Monaco Leather', 50], ['Kangaroo Leather', 200],
        ]],
        ['name' => 'Add-ons', 'type' => 'multiple', 'values' => [
            ['CE Level 2 (EN 1621-1:2012)', 60],
            ['Back Protector (EN 1621-2:2014)', 40],
            ['Chest Protector (EN 1621-3:2018)', 40],
            ['Removable Lining', 25],
            ['Thermal Removable Lining', 45],
            ['Extra Knee Slider', 15],
        ]],
    ],
    'fashion' => [
        ['name' => 'Lining Options', 'type' => 'single', 'values' => [
            ['Regular Lining (100% Viscose)', 0], ['No Lining', 0], ['Sublimated Print Lining', 50],
        ]],
        ['name' => 'Material Upgrade', 'type' => 'single', 'values' => [
            ['Standard Hide', 0], ['Lambskin Shearling', 400],
        ]],
    ],
    'vest' => [
        ['name' => 'Lining Options', 'type' => 'single', 'values' => [
            ['Regular Lining (100% Viscose)', 0], ['No Lining', 0], ['Sublimated Print Lining', 50],
        ]],
    ],
    'fashion-pants' => [
        ['name' => 'Leather', 'type' => 'single', 'values' => [
            ['Cowhide Leather', 0], ['Monaco Leather', 50], ['Kangaroo Leather', 200],
        ]],
    ],
    'none' => [],
];

/* Custom Size surcharge per class, mirroring leathercollection.com. */
$customSizeFees = [
    'moto-suit' => 125,
    'moto-jacket' => 75,
    'moto-pants' => 95,
    'fashion' => 95,
    'vest' => 95,
    'fashion-pants' => 95,
    'none' => 95,
];

/* Classify a product: category chain first, then title keywords. The first
   matching rule wins. Chains like "MotoGP > Brands > Ducati" carry no product
   type, so racing titles ("MotoGP Racing Leathers Ducati") decide there. */
$classify = function (Product $product): string {
    $chain = [];
    $node = $product->category;
    while ($node) {
        $chain[] = mb_strtolower(trim($node->name));
        $node = $node->parent_id ? Category::find($node->parent_id) : null;
    }
    $chainPath = implode(' > ', $chain);
    $title = mb_strtolower($product->title);
    $has = fn (string $needle): bool => str_contains($chainPath, $needle);

    /* Size-only families, by category or title. */
    if ($has('glove') || $has('footwear') || $has('chappal') || $has('handbag')
        || $has('kilt') || $has('skirt') || $has('chap') || $has('bags')) {
        return 'none';
    }
    if (preg_match('/\bgloves?\b|\bboots?\b|\bshoes?\b|\bsandals?\b|\bchappals?\b|\bhandbags?\b|\bkilts?\b|\bskirt|\bchaps?\b|\bbags?\b|paddle case/i', $title)) {
        return 'none';
    }

    $isSuit = (bool) preg_match('/\bsuits?\b|\bleathers\b|\b1\s?piece\b|\b2\s?piece\b|one piece|two piece/i', $title);
    $isJacket = (bool) preg_match('/jacket/i', $title);
    $isPants = (bool) preg_match('/\bpants\b|\btrousers?\b/i', $title);

    /* Racing chains: MotoGP trees, motorcycle race suits, race branches. */
    if ($has('motogp') || $has('race suit') || $has('moto speeds') || preg_match('/\brace\b/', $chainPath)) {
        if ($isSuit) return 'moto-suit';
        if ($isPants) return 'moto-pants';
        if ($isJacket) return 'moto-jacket';
        return 'moto-suit'; // racing replica fallback (e.g. "Motorcycle Leathers White")
    }

    /* Motorcycle tree: LC gives every moto jacket the racing matrix except
       its "biker style" fashion line, which only carries Lining Options. */
    if ($has('motorcycle')) {
        if ($isJacket || $has('jackets')) {
            return preg_match('/biker style|bikers fashion/i', $title) ? 'fashion' : 'moto-jacket';
        }
        if ($has('vests')) return 'vest';
        if ($has('pants')) return 'moto-pants';
        if ($isSuit) return 'moto-suit';
        if ($isJacket) return 'moto-jacket';
        if ($isPants) return 'moto-pants';
    }

    /* Fashion tree + custom + uncategorized: decide by title keywords. */
    if ($isSuit) return 'moto-suit';
    if ($isPants) return 'fashion-pants';
    if ($isJacket) return 'fashion';
    if (preg_match('/\bvest\b|waistcoat/i', $title)) return 'vest';
    if (preg_match('/\bcoat\b|trench|blazer|parka/i', $title)) return 'fashion';

    return 'none';
};

$deleteGroups = function (Product $product): void {
    DB::table('product_option_values')
        ->whereIn('product_option_group_id', fn ($q) => $q->select('id')->from('product_option_groups')->where('product_id', $product->id))
        ->delete();
    DB::table('product_option_groups')->where('product_id', $product->id)->delete();
};

$createGroups = function (Product $product, array $definition) use ($deleteGroups) {
    $deleteGroups($product);

    $currency = $product->getRawOriginal('price_currency');
    $order = 0;
    foreach ($definition as $group) {
        $groupId = DB::table('product_option_groups')->insertGetId([
            'product_id' => $product->id,
            'name' => $group['name'],
            'selection_type' => $group['type'],
            'min_selections' => 0,
            'max_selections' => $group['type'] === 'single' ? 1 : null,
            'is_required' => false,
            'is_active' => true,
            'sort_order' => $order++,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $valueRows = [];
        $priceRows = [];
        foreach ($group['values'] as $index => [$name, $price]) {
            $valueRows[] = [
                'product_option_group_id' => $groupId,
                'name' => $name,
                'price_adjustment' => $price,
                'price_currency' => $currency,
                'is_active' => true,
                'sort_order' => $index,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('product_option_values')->insert($valueRows);
        // The pricing layer resolves storefront prices from currency_prices;
        // the stamped column is only the legacy source of truth.
        $valueIds = DB::table('product_option_values')->where('product_option_group_id', $groupId)->orderBy('id')->pluck('id');
        foreach ($group['values'] as $index => [$name, $price]) {
            $priceRows[] = [
                'kind' => 'option',
                'entity_id' => $valueIds[$index],
                'currency' => $currency,
                'amount_minor' => (int) round($price * 100),
                'sale_minor' => null,
                'minimum_minor' => null,
                'maximum_minor' => null,
            ];
        }
        DB::table('currency_prices')->insert($priceRows);
    }
};

/* Re-price the Custom Size variant to the LC surcharge for this class. */
$updateCustomSize = function (Product $product, string $class) use ($customSizeFees): void {
    $fee = $customSizeFees[$class];
    if ($fee === 95) {
        return; // current surcharge already matches
    }
    $delta = (int) round(($fee - 95) * 100);
    $variants = DB::table('product_variants')->where('product_id', $product->id)->where('name', 'like', '%Custom Size%')->get(['id']);
    foreach ($variants as $variant) {
        $row = DB::table('product_variants')->where('id', $variant->id)->first(['id', 'price', 'discount_price', 'name']);
        if (! $row) {
            continue;
        }
        $newPrice = (int) round(((float) $row->price) * 100) + $delta;
        $newDiscount = $row->discount_price !== null
            ? (int) round(((float) $row->discount_price) * 100) + $delta
            : null;
        DB::table('product_variants')->where('id', $variant->id)->update([
            'price' => $newPrice / 100,
            'discount_price' => $newDiscount === null ? null : $newDiscount / 100,
            'name' => sprintf('Custom Size (+ $%.2f)', $fee),
            'updated_at' => now(),
        ]);
        DB::table('currency_prices')->where(['kind' => 'variant', 'entity_id' => $variant->id])->update([
            'amount_minor' => DB::raw("amount_minor + {$delta}"),
            'sale_minor' => DB::raw($newDiscount === null ? 'sale_minor' : "CASE WHEN sale_minor IS NULL THEN NULL ELSE sale_minor + {$delta} END"),
        ]);
    }
};

$query = Product::query()->with('category')->orderBy('id');
$total = (clone $query)->count();
$processed = 0;
$skipped = 0;
$summary = [];

set_time_limit(0);
DB::transaction(function () use ($query, $total, &$processed, &$skipped, &$summary, $matrices, $classify, $createGroups, $deleteGroups, $updateCustomSize, $dry) {
    $query->chunkById(200, function ($products) use ($total, &$processed, &$skipped, &$summary, $matrices, $classify, $createGroups, $deleteGroups, $updateCustomSize, $dry) {
        foreach ($products as $product) {
            $class = $classify($product);
            $definition = $matrices[$class];

            if (empty($definition)) {
                $existing = DB::table('product_option_groups')->where('product_id', $product->id)->count();
                if ($existing === 0) {
                    $skipped++;
                    continue;
                }
                if (! $dry) {
                    $deleteGroups($product);
                }
                $summary[$class.' (cleared)'] = ($summary[$class.' (cleared)'] ?? 0) + 1;
                $processed++;
                continue;
            }

            if (! $dry) {
                $createGroups($product, $definition);
                $updateCustomSize($product, $class);
            }
            $label = $class.': '.implode(' + ', array_map(fn ($g) => $g['name'], $definition));
            $summary[$label] = ($summary[$label] ?? 0) + 1;
            $processed++;
        }
        echo '  '.$processed.' / '.$total." products processed\n";
        @flush();
        @ob_flush();
    });
}, 3);

// The pricing service caches resolved prices; one bump refreshes them all.
if (! $dry && class_exists(\App\Services\Pricing\PricingService::class)) {
    $pricing = app(\App\Services\Pricing\PricingService::class);
    if (method_exists($pricing, 'installed') && $pricing->installed() && method_exists($pricing, 'bump')) {
        $pricing->bump();
    }
}

echo "Products: {$total}, processed: {$processed}, untouched: {$skipped}\n";
foreach ($summary as $label => $count) {
    echo str_pad((string) $count, 6).'  '.$label."\n";
}
echo $dry ? "DRY RUN — nothing written.\n" : "Done.\n";
