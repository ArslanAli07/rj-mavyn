<?php

/**
 * ONE-OFF (local): export the finished rich product data from the local, already-fixed DB
 * so it can be replayed on the live server without the supplier catalog export.
 *
 * Produces: rich-data-export.json  { size_charts: {SKU: chart}, options: {SKU: [groups]}, variants: {SKU: [variants]} }
 *
 * Run:  php bin/export-rich-data.php            (writes to storage/app/rich-data-export.json)
 *       php bin/export-rich-data.php /path/out.json
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$outPath = $argv[1] ?? storage_path('app/rich-data-export.json');

$export = ['format' => 1, 'generated_at' => date('c'), 'size_charts' => [], 'options' => [], 'variants' => []];

// 1. size charts keyed by SKU
$skus = [];
foreach (DB::table('products')->whereNotNull('size_chart')->orderBy('id')->get(['id', 'sku', 'size_chart']) as $p) {
    $chart = $p->size_chart;
    $chart = is_string($chart) ? json_decode($chart, true) : $chart;
    if (! is_array($chart) || empty($chart['tables'])) {
        continue; // only export rich multi-table charts
    }
    $export['size_charts'][$p->sku] = $chart;
    $skus[$p->id] = $p->sku;
}
echo 'size charts exported: '.count($export['size_charts'])."\n";

// 2. option groups + values keyed by SKU
$groups = DB::table('product_option_groups')->orderBy('id')->get();
$values = DB::table('product_option_values')->orderBy('product_option_group_id')->orderBy('sort_order')->get()->groupBy('product_option_group_id');
$productIds = array_map(fn ($id) => (int) $id, array_keys($skus));
foreach ($groups as $g) {
    $sku = $skus[$g->product_id] ?? null;
    if (! $sku) continue;
    $vals = [];
    foreach ($values->get($g->id, collect()) as $v) {
        $vals[] = [
            'name' => $v->name,
            'price_adjustment' => $v->price_adjustment,
            'price_currency' => $v->price_currency,
            'is_active' => (bool) $v->is_active,
            'sort_order' => (int) $v->sort_order,
        ];
    }
    $export['options'][$sku][] = [
        'name' => $g->name,
        'selection_type' => $g->selection_type,
        'min_selections' => (int) $g->min_selections,
        'max_selections' => $g->max_selections !== null ? (int) $g->max_selections : null,
        'is_required' => (bool) $g->is_required,
        'is_active' => (bool) $g->is_active,
        'sort_order' => (int) $g->sort_order,
        'values' => $vals,
    ];
}
echo 'products with options exported: '.count($export['options'])."\n";

// 3. variants keyed by SKU (insert-if-missing on replay)
foreach (DB::table('product_variants')->orderBy('product_id')->orderBy('sort_order')->get() as $v) {
    $sku = $skus[$v->product_id] ?? null;
    if (! $sku) continue;
    $export['variants'][$sku][] = [
        'name' => $v->name,
        'weight' => $v->weight,
        'price' => $v->price,
        'discount_price' => $v->discount_price,
        'price_currency' => $v->price_currency,
        'sort_order' => (int) $v->sort_order,
    ];
}
echo 'products with variants exported: '.count($export['variants'])."\n";

file_put_contents($outPath, json_encode($export, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo 'written: '.$outPath.' ('.round(filesize($outPath) / 1024 / 1024, 1)." MB)\n";
