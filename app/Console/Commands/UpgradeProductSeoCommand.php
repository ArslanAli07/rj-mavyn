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

        // Meta descriptions shared by several products are rewritten too.
        $descriptionCounts = $products
            ->filter(fn ($p) => filled($p->meta_description))
            ->groupBy(fn ($p) => mb_strtolower(trim((string) $p->meta_description)))
            ->map->count();
        $duplicateDescriptions = $descriptionCounts->filter(fn ($c) => $c > 1)->keys()->all();
        $this->info('Found ' . count($duplicateDescriptions) . ' duplicate meta-description groups.');

        $updatedCount = 0;

        // Descriptions already in use by products we will not rewrite.
        $claimedDescriptions = [];
        foreach ($products as $existing) {
            $text = (string) $existing->meta_description;
            $keeps = ! (mb_strlen(trim($text)) < 90 || ! $this->mentionsProduct($text, (string) $existing->title) || $this->needsPolish($text))
                && ! in_array(mb_strtolower(trim($text)), $duplicateDescriptions, true) && ! $force;
            if ($keeps) {
                $claimedDescriptions[mb_strtolower(trim($text))] = $existing->id;
            }
        }

        foreach ($products as $product) {
            $originalTitle = $product->title ?: 'Leather Motorcycle Jacket';
            $metaTitle = $product->meta_title ?: $originalTitle;
            $metaDescription = $product->meta_description ?? '';
            $metaKeywords = $product->meta_keywords ?? '';

            $isDuplicate = in_array(mb_strtolower(trim((string) ($product->meta_title ?: $product->title))), $duplicateTitleNames, true);
            $newMetaTitle = $this->buildMetaTitle($product, $isDuplicate);

            $isDuplicateDescription = in_array(mb_strtolower(trim((string) $metaDescription)), $duplicateDescriptions, true);
            $needsDescUpdate = $force
                || $isDuplicateDescription
                || empty($metaDescription)
                || mb_strlen(trim($metaDescription)) < 90
                || ! $this->mentionsProduct($metaDescription, $originalTitle)
                || $this->needsPolish($metaDescription);
            $newMetaDescription = $needsDescUpdate ? $this->buildMetaDescription($product) : $metaDescription;

            // Keep every description unique across the catalogue: when two
            // products would read the same, lead with the model code.
            $descriptionKey = mb_strtolower(trim($newMetaDescription));
            if ($needsDescUpdate && isset($claimedDescriptions[$descriptionKey]) && $claimedDescriptions[$descriptionKey] !== $product->id) {
                $newMetaDescription = $this->buildMetaDescription($product, $this->extractSlugDistinction($product));
                $descriptionKey = mb_strtolower(trim($newMetaDescription));
            }
            $claimedDescriptions[$descriptionKey] = $product->id;

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
        // ...unless an earlier run cut it off mid-word with an ellipsis.
        $truncated = str_ends_with($existing, '...') || str_ends_with($existing, '…');
        if ($existing !== '' && mb_strlen($existing) >= 30 && mb_strlen($existing) <= 65 && ! $isDuplicate && ! $truncated) {
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

        // 3. Long titles: keep whole words, up to 65 characters, no ellipsis.
        return $this->fitWords($base, 65);
    }

    /**
     * Shorten text to at most $max characters on a word boundary, dropping a
     * dangling connector ("of", "with", "—"…) so the snippet reads cleanly.
     */
    private function fitWords(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max + 1);
        $space = mb_strrpos($cut, ' ');
        $cut = $space !== false ? mb_substr($cut, 0, $space) : mb_substr($text, 0, $max);

        // Prefer ending on a finished clause: cut back to the last comma,
        // semicolon or dash when that keeps most of the text.
        if (preg_match('/^(.*)[;,]\s/u', $cut, $clause) && mb_strlen($clause[1]) >= (int) (mb_strlen($cut) * 0.55)) {
            $cut = $clause[1];
        }

        $connectors = '(of|with|and|or|for|the|in|on|to|by|from|a|an|at|as|into|under|over|through|during|while|that|which|is|are|per|via|between|than|its|their|your|our|but|so|engineered|designed|built|made|crafted|tailored|paired|combined|reinforced|&|—|-|–|\|,)';
        while (preg_match('/\s'.$connectors.'$/iu', $cut)) {
            $cut = preg_replace('/\s'.$connectors.'$/iu', '', $cut);
        }

        return rtrim($cut, " ,;:—–-");
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

    /** Does the description name the product (its first three title words)? */
    private function mentionsProduct(string $description, string $title): bool
    {
        $words = preg_split('/\s+/u', trim($title), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lead = mb_strtolower(implode(' ', array_slice($words, 0, 3)));

        return $lead !== '' && str_contains(mb_strtolower($description), $lead);
    }

    /**
     * Copy left by an earlier generator version that reads badly: awkward
     * wording ("handcrafted Ducati"), no closing punctuation, or a sentence
     * that stops on a dangling word ("…free worldwide shipping by.").
     */
    private function needsPolish(string $description): bool
    {
        $text = trim($description);

        // The first template cut its closing sentence at 158 characters, mid-word.
        return (str_contains($text, 'Custom made to measure with reinforced seams') && ! str_ends_with($text, 'RJ Motorbike.'))
            || (bool) preg_match('/ — handcrafted .+ by RJ Motorbike, made to your measurements\\./u', $text)
            || ! preg_match('/[.!?]$/u', $text)
            || (bool) preg_match('/\s(of|with|and|or|for|the|in|on|to|by|from|a|an|at|as|into|under|over|through|during|while|that|which|is|are|per|via|between|than|its|their|your|our|but|so|engineered|designed|built|made|crafted|tailored|paired|combined|reinforced)[.!?]$/iu', $text);
    }

    /**
     * Product-first meta description (about 120-160 characters): the product
     * name, then its own spec line, then a short brand/shipping tail that
     * fits. A long spec is cut at a natural boundary, never mid-phrase and
     * never with an ellipsis.
     */
    private function buildMetaDescription(Product $product, ?string $modelCode = null): string
    {
        $title = trim($product->title ?: 'Custom leather jacket');
        if ($modelCode !== null) {
            $title .= ' '.$modelCode;
        }
        $spec = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($product->short_description ?: ''))));
        $spec = rtrim($spec, ' .;,');
        $max = 160;

        if (mb_strlen($spec) >= 30) {
            $room = $max - mb_strlen("{$title} — .");
            $base = "{$title} — ".$this->clipSpec($spec, $room).'.';

            foreach ([' Made to measure by RJ Motorbike. Free worldwide shipping.', ' Made to measure by RJ Motorbike.', ' Free worldwide shipping.', ''] as $tail) {
                if (mb_strlen($base.$tail) <= $max) {
                    if (mb_strlen($base.$tail) >= 100) {
                        return $base.$tail;
                    }
                    break;
                }
            }

            // A very short spec line: pad with the brand sentence instead.
            $text = $this->fitWords("{$title} — {$spec}. Handcrafted by RJ Motorbike from full-grain leather, made to your measurements. Free worldwide shipping.", 158);

            return preg_match('/[.!?]$/u', $text) ? $text : $text.'.';
        }

        $text = $this->fitWords("Shop {$title}, handcrafted by RJ Motorbike from full-grain leather. Made to your exact measurements with free worldwide shipping.", 158);

        return preg_match('/[.!?]$/u', $text) ? $text : $text.'.';
    }

    /**
     * Shorten a spec line to $room characters, ending where a phrase ends:
     * before the last comma, semicolon or conjunction that keeps at least
     * half the room. Falls back to a plain word boundary.
     */
    private function clipSpec(string $spec, int $room): string
    {
        if (mb_strlen($spec) <= $room) {
            return $spec;
        }

        $window = mb_substr($spec, 0, $room + 1);
        $best = null;
        // Punctuation ends a phrase more cleanly than a conjunction, so it is
        // tried first; conjunctions are the fallback.
        foreach (['/[;,]\s/u', '/ (?:and|while|plus|with|which) /u'] as $pattern) {
            if (preg_match_all($pattern, $window, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as [, $byteOffset]) {
                    $position = mb_strlen(substr($window, 0, $byteOffset));
                    if ($position >= (int) ($room * 0.5) && $position <= $room) {
                        $best = $position;
                    }
                }
            }
            if ($best !== null) {
                break;
            }
        }

        return $best !== null ? rtrim(mb_substr($window, 0, $best), ' ,;') : $this->fitWords($spec, $room);
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
