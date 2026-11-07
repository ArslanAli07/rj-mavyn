<?php

namespace Database\Seeders;

use App\Services\Catalog\RjLeatherCatalogImporter;
use Illuminate\Database\Seeder;

/**
 * Loads the RJ Leather supplier export into the storefront catalogue.
 *
 * The export lives outside the repository; its location is configured with
 * RJ_CATALOG_SOURCE and defaults to the standard Downloads folder. Running
 * the seeder again refreshes the catalogue instead of duplicating it.
 *
 * php artisan db:seed --class=RjLeatherCatalogSeeder
 */
class RjLeatherCatalogSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * The catalogue lives inside the project under products-data, so a new
         * machine gets products and photography from one seeder run. RJ_CATALOG_SOURCE
         * can still point at any other copy of the supplier export.
         */
        $source = rtrim((string) (env('RJ_CATALOG_SOURCE') ?: base_path('products-data')), '/');
        $copyImages = filter_var(env('RJ_CATALOG_COPY_IMAGES', true), FILTER_VALIDATE_BOOLEAN);

        $this->command?->info('Importing the RJ Leather catalogue from '.$source);

        $importer = app(RjLeatherCatalogImporter::class);
        $summary = $importer->import($source, [
            'copy_images' => $copyImages,
            'currency' => (string) env('RJ_CATALOG_CURRENCY', 'USD'),
        ]);

        $this->command?->line('  imported: '.$summary['products_created'].' created, '.$summary['products_updated'].' updated, '.$summary['products_skipped'].' skipped');

        foreach ($summary as $key => $value) {
            $this->command?->line('  '.$key.': '.(is_array($value) ? implode(', ', $value) : $value));
        }
    }
}
