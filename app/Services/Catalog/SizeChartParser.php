<?php

namespace App\Services\Catalog;

use Illuminate\Support\Str;

/**
 * Reads the supplier size charts and turns them into two things the storefront
 * needs: printable measurement tables (supporting multiple units and demographics)
 * and orderable sizes.
 */
final class SizeChartParser
{
    /** Letter sizes the supplier uses in a leading column. */
    private const LETTER_SIZES = ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', '3XL', '4XL', '5XL', 'XXXL', 'XXXXL', 'ONE SIZE'];

    /**
     * @param  array<string, mixed>|null  $chart
     * @return array{
     *     name: string|null,
     *     diagram: string|null,
     *     how_to_measure: string|null,
     *     tables: array<int, array{
     *         chart: string,
     *         unit: string,
     *         gender: string,
     *         columns: array<int, string>,
     *         rows: array<int, array<int, string>>,
     *         sizes: array<int, string>
     *     }>,
     *     columns: array<int, string>,
     *     rows: array<int, array<int, string>>,
     *     sizes: array<int, string>,
     *     grouped_sizes: array<string, array<int, string>>
     * }|null
     */
    public function parse(?array $chart): ?array
    {
        if (! $chart) {
            return null;
        }

        $rawTables = $chart['tables'] ?? [];
        if (! is_array($rawTables) || empty($rawTables)) {
            return null;
        }

        $parsedTables = [];
        $allSizes = [];
        $groupedSizes = [];

        foreach ($rawTables as $table) {
            $rows = [];
            foreach ($table['rows'] ?? [] as $row) {
                $rows[] = array_map(fn ($cell) => trim((string) $cell), array_values((array) $row));
            }

            $start = $this->firstDataRow($rows);
            if ($start === null) {
                continue;
            }

            $headerRows = array_slice($rows, 0, $start);
            $dataRows = array_slice($rows, $start);
            $columns = $this->columns($headerRows, $dataRows);
            $tableSizes = $this->sizes($columns, $dataRows);

            $chartName = trim((string) ($table['chart'] ?? 'Standard'));
            $rawUnit = strtoupper(trim((string) ($table['unit'] ?? 'CENTIMETER')));
            $unit = match (true) {
                str_contains($rawUnit, 'INCH') => 'INCHES',
                default => 'CENTIMETER',
            };

            $gender = match (true) {
                str_contains(strtolower($chartName), 'women') => 'women',
                str_contains(strtolower($chartName), 'men') => 'men',
                default => 'unisex',
            };

            $groupKey = match ($gender) {
                'men' => 'Men',
                'women' => 'Women',
                default => 'Standard',
            };

            if (! isset($groupedSizes[$groupKey])) {
                $groupedSizes[$groupKey] = [];
            }
            foreach ($tableSizes as $sz) {
                if (! in_array($sz, $groupedSizes[$groupKey], true)) {
                    $groupedSizes[$groupKey][] = $sz;
                }
                if (! in_array($sz, $allSizes, true)) {
                    $allSizes[] = $sz;
                }
            }

            $parsedTables[] = [
                'chart' => $chartName,
                'unit' => $unit,
                'gender' => $gender,
                'columns' => $columns,
                'rows' => $dataRows,
                'sizes' => $tableSizes,
            ];
        }

        if (empty($parsedTables)) {
            return null;
        }

        $primary = $parsedTables[0];

        return [
            'name' => isset($chart['name']) && trim((string) $chart['name']) !== '' ? trim((string) $chart['name']) : null,
            'diagram' => isset($chart['diagram']) && trim((string) $chart['diagram']) !== '' ? trim((string) $chart['diagram']) : null,
            'how_to_measure' => isset($chart['how_to_measure']) && trim((string) $chart['how_to_measure']) !== '' ? trim((string) $chart['how_to_measure']) : null,
            'tables' => $parsedTables,
            'columns' => $primary['columns'],
            'rows' => $primary['rows'],
            'sizes' => $allSizes,
            'grouped_sizes' => $groupedSizes,
        ];
    }

    /**
     * The first row that carries a real size in its leading cell starts the data.
     *
     * @param  array<int, array<int, string>>  $rows
     */
    private function firstDataRow(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            $cells = array_values(array_filter($row, fn ($cell) => $cell !== ''));
            if ($cells !== [] && $this->isSize($cells[0])) {
                return $index;
            }
        }

        return null;
    }

    private function isSize(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        if (is_numeric($value)) {
            return true;
        }
        $normalised = strtoupper(str_replace([' ', '-'], '', $value));
        if (in_array($normalised, self::LETTER_SIZES, true)) {
            return true;
        }

        // Labels such as 2XL and 3XL.
        return (bool) preg_match('/^\d{0,2}XL{1,3}$/', $normalised);
    }

    /**
     * The last header row carries the column labels; earlier rows group them.
     *
     * @param  array<int, array<int, string>>  $headerRows
     * @param  array<int, array<int, string>>  $dataRows
     * @return array<int, string>
     */
    private function columns(array $headerRows, array $dataRows): array
    {
        $labels = $headerRows === [] ? [] : end($headerRows);
        $width = $dataRows === [] ? 0 : count($dataRows[0]);

        // Header rows keep the empty cell that "PRODUCT SIZE" and its friends
        // span across; measurement rows drop it. Align on the trailing labels.
        if ($width > 0 && count($labels) > $width) {
            $labels = array_slice($labels, count($labels) - $width);
        }
        while (count($labels) < $width) {
            $labels[] = '';
        }

        // Unlabelled grouping rows (a run of A/B/C letters only) carry no real
        // names; number them so the table stays readable.
        if (count(array_filter($labels, fn ($label) => $label !== '')) === 0) {
            $labels = array_map(fn ($index) => 'Column '.($index + 1), $labels);
        }

        return $labels;
    }

    /**
     * Build orderable size labels per measurement row.
     *
     * @param  array<int, string>  $columns
     * @param  array<int, array<int, string>>  $rows
     * @return array<int, string>
     */
    private function sizes(array $columns, array $rows): array
    {
        $indexes = [];
        foreach ($columns as $index => $column) {
            if (str_contains(strtoupper($column), 'SIZE')) {
                $indexes[] = $index;
            }
        }
        if ($indexes === []) {
            $indexes = array_slice(range(0, count($columns) - 1), 0, 1);
        }

        $sizes = [];
        foreach ($rows as $row) {
            $parts = [];
            foreach ($indexes as $index) {
                $value = trim((string) ($row[$index] ?? ''));
                if ($value === '' || ! $this->isSize($value)) {
                    continue;
                }
                $parts[] = $this->prefix($columns[$index] ?? '', $value).$value;
            }
            if ($parts === []) {
                continue;
            }
            $sizes[Str::slug(implode(' ', $parts))] = implode(' · ', $parts);
        }

        return array_values(array_unique($sizes));
    }

    /** Only label a measurement column when the row does not already say so. */
    private function prefix(string $column, string $value): string
    {
        $column = strtoupper(trim($column));
        $prefix = match (true) {
            str_contains($column, 'U.S') => 'US ',
            str_contains($column, 'EU SIZE') => 'EU ',
            str_contains($column, 'INTL') => '',
            default => '',
        };

        // Letter reads like a size on its own, so "XS" stays "XS".
        return is_numeric($value) ? $prefix : '';
    }
}
