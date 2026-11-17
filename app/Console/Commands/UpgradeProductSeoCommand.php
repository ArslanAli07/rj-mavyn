<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Services\SitemapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpgradeProductSeoCommand extends Command
{
    protected $signature = 'seo:upgrade-products {--dry-run : Preview changes without saving} {--force : Overwrite existing descriptions}';

    protected $description = 'Clean up duplicate and short titles, expand thin descriptions, and generate rich product-specific keywords across all products.';

    public function handle(SitemapService $sitemapService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $this->info('Starting Product SEO Upgrade across catalog...');

        // 1. Gather all active products with category
        $products = Product::active()->with('category')->get();
        $this->info("Analyzing {$products->count()} active products.");

        // 2. Identify duplicate titles across products
        $titleCounts = $products->groupBy(fn ($p) => mb_strtolower(trim((string) ($p->meta_title ?: $p->title))))->map->count();
        $duplicateTitleNames = $titleCounts->filter(fn ($c) => $c > 1)->keys()->all();
        $this->info('Found ' . count($duplicateTitleNames) . ' duplicate meta-title groups.');

        $updatedCount = 0;

        foreach ($products as $product) {
            $originalTitle = $product->title ?: 'Leather Motorcycle Jacket';
            $metaTitle = $product->meta_title ?: $originalTitle;
            $metaDescription = $product->meta_description ?? '';
            $metaKeywords = $product->meta_keywords ?? '';

            $isDuplicate = in_array(mb_strtolower(trim((string) ($product->meta_title ?: $product->title))), $duplicateTitleNames, true);
            $newMetaTitle = $this->buildMetaTitle($product, $isDuplicate);

            $needsDescUpdate = $force || empty($metaDescription) || mb_strlen(trim($metaDescription)) < 90;
            $newMetaDescription = $needsDescUpdate ? $this->buildMetaDescription($product) : $metaDescription;

            $newKeywords = $this->buildKeywords($product);

            $changed = ($newMetaTitle !== $product->meta_title)
                || ($newMetaDescription !== $product->meta_description)
                || ($newKeywords !== $product->meta_keywords);

            if ($changed) {
                $updatedCount++;
                if (! $dryRun) {
                    $product->meta_title = $newMetaTitle;
                    $product->meta_description = $newMetaDescription;
                    $product->meta_keywords = $newKeywords;
                    Product::withoutEvents(fn () => $product->save());
                }
            }
        }

        if ($dryRun) {
            $this->warn("[DRY RUN] Would update {$updatedCount} products.");
        } else {
            $this->info("Successfully updated {$updatedCount} products.");
            $this->info('Regenerating sitemap...');
            $res = $sitemapService->generateQuietly();
            $this->info('Sitemap regenerated.');
        }

        return self::SUCCESS;
    }

    private function buildMetaTitle(Product $product, bool $isDuplicate): string
    {
        $base = trim($product->title ?: 'Leather Motorcycle Jacket');
        $brand = ' | RJ Motorbike';
        $existing = trim((string) $product->meta_title);

        // If not duplicate and existing title is already optimal (30-65 chars), preserve it
        if ($existing !== '' && mb_strlen($existing) >= 30 && mb_strlen($existing) <= 65 && ! $isDuplicate) {
            return $existing;
        }

        // 1. If duplicate, append distinctive model/variant code from slug
        if ($isDuplicate) {
            $suffix = $this->extractSlugDistinction($product);
            $maxBaseLen = 65 - mb_strlen($brand) - mb_strlen($suffix) - 1;
            $base = trim(Str::limit($base, $maxBaseLen, '') . ' ' . $suffix);
        }

        // 2. For short titles (<= 32 chars), append Made to Measure before brand
        if (mb_strlen($base) <= 32) {
            $candidate = $base . ' — Made to Measure' . $brand;
            if (mb_strlen($candidate) <= 65) {
                return $candidate;
            }
        }

        if (mb_strlen($base . $brand) <= 65) {
            return $base . $brand;
        }

        // 3. For long titles (> 50 chars), fit under 65 chars
        if (mb_strlen($base) > 65) {
            return Str::limit($base, 62, '...');
        }

        return Str::limit($base, 65, '');
    }

    private function extractSlugDistinction(Product $product): string
    {
        $slug = $product->slug;
        $titleWords = collect(explode(' ', strtolower($product->title)))->filter()->all();

        // Extract trailing code or descriptors from slug
        $parts = explode('-', $slug);
        $distinctions = [];

        foreach ($parts as $part) {
            if ($part === '' || in_array(strtolower($part), $titleWords, true)) {
                continue;
            }
            if (in_array(strtolower($part), ['leather', 'jacket', 'men', 'women', 'for', 'style'], true)) {
                continue;
            }
            $distinctions[] = strtoupper($part);
        }

        if (! empty($distinctions)) {
            $code = implode('-', array_slice($distinctions, -2));
            return "({$code})";
        }

        return "(#{$product->id})";
    }

    private function buildMetaDescription(Product $product): string
    {
        $title = trim($product->title ?: 'Custom leather jacket');
        $category = $product->category?->name ?: 'Motorcycle Gear';
        $description = trim(strip_tags((string) ($product->short_description ?: $product->description)));

        if (mb_strlen($description) >= 60) {
            $text = "{$title} — {$category}. {$description} Handcrafted to measure from premium hides by RJ Motorbike. Free worldwide shipping.";
        } else {
            $text = "Shop {$title} — handcrafted {$category} by RJ Motorbike. Tailored to your exact measurements from full-grain hides with reinforced stitching. Free worldwide shipping.";
        }

        return Str::limit($text, 158, '');
    }

    private function buildKeywords(Product $product): string
    {
        $existing = (string) $product->meta_keywords;
        if (filled($existing)) {
            // Replace legacy brand references and ensure RJ Motorbike is present
            $updated = str_ireplace('RJ Leather', 'RJ Motorbike', $existing);
            if (! str_contains($updated, 'RJ Motorbike')) {
                $updated .= ', RJ Motorbike';
            }

            return $updated;
        }

        $category = $product->category?->name;
        $title = $product->title;
        $tokens = [
            $title,
            $category,
            'custom leather jacket',
            'motorcycle gear',
            'made to measure',
            'handmade leather',
            'RJ Motorbike',
        ];

        return implode(', ', array_filter(array_unique($tokens)));
    }
}
