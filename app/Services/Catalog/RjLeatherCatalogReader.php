<?php

namespace App\Services\Catalog;

/**
 * Walks the supplier export on disk and returns its category tree and
 * product folders. The export mirrors the storefront taxonomy: a group
 * folder, optional subcategory folders and a "products" folder holding one
 * folder per product with its JSON payload and photographs.
 */
final class RjLeatherCatalogReader
{
    public function __construct(private readonly string $root) {}

    public function exists(): bool
    {
        return is_dir($this->root);
    }

    /**
     * The category tree, ordered the way the supplier ordered it on disk.
     *
     * @return array<int, array{name: string, path: array<int, string>, children: array<int, mixed>, products: array<int, array{code: string, dir: string, path: array<int, string>}>}>
     */
    public function categories(): array
    {
        if (! $this->exists()) {
            return [];
        }

        $root = ['name' => '', 'path' => [], 'children' => [], 'products' => []];
        $this->scan($this->root, $root);

        return $root['children'];
    }

    /**
     * Every product folder in the export with its parsed JSON payload.
     *
     * @return array<int, array{code: string, dir: string, path: array<int, string>, data: array<string, mixed>}>
     */
    public function products(): array
    {
        $products = [];
        foreach ($this->walk($this->categories()) as $product) {
            $file = $product['dir'].'/product.json';
            if (! is_file($file)) {
                continue;
            }
            $data = json_decode((string) file_get_contents($file), true);
            if (! is_array($data)) {
                continue;
            }
            $products[] = [
                'code' => $product['code'],
                'dir' => $product['dir'],
                'path' => $product['path'],
                'data' => $data,
            ];
        }

        return $products;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array{code: string, dir: string, path: array<int, string>}>
     */
    private function walk(array $nodes): array
    {
        $flat = [];
        foreach ($nodes as $node) {
            foreach ($node['products'] as $product) {
                $flat[] = $product;
            }
            $flat = [...$flat, ...$this->walk($node['children'])];
        }

        return $flat;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function scan(string $dir, array &$node): void
    {
        $entries = @scandir($dir);
        if ($entries === false) {
            return;
        }
        sort($entries);

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.') || str_starts_with($entry, '_')) {
                continue;
            }
            $full = $dir.'/'.$entry;
            if (! is_dir($full)) {
                continue;
            }

            if ($entry === 'products') {
                $node['products'] = [...$node['products'], ...$this->productFolders($full, $node['path'])];
                continue;
            }

            $child = [
                'name' => $entry,
                'path' => [...$node['path'], $entry],
                'children' => [],
                'products' => [],
            ];
            $this->scan($full, $child);
            $node['children'][] = $child;
        }
    }

    /**
     * @param  array<int, string>  $path
     * @return array<int, array{code: string, dir: string, path: array<int, string>}>
     */
    private function productFolders(string $dir, array $path): array
    {
        $entries = @scandir($dir);
        if ($entries === false) {
            return [];
        }
        sort($entries);

        $products = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }
            $full = $dir.'/'.$entry;
            if (! is_dir($full) || ! is_file($full.'/product.json')) {
                continue;
            }
            $products[] = ['code' => $entry, 'dir' => $full, 'path' => $path];
        }

        return $products;
    }
}
