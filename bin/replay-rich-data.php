<?php

/**
 * Replay the finished rich product data (exported from the fixed local DB) onto any database.
 * No supplier catalog needed. DB-agnostic (works on MySQL / SQLite via Laravel's DB layer).
 *
 * Semantics (mirrors catalog:sync-rich-data, safe to re-run):
 *   - size_chart: OVERWRITTEN for every SKU found in the export (this is the fix).
 *   - option groups: created only if the product has no group with the same name (existing untouched).
 *   - variants: created only if the product has no variant with the same name (existing untouched).
 *   - SKUs missing on live are reported and skipped.
 *
 * Upload BOTH files to ~/mavyn/bin/ (or anywhere in the project):
 *   - replay-rich-data.php          (this script)
 *   - rich-data-export.json         (the export)
 *
 * Run:  php bin/replay-rich-data.php /path/to/rich-data-export.json
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

$dataFile = $argv[1] ?? null;
if ($dataFile === null) {
    echo "No export path given.\n";
    echo "Usage: php bin/replay-rich-data.php /path/to/rich-data-export.json\n";
    exit(1);
}
if (! is_file($dataFile)) {
    echo "Export file NOT FOUND at: {$dataFile}\n";
    echo "cwd: ".getcwd()."\n";
    echo "Looking for likely files nearby:\n";
    foreach (glob(dirname(__DIR__).'/*.json') as $f) {
        $sz = round(@filesize($f) / 1024 / 1024, 1);
        echo "  {$f} ({$sz} MB)\n";
    }
    foreach (glob(dirname(__DIR__, 2).'/*.json') as $f) {
        $sz = round(@filesize($f) / 1024 / 1024, 1);
        echo "  {$f} ({$sz} MB)\n";
    }
    exit(1);
}
echo 'using export: '.$dataFile.' ('.round(filesize($dataFile) / 1024 / 1024, 1)." MB)\n";
$export = json_decode((string) file_get_contents($dataFile), true);
if (! is_array($export) || ($export['format'] ?? null) !== 1) {
    echo "Invalid export file.\n";
    exit(1);
}

$chartsUpdated = 0;
$groupsCreated = 0;
$valuesCreated = 0;
$variantsCreated = 0;
$missingSkus = [];

$live = DB::table('products')->get(['id', 'sku']);
$bySku = [];
foreach ($live as $p) {
    $bySku[$p->sku] = $p->id;
}
echo 'live products: '.count($bySku)."\n";

// existing option group names per product
$existingGroups = [];
foreach (DB::table('product_option_groups')->get(['id', 'product_id', 'name']) as $g) {
    $existingGroups[$g->product_id][$g->name] = $g->id;
}
// existing variant names per product
$existingVariants = [];
foreach (DB::table('product_variants')->get(['id', 'product_id', 'name']) as $v) {
    $existingVariants[$v->product_id][$v->name] = $v->id;
}

DB::beginTransaction();
try {
    // --- size charts ---
    foreach ($export['size_charts'] ?? [] as $sku => $chart) {
        $id = $bySku[$sku] ?? null;
        if (! $id) { $missingSkus[$sku] = true; continue; }
        DB::table('products')->where('id', $id)->update([
            'size_chart' => json_encode($chart, JSON_UNESCAPED_SLASHES),
        ]);
        $chartsUpdated++;
    }

    // --- option groups + values ---
    foreach ($export['options'] ?? [] as $sku => $groups) {
        $id = $bySku[$sku] ?? null;
        if (! $id) { $missingSkus[$sku] = true; continue; }
        foreach ($groups as $group) {
            $gid = $existingGroups[$id][$group['name']] ?? null;
            if ($gid === null) {
                $gid = DB::table('product_option_groups')->insertGetId([
                    'product_id' => $id,
                    'name' => $group['name'],
                    'selection_type' => $group['selection_type'],
                    'min_selections' => $group['min_selections'],
                    'max_selections' => $group['max_selections'],
                    'is_required' => $group['is_required'],
                    'is_active' => $group['is_active'],
                    'sort_order' => $group['sort_order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $existingGroups[$id][$group['name']] = $gid;
                $groupsCreated++;
            }
            // values: only for newly created groups (existing groups stay untouched)
            $hasValues = DB::table('product_option_values')->where('product_option_group_id', $gid)->exists();
            if ($gid !== null && ! $hasValues) {
                foreach ($group['values'] as $val) {
                    DB::table('product_option_values')->insert([
                        'product_option_group_id' => $gid,
                        'name' => $val['name'],
                        'price_adjustment' => $val['price_adjustment'],
                        'price_currency' => $val['price_currency'],
                        'is_active' => $val['is_active'],
                        'sort_order' => $val['sort_order'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $valuesCreated++;
                }
            }
        }
    }

    // --- variants ---
    foreach ($export['variants'] ?? [] as $sku => $variants) {
        $id = $bySku[$sku] ?? null;
        if (! $id) { $missingSkus[$sku] = true; continue; }
        foreach ($variants as $variant) {
            if (isset($existingVariants[$id][$variant['name']])) continue;
            DB::table('product_variants')->insert([
                'product_id' => $id,
                'name' => $variant['name'],
                'weight' => $variant['weight'],
                'price' => $variant['price'],
                'discount_price' => $variant['discount_price'],
                'price_currency' => $variant['price_currency'],
                'sort_order' => $variant['sort_order'],
                'is_default' => false,
                'is_active' => true,
                'in_stock' => true,
                'track_inventory' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $existingVariants[$id][$variant['name']] = true;
            $variantsCreated++;
        }
    }

    DB::commit();
} catch (Throwable $e) {
    DB::rollBack();
    echo 'FAILED, rolled back: '.$e->getMessage()."\n";
    exit(1);
}

// refresh pricing cache like the sync command does
try {
    app(App\Services\Pricing\PricingService::class)->bump();
    Cache::flush();
} catch (Throwable $e) {
    echo "(cache refresh skipped: {$e->getMessage()})\n";
}

echo "DONE\n";
echo "size charts updated: {$chartsUpdated}\n";
echo "option groups created: {$groupsCreated} (values: {$valuesCreated})\n";
echo "variants created: {$variantsCreated}\n";
if ($missingSkus) {
    echo 'SKUs not found on live (skipped): '.count($missingSkus)."\n";
    echo implode(', ', array_slice(array_keys($missingSkus), 0, 10)).(count($missingSkus) > 10 ? " ...\n" : "\n");
}
