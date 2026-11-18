<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Services\SitemapService;
use App\Services\Seo\StructuredDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression checks for the technical-SEO audit fixes: valid JSON-LD
 * envelopes, well-formed Open Graph tags and a working blog admin.
 */
class SeoTechnicalAuditTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(array $overrides = []): Post
    {
        return Post::create($overrides + [
            'title' => 'Leather Care Guide',
            'slug' => 'leather-care-guide',
            'excerpt' => 'Keep your jacket supple.',
            'content' => '<p>Condition twice a year.</p>',
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function jsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

        return array_map(fn ($json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $matches[1]);
    }

    public function test_json_ld_is_wrapped_in_schema_org_context_without_nulls(): void
    {
        $service = app(StructuredDataService::class);

        $list = json_decode(strip_tags((string) $service->render([['@type' => 'Thing', 'name' => 'A', 'url' => null, 'sameAs' => []]])), true);
        $this->assertSame('https://schema.org', $list['@context']);
        $this->assertSame([['@type' => 'Thing', 'name' => 'A']], $list['@graph']);

        // A single node (articleGraph returns one) is wrapped the same way.
        $single = json_decode(strip_tags((string) $service->render(['@type' => 'BlogPosting', 'headline' => 'H'])), true);
        $this->assertSame('BlogPosting', $single['@graph'][0]['@type']);

        // Merchant copy can never close the script tag early.
        $this->assertStringNotContainsString('</script><', (string) $service->render([['@type' => 'Thing', 'name' => '</script><b>']]));
    }

    public function test_blog_post_page_has_valid_head_tags_and_article_schema(): void
    {
        DB::table('theme_selection')->where('id', 1)->update(['active_theme' => 'rjleather.theme-storefront']);
        $post = $this->makePost();

        $html = $this->get(route('blog.show', $post->slug))->assertOk()->getContent();

        $this->assertStringContainsString('<meta property="og:locale" content="en_US">', $html);
        $this->assertStringContainsString('<meta property="og:url" content="'.route('blog.show', $post->slug).'">', $html);

        $types = collect($this->jsonLd($html))
            ->each(fn ($doc) => $this->assertSame('https://schema.org', $doc['@context']))
            ->flatMap(fn ($doc) => array_column($doc['@graph'], '@type'))
            ->all();
        $this->assertContains('Organization', $types);
        $this->assertContains('BlogPosting', $types);

        $index = $this->get(route('blog.index'))->assertOk()->getContent();
        $this->assertStringContainsString('<meta property="og:type" content="website">', $index);
    }

    public function test_paginated_pages_get_distinct_titles(): void
    {
        DB::table('theme_selection')->where('id', 1)->update(['active_theme' => 'rjleather.theme-storefront']);
        $this->makePost();

        $html = $this->get(route('blog.index', ['page' => 2]))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<title>[^<]+ — Page 2</title>#', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.route('blog.index').'?page=2">', $html);
    }

    public function test_blog_admin_screens_render_and_save(): void
    {
        $admin = Admin::create([
            'name' => 'Editor', 'email' => uniqid().'@example.com', 'password' => 'a-long-test-password',
            'role' => 'custom', 'is_active' => true, 'permissions' => ['posts.view', 'posts.manage', 'posts.delete'],
        ]);
        $post = $this->makePost();

        $this->actingAs($admin, 'admin');
        $this->get(route('admin.posts.index'))->assertOk()->assertSee($post->title);
        $this->get(route('admin.posts.create'))->assertOk()->assertSee('name="meta_description"', false);
        $this->get(route('admin.posts.edit', $post))->assertOk()->assertSee($post->title);

        $this->post(route('admin.posts.store'), [
            'title' => 'Biker Jacket Sizing',
            'content' => '<p>Measure your chest.</p>',
            'is_published' => '1',
        ])->assertRedirect(route('admin.posts.index'));
        $this->assertDatabaseHas('posts', ['slug' => 'biker-jacket-sizing', 'is_published' => true]);

        $this->put(route('admin.posts.update', $post), [
            'title' => $post->title,
            'slug' => $post->slug,
            'content' => $post->content,
            'is_published' => '0',
        ])->assertRedirect(route('admin.posts.index'));
        $this->assertFalse($post->fresh()->is_published);
    }

    public function test_sibling_categories_under_men_and_women_get_distinct_meta_titles(): void
    {
        $men = Category::create(['name' => 'For Men', 'slug' => 'for-men', 'is_active' => true]);
        $women = Category::create(['name' => 'For Women', 'slug' => 'for-women', 'is_active' => true]);
        Category::create(['name' => 'Jackets', 'slug' => 'for-men-jackets', 'parent_id' => $men->id, 'is_active' => true]);
        Category::create(['name' => 'Jackets', 'slug' => 'for-women-jackets', 'parent_id' => $women->id, 'is_active' => true]);

        $this->artisan('seo:generate-category-metas', ['--force' => true])->assertSuccessful();

        $titles = Category::whereIn('slug', ['for-men-jackets', 'for-women-jackets'])->pluck('meta_title', 'slug');
        $this->assertSame("Men's Leather Jackets | RJ Motorbike", $titles['for-men-jackets']);
        $this->assertSame("Women's Leather Jackets | RJ Motorbike", $titles['for-women-jackets']);
    }

    public function test_product_meta_descriptions_name_the_product_stay_unique_and_end_cleanly(): void
    {
        $category = Category::create(['name' => 'Racing', 'slug' => 'racing', 'is_active' => true]);
        $spec = '1.2–1.3 mm drum-dyed milled cowhide leather resists tearing and puncturing during high-speed slides; paired with pre-curved sleeves for optimal racing posture';

        foreach (['Jack Miller MotoGP Racing Jacket Ducati 2024', 'Pol Espargaro MotoGP Racing Jacket Honda 2022'] as $i => $title) {
            Product::create([
                'category_id' => $category->id, 'title' => $title, 'slug' => 'racing-'.$i, 'price' => 399, 'is_active' => true,
                'short_description' => $spec, 'meta_description' => $spec,
            ]);
        }

        $this->artisan('seo:upgrade-products')->assertSuccessful();

        $descriptions = Product::orderBy('id')->get()->map->meta_description;
        foreach (['Jack Miller MotoGP Racing Jacket Ducati 2024', 'Pol Espargaro MotoGP Racing Jacket Honda 2022'] as $i => $title) {
            $description = $descriptions[$i];
            $this->assertStringStartsWith($title.' — ', $description);
            $this->assertLessThanOrEqual(160, mb_strlen($description));
            $this->assertGreaterThanOrEqual(100, mb_strlen($description));
            $this->assertMatchesRegularExpression('/[.!?]$/', $description);
            $this->assertStringNotContainsString('...', $description);
            // Cut at a phrase boundary, not on a dangling word such as "paired".
            $this->assertDoesNotMatchRegularExpression('/\b(paired|with|and|for|the)\.$/i', $description);
        }
        $this->assertCount(2, $descriptions->unique());
    }

    public function test_robots_txt_blocks_private_areas_and_points_to_the_sitemap(): void
    {
        $robots = app(SitemapService::class)->buildRobotsTxt();

        foreach (['/admin/', '/account/', '/cart', '/checkout', '/login'] as $path) {
            $this->assertStringContainsString('Disallow: '.$path, $robots);
        }
        $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), $robots);
    }

    public function test_search_results_are_noindex_while_catalog_pages_stay_indexable(): void
    {
        DB::table('theme_selection')->where('id', 1)->update(['active_theme' => 'rjleather.theme-storefront']);

        $this->get(route('search', ['q' => 'jacket']))->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->get(route('blog.index'))->assertOk()->assertSee('<meta name="robots" content="index, follow">', false);
    }
}
