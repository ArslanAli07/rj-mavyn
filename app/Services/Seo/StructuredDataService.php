<?php

namespace App\Services\Seo;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Services\Catalog\CategoryTree;
use App\Services\Pricing\PricingService;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * Builds the JSON-LD structured data the storefront embeds in <head>.
 *
 * Every graph is plain PHP that serializes to schema.org JSON. The layout
 * renders the sitewide Organization/WebSite graph once; pages push their own
 * graphs (Product, BreadcrumbList, ItemList, FAQPage) onto the `head` stack,
 * so Google sees one document with everything it needs for rich results.
 */
final class StructuredDataService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly CategoryTree $tree,
    ) {}

    /** Sitewide graph: the store as an Organization plus WebSite with search. */
    public function websiteGraph(): array
    {
        $storeName = (string) setting('site_name', config('site.name', 'RJ Motorbike'));
        $homeUrl = url('/');

        return [
            [
                '@type' => 'Organization',
                '@id' => $homeUrl.'#organization',
                'name' => $storeName,
                'url' => $homeUrl,
                'logo' => $this->logoUrl(),
                'contactPoint' => $this->contactPoint(),
                'sameAs' => $this->sameAs(),
            ],
            [
                '@type' => 'WebSite',
                '@id' => $homeUrl.'#website',
                'url' => $homeUrl,
                'name' => $storeName,
                'publisher' => ['@id' => $homeUrl.'#organization'],
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => [
                        '@type' => 'EntryPoint',
                        'urlTemplate' => route('search').'?q={search_term_string}',
                    ],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ];
    }

    /**
     * Product page graph: the product offer plus the breadcrumb that mirrors
     * the visual trail (Home / Men / Jackets / This Piece). The trail is
     * derived from the product's category when not supplied.
     *
     * @param  Collection<int, Category>|null  $trail  root → leaf path of the product's category
     */
    public function productGraph(Product $product, ?Collection $trail = null): array
    {
        $trail ??= $product->category ? $this->tree->trail($product->category) : collect();
        $graphs = [$this->product($product)];

        if ($trail->isNotEmpty()) {
            $graphs[] = $this->breadcrumb($trail);
        }

        return $graphs;
    }

    /**
     * Category page graph: breadcrumb plus the first page of the collection
     * as an ItemList, so Google understands the shelf and its contents.
     */
    public function categoryGraph(Category $category, iterable $products): array
    {
        $graphs = [$this->breadcrumb($this->tree->trail($category))];

        $items = collect($products instanceof Collection ? $products : collect($products))->take(24);
        if ($items->isNotEmpty()) {
            $graphs[] = [
                '@type' => 'ItemList',
                'itemListElement' => $items->map(fn (Product $product, int $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $product->title,
                    'url' => route('product.show', $product->slug),
                ])->all(),
            ];
        }

        return $graphs;
    }

    /** FAQ page graph: every active question with its answer. */
    public function faqGraph(Collection $faqs): array
    {
        return [[
            '@type' => 'FAQPage',
            'mainEntity' => $faqs->flatMap(fn ($group) => $group)->map(fn ($faq) => [
                '@type' => 'Question',
                'name' => $faq->question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => trim(strip_tags($faq->answer)),
                ],
            ])->values()->all(),
        ]];
    }

    /**
     * Serialize a graph into the <script type="application/ld+json"> tag.
     *
     * Accepts either a list of nodes or a single node, and always wraps them
     * in {"@context": "https://schema.org", "@graph": [...]} so parsers can
     * resolve the schema.org vocabulary. JSON_HEX_TAG keeps a stray "</script>"
     * in merchant copy from closing the tag early.
     */
    public function render(array $graph): HtmlString
    {
        if ($graph === []) {
            return new HtmlString('');
        }

        $nodes = array_is_list($graph) ? $graph : [$graph];

        $document = [
            '@context' => 'https://schema.org',
            '@graph' => array_values(array_map(fn ($node) => $this->prune($node), $nodes)),
        ];

        return new HtmlString('<script type="application/ld+json">'
            .json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_THROW_ON_ERROR)
            .'</script>');
    }

    /** Drop null and empty-array properties, which schema validators flag. */
    private function prune(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $pruned = array_map(fn ($item) => $this->prune($item), $value);
        $pruned = array_filter($pruned, fn ($item) => $item !== null && $item !== []);

        return array_is_list($value) ? array_values($pruned) : $pruned;
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function product(Product $product): array
    {
        $storeName = (string) setting('site_name', config('site.name', 'RJ Motorbike'));
        $images = collect([$product->main_image_url])
            ->merge(collect($product->gallery_images)->pluck('url'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $offer = [
            '@type' => 'Offer',
            'url' => route('product.show', $product->slug),
            'priceCurrency' => $this->pricing->context()->currency,
            'price' => $product->effective_price,
            'availability' => $product->available_for_sale
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock',
            'itemCondition' => 'https://schema.org/NewCondition',
        ];
        if ($product->is_on_sale) {
            $offer['priceSpecification'] = [
                '@type' => 'UnitPriceSpecification',
                'price' => $product->effective_price,
                'priceCurrency' => $this->pricing->context()->currency,
            ];
        }

        $description = trim(strip_tags((string) ($product->meta_description ?: $product->short_description ?: $product->title)));

        return [
            '@type' => 'Product',
            'name' => $product->title,
            'description' => $description,
            'image' => $images,
            'sku' => (string) $product->sku,
            'brand' => ['@type' => 'Brand', 'name' => $storeName],
            'offers' => $offer,
        ];
    }

    /**
     * BreadcrumbList for a category path; labels match the visual breadcrumb
     * (the "For " prefix is display-only and stripped on both).
     *
     * @param  Collection<int, Category>  $trail
     */
    private function breadcrumb(Collection $trail): array
    {
        $items = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')]];

        foreach ($trail->values() as $index => $category) {
            $label = preg_replace('/^for\s+/i', '', $category->name);
            $items[] = [
                '@type' => 'ListItem',
                'position' => $index + 2,
                'name' => $label,
                'item' => $this->categoryUrl($category),
            ];
        }

        return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    /**
     * The URL a category actually resolves at. A top-level group with
     * subcategories 302-redirects to its first child, so its indexable
     * whole-group page is `?all=1` (the same URL the sitemap lists).
     */
    private function categoryUrl(Category $category): string
    {
        $url = route('category.show', $category->slug);

        if ($category->parent_id === null && $this->tree->branch($category)->isNotEmpty()) {
            $url .= '?all=1';
        }

        return $url;
    }

    /** Article/BlogPosting graph for informational and guide pages. */
    public function articleGraph(Post $post): array
    {
        $storeName = (string) setting('site_name', config('site.name', 'RJ Motorbike'));
        $homeUrl = url('/');
        $postUrl = \Illuminate\Support\Facades\Route::has('blog.show') ? route('blog.show', $post->slug) : url('/blog/'.$post->slug);

        return [
            '@type' => 'BlogPosting',
            '@id' => $postUrl.'#article',
            'isPartOf' => ['@id' => $homeUrl.'#website'],
            'headline' => $post->title,
            'description' => $post->meta_description ?: $post->excerpt,
            'url' => $postUrl,
            'datePublished' => $post->published_at?->toIso8601String() ?? $post->created_at?->toIso8601String(),
            'dateModified' => $post->updated_at?->toIso8601String(),
            'image' => $post->featured_image_url,
            'author' => [
                '@type' => 'Organization',
                'name' => $storeName,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $storeName,
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => $this->logoUrl(),
                ],
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $postUrl,
            ],
        ];
    }

    private function logoUrl(): string
    {
        $uploaded = store_media_url('site_logo', '');
        if ($uploaded !== '') {
            return $uploaded;
        }

        try {
            return theme_asset('images/icon.png');
        } catch (\InvalidArgumentException) {
            return asset('icon.png');
        }
    }

    /** The store contact point, only when the merchant filled the fields. */
    private function contactPoint(): ?array
    {
        $phone = trim((string) setting('site_phone'));
        $email = trim((string) setting('site_email'));

        if ($phone === '' && $email === '') {
            return null;
        }

        return array_filter([
            '@type' => 'ContactPoint',
            'contactType' => 'customer service',
            'telephone' => $phone ?: null,
            'email' => $email ?: null,
            'availableLanguage' => ['English'],
        ], fn ($value) => $value !== null);
    }

    /** The merchant's social profiles, only when filled. */
    private function sameAs(): array
    {
        return collect(['facebook_url', 'instagram_url', 'tiktok_url', 'site_whatsapp'])
            ->map(fn ($key) => trim((string) setting($key)))
            ->filter(fn ($url) => $url !== '' && str_starts_with($url, 'http'))
            ->values()
            ->all();
    }
}
