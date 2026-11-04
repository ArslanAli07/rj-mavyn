<?php

/**
 * One-off data fix (no core file changes). Works on any database Laravel supports
 * (SQLite locally, MySQL on live) by using the framework's DB connection.
 *
 * 1. HEIGHT (INCH) columns: supplier export mangles feet-inches ("5'10\"" -> "510\"").
 *    Recompute from the matching CENTIMETER table's HEIGHT (CM) as plain inches (1 decimal).
 * 2. Single dual-unit tables (alternating "(INCHES)","(CM)" columns, e.g. gloves):
 *    split into separate INCHES and CENTIMETER tables so the storefront CM/INCHES toggle works.
 *
 * Idempotent: only cells that still differ are rewritten; safe to run repeatedly.
 *
 * Run:  php bin/fix-sizechart-data.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$baseName = fn (string $c): string => preg_replace('/\s*\((INCH|CM)\)\s*$/i', '', trim($c));
$unitOf = fn (string $c): ?string => str_contains(strtolower($c), '(inch') ? 'in' : (str_contains(strtolower($c), '(cm)') ? 'cm' : null);

$fixedHeights = 0;
$splitTables = 0;
$productsTouched = 0;

$total = DB::table('products')->whereNotNull('size_chart')->where('size_chart', '!=', '')->count();
echo "products with size_chart: {$total}\n";

DB::table('products')
    ->whereNotNull('size_chart')
    ->where('size_chart', '!=', '')
    ->orderBy('id')
    ->chunkById(200, function ($products) use (&$fixedHeights, &$splitTables, &$productsTouched, $unitOf) {
        foreach ($products as $row) {
            $chart = is_string($row->size_chart) ? json_decode($row->size_chart, true) : (array) $row->size_chart;
            if (! is_array($chart)) {
                continue;
            }
            $changed = false;

            if (isset($chart['tables']) && is_array($chart['tables'])) {
                // --- split dual-unit single tables ---
                $newTables = [];
                foreach ($chart['tables'] as $tbl) {
                    $cols = $tbl['columns'] ?? [];
                    $hasIn = false;
                    $hasCm = false;
                    foreach ($cols as $i => $c) {
                        if ($i === 0) continue;
                        $u = $unitOf((string) $c);
                        if ($u === 'in') $hasIn = true;
                        if ($u === 'cm') $hasCm = true;
                    }
                    if ($hasIn && $hasCm && count($cols) > 2) {
                        foreach (['INCHES', 'CENTIMETER'] as $targetUnit) {
                            $keptIdx = [];
                            foreach ($cols as $i => $c) {
                                $u = $unitOf((string) $c);
                                if ($i === 0 || ($targetUnit === 'INCHES' && $u === 'in') || ($targetUnit === 'CENTIMETER' && $u === 'cm')) {
                                    $keptIdx[] = $i;
                                }
                            }
                            $newCols = array_map(fn ($i) => $cols[$i], $keptIdx);
                            $newRows = array_map(fn ($r) => array_values(array_intersect_key((array) $r, array_flip($keptIdx))), $tbl['rows'] ?? []);
                            $newTables[] = [
                                'chart' => $tbl['chart'] ?? 'Standard',
                                'unit' => $targetUnit,
                                'gender' => $tbl['gender'] ?? 'unisex',
                                'columns' => $newCols,
                                'rows' => $newRows,
                                'sizes' => $tbl['sizes'] ?? [],
                            ];
                            $splitTables++;
                        }
                        $changed = true;
                    } else {
                        $newTables[] = $tbl;
                    }
                }
                $chart['tables'] = $newTables;

                // --- fix HEIGHT (INCH) using CENTIMETER table (operate by reference) ---
                $cmTables = array_values(array_filter($chart['tables'], fn ($t) => ($t['unit'] ?? '') === 'CENTIMETER'));
                foreach ($chart['tables'] as $ti => &$itbl) {
                    if (($itbl['unit'] ?? '') !== 'INCHES' || empty($cmTables)) continue;
                    $icol = null;
                    foreach ($itbl['columns'] ?? [] as $i => $c) {
                        if (stripos((string) $c, 'HEIGHT') !== false && $unitOf((string) $c) === 'in') { $icol = $i; break; }
                    }
                    if ($icol === null) { continue; }
                    $match = null;
                    foreach ($cmTables as $ctbl) {
                        if (($ctbl['chart'] ?? '') === ($itbl['chart'] ?? '') && ($ctbl['gender'] ?? '') === ($itbl['gender'] ?? '')) { $match = $ctbl; break; }
                    }
                    $match = $match ?? $cmTables[0] ?? null;
                    if (! $match) continue;
                    $ccol = null;
                    foreach ($match['columns'] ?? [] as $i => $c) {
                        if (stripos((string) $c, 'HEIGHT') !== false && $unitOf((string) $c) === 'cm') { $ccol = $i; break; }
                    }
                    if ($ccol === null) continue;
                    $cmByKey = [];
                    foreach ($match['rows'] ?? [] as $r) {
                        $key = implode('|', array_slice((array) $r, 0, 3));
                        $cmByKey[$key] = $r[$ccol] ?? null;
                    }
                    $cmList = array_values(array_filter(array_column($match['rows'] ?? [], $ccol), fn ($v) => $v !== null && $v !== ''));
                    $idx = 0;
                    foreach ($itbl['rows'] ?? [] as $ri => $r) {
                        if (! isset($r[$icol])) continue;
                        $key = implode('|', array_slice((array) $r, 0, 3));
                        $cm = $cmByKey[$key] ?? null;
                        if ($cm === null) { $cm = $cmList[$idx] ?? null; }
                        $idx++;
                        if ($cm === null || ! is_numeric($cm)) continue;
                        $inches = round(((float) $cm) / 2.54, 1);
                        $newVal = number_format($inches, 1, '.', '');
                        if ((string) $r[$icol] !== $newVal) { $itbl['rows'][$ri][$icol] = $newVal; $fixedHeights++; $changed = true; }
                    }
                }
                unset($itbl);

                if ($changed && ! empty($chart['tables'][0])) {
                    $chart['columns'] = $chart['tables'][0]['columns'];
                    $chart['rows'] = $chart['tables'][0]['rows'];
                }
            }

            if ($changed) {
                DB::table('products')->where('id', $row->id)->update([
                    'size_chart' => json_encode($chart, JSON_UNESCAPED_SLASHES),
                ]);
                $productsTouched++;
            }
        }
    });

echo "products updated: {$productsTouched}, height cells fixed: {$fixedHeights}, tables split: {$splitTables}\n";
