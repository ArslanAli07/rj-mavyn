<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Services\Catalog\CategoryTree;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Fills meta_title and meta_description for every active category from its
 * position in the tree. The merchant can then retune any single row in the
 * admin category editor; rerunning with --force regenerates everything.
 *
 * Copy follows the agreed keyword strategy (USA/UK/AU English, brand
 * "RJ Motorbike"): the title carries the demographic + garment keyword,
 * the description sells material, made-to-measure sizing and shipping.
 */
class GenerateCategoryMetasCommand extends Command
{
    protected $signature = 'seo:generate-category-metas
        {--force : Overwrite meta fields that already hold copy}
        {--fix-duplicates : Only rewrite categories whose current meta title is shared with another category}';

    protected $description = 'Generate keyword-rich meta titles and descriptions for all categories from the category tree';

    private const BRAND = 'RJ Motorbike';

    /** Title phrase per leaf category name, keyed by the root it sits under. */
    private const LEAF_PHRASES = [
        // ── For Men / For Women jackets & coats ────────────────────────────
        "biker's" => 'Biker Leather Jackets',
        'bomber' => 'Bomber Leather Jackets',
        'fur' => 'Fur Leather Jackets',
        'hooded' => 'Hooded Leather Jackets',
        'quilted' => 'Quilted Leather Jackets',
        'shirt style' => 'Shirt Style Leather Jackets',
        'short' => 'Short Leather Jackets',
        'jackets' => 'Leather Jackets',
        'coats' => 'Leather Coats',
        'blazers' => 'Leather Blazers',
        'trench' => 'Trench Leather Coats',
        'long' => 'Long Leather Coats',
        'peacoats' => 'Leather Peacoats',
        'gloves' => 'Leather Gloves',
        'pants' => 'Leather Pants',
        'vests' => 'Leather Vests',
        'chaps' => 'Leather Chaps',
        'chappals' => 'Leather Chappals',
        'leather kilts & skirts' => 'Leather Kilts & Skirts',
        'leather kilts' => 'Leather Kilts',
        'leather handbags' => 'Leather Handbags',

        // ── MotoGP ─────────────────────────────────────────────────────────
        'race suits' => 'MotoGP Race Suits',
        'race' => 'MotoGP Race Suits',
        'moto archives' => 'Archive MotoGP Race Suits',
        'footwear' => 'MotoGP Leather Boots',
        'ducati' => 'Ducati MotoGP Leather Collection',
        'honda' => 'Honda MotoGP Leather Collection',
        'ktm' => 'KTM MotoGP Leather Collection',
        'yamaha' => 'Yamaha MotoGP Leather Collection',
        'ama' => 'AMA MotoGP Race Suits',
        'moto gp' => 'MotoGP Race Suits',
        'wsbk' => 'WSBK Race Suits',

        // ── Motorcycle ─────────────────────────────────────────────────────
        'men' => 'Men\'s Motorcycle Leather Jackets',
        '1-piece race suit' => '1-Piece Motorcycle Race Suits',
    ];

    /** Phrase overrides keyed by exact slug for the odd ones. */
    private const SLUG_PHRASES = [
        'for-men' => 'Men\'s Leather Jackets, Suits & Gear',
        'for-women' => 'Women\'s Leather Jackets, Coats & Gear',
        'motogp' => 'MotoGP Leather Jackets & Race Suits',
        'motorcycle' => 'Motorcycle Leather Jackets & Riding Gear',
        'customize' => 'Custom Leather Jackets Made to Measure',
        'motogp-brands' => 'MotoGP Brand Collections',
        'customize-fashion' => 'Custom Leather Jackets Made to Your Design',
        'shop-by-brands' => 'Collections & Brand Archives',
        'leather-collection' => 'Leather Collection Archive',
        'for-women-movies-jackets' => 'Women\'s Movie Leather Jackets',
        'uncategorized' => 'Specialty Leather Goods',
        'moto-speeds' => 'Moto Speeds Racing Collection',
        'motorcycle-jackets-race' => 'Racing Motorcycle Leather Jackets',
        'motorcycle-jackets-semi' => 'Semi Racing Motorcycle Jackets',
        'motorcycle-jackets-touring' => 'Touring Motorcycle Leather Jackets',
        'motorcycle-race-suit' => 'Motorcycle Race Suits',
        'motorcycle-vests' => 'Motorcycle Leather Vests',
        // "Race" (AMA / Moto GP / WSBK series gear) is a different shelf from
        // "Race Suits", so each gets its own title.
        'motogp-race' => 'MotoGP, WSBK & AMA Racing Leathers',
        'motogp-race-moto-gp' => 'MotoGP Racing Leathers & Replica Gear',
    ];

    /** Section qualifier per catalogue root, so Men's and Women's shelves differ. */
    private const ROOT_QUALIFIERS = [
        'for-men' => "Men's",
        'for-women' => "Women's",
        'motogp' => 'MotoGP',
        'motorcycle' => 'Motorcycle',
    ];

    public function __construct(private readonly CategoryTree $tree)
    {
        parent::__construct();
    }

    /** Cache + sitemap refresh once, after the bulk write. */
    private function bustCaches(): void
    {
        foreach (['nav_categories', 'home_categories', 'active_categories'] as $key) {
            \Illuminate\Support\Facades\Cache::forget($key);
        }

        $result = app(\App\Services\SitemapService::class)->generate();
        $this->info("Sitemap regenerated with {$result['urls']} URLs.");
    }

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $fixDuplicates = (bool) $this->option('fix-duplicates');
        $categories = Category::active()->orderBy('display_order')->get();

        // Titles currently shared by more than one category (case-insensitive).
        $sharedTitles = $categories
            ->filter(fn (Category $category) => filled($category->meta_title))
            ->groupBy(fn (Category $category) => mb_strtolower(trim($category->meta_title)))
            ->filter(fn ($group) => $group->count() > 1)
            ->keys()
            ->all();
        $filled = 0;
        $skipped = 0;

        foreach ($categories as $category) {
            $phrase = $this->phraseFor($category);

            if ($phrase === null) {
                $skipped++;
                $this->warn("No keyword mapping for [{$category->slug}] — left untouched.");

                continue;
            }

            if ($fixDuplicates) {
                if (! in_array(mb_strtolower(trim((string) $category->meta_title)), $sharedTitles, true)) {
                    $skipped++;

                    continue;
                }
            } elseif (! $force && filled($category->meta_title) && filled($category->meta_description)) {
                $skipped++;

                continue;
            }

            $category->meta_title = $this->title($phrase);
            $category->meta_description = $this->description($category, $phrase);
            // Bulk copy update: skip the per-save sitemap regeneration and
            // rebuild the sitemap once at the end instead.
            Category::withoutEvents(fn () => $category->save());
            $filled++;
        }

        $this->bustCaches();

        $this->info("Meta copy written for {$filled} categories; {$skipped} skipped.");

        $stillShared = Category::active()->whereNotNull('meta_title')->get()
            ->groupBy(fn (Category $category) => mb_strtolower(trim($category->meta_title)))
            ->filter(fn ($group) => $group->count() > 1);
        foreach ($stillShared as $title => $group) {
            $this->warn("Duplicate meta title \"{$title}\": ".$group->pluck('slug')->implode(', '));
        }

        return self::SUCCESS;
    }

    /** The garment/collection keyword phrase, from curated maps then fallbacks. */
    private function phraseFor(Category $category): ?string
    {
        if (isset(self::SLUG_PHRASES[$category->slug])) {
            return self::SLUG_PHRASES[$category->slug];
        }

        // Movie title leaves: "{Movie} Leather Jacket" (gendered by the root).
        $rootSlug = $this->tree->trail($category)->first()->slug ?? '';
        if (str_contains($category->slug, 'movies-leather-jacket') || str_contains($category->slug, 'film-leather-jacket')) {
            $women = str_contains($rootSlug, 'women');

            // The shelf itself (slug ends on the group pattern, no movie name).
            if (preg_match('/-(movies|film)-leather-jacket-for-(men|women)$/', $category->slug)) {
                return $women ? 'Movie Leather Jackets for Women' : 'Movies Leather Jackets for Men';
            }

            $movie = trim(preg_replace('/^for-(men|women)-(movies|film)-leather-jacket-for-(men|women)-/', '', $category->slug));
            if ($movie === '') {
                return $women ? 'Movie Leather Jackets for Women' : 'Movies Leather Jackets for Men';
            }

            return Str::headline($movie).' Leather Jacket for '.($women ? 'Women' : 'Men');
        }

        // "MotoGP {year}" leaves inherit their parent's garment.
        if (preg_match('/^motogp (\d{4})$/', strtolower($category->name), $matches)) {
            $parentPhrase = $category->parent ? $this->phraseFor($category->parent) : null;
            $garment = $this->trimGarment($parentPhrase);

            return "MotoGP {$matches[1]} {$garment}";
        }

        $key = mb_strtolower(trim($category->name));
        if (isset(self::LEAF_PHRASES[$key])) {
            return $this->qualify(self::LEAF_PHRASES[$key], $rootSlug);
        }

        return null;
    }

    /**
     * Prefix a generic leaf phrase with its catalogue section so sibling
     * shelves stay distinct: "Leather Jackets" → "Men's Leather Jackets",
     * "MotoGP Leather Boots" under Motorcycle → "Motorcycle Leather Boots".
     */
    private function qualify(string $phrase, string $rootSlug): string
    {
        $qualifier = self::ROOT_QUALIFIERS[$rootSlug] ?? null;
        if ($qualifier === null) {
            return $phrase;
        }

        if ($qualifier === 'Motorcycle') {
            $phrase = trim(str_replace('MotoGP ', '', $phrase));
        }

        return str_contains($phrase, $qualifier) ? $phrase : "{$qualifier} {$phrase}";
    }

    /** "Leather Jackets" | "MotoGP Race Suits" → "Leather Jackets" | "Race Suits". */
    private function trimGarment(?string $phrase): string
    {
        return trim(str_replace(['MotoGP ', 'Archive '], '', (string) $phrase));
    }

    private function title(string $phrase): string
    {
        return Str::limit("{$phrase} | ".self::BRAND, 65);
    }

    private function description(Category $category, string $phrase): string
    {
        $count = $category->products()->count();

        if (str_contains($phrase, 'Leather Jacket for')) {
            $base = "{$phrase} as seen on screen, cut from full-grain leather and made to your measurements.";
            $extra = 'Free worldwide shipping.';
        } elseif (str_contains(strtolower($phrase), 'race suit')) {
            $base = "{$phrase} built for the track: premium hides, CE armour and a made-to-measure fit, handcrafted by ".self::BRAND.'.';
            $extra = 'Free worldwide shipping.';
        } elseif (str_contains(strtolower($phrase), 'custom')) {
            $base = "{$phrase} — send your sketch or reference: full-grain hides, Pantone colour matching and a perfect made-to-measure fit.";
            $extra = null;
        } else {
            $base = "Handcrafted {$phrase} from full-grain leather, made to your measurements with custom colours, sizes and linings.";
            $extra = 'Free worldwide shipping.';
        }

        // Assemble from the fullest copy down, keeping the description whole
        // (never mid-word) and inside the ~160-character display limit.
        foreach (array_filter([
            $count > 0 ? sprintf($count === 1 ? '1 style in stock. ' : '%d styles in stock. ', $count) : null,
            $extra,
        ]) as $part) {
            $candidate = trim($base.' '.$part);
            if (mb_strlen($candidate) <= 160) {
                return $candidate;
            }
        }

        return mb_strlen($base) <= 160 ? $base : Str::limit($base, 160);
    }
}
