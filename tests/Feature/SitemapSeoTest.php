<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Services\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_includes_parent_categories_with_all_param_and_blog_posts(): void
    {
        $parent = Category::create(['name' => 'Men', 'slug' => 'for-men', 'parent_id' => null, 'is_active' => true]);
        $child = Category::create(['name' => 'Men Jackets', 'slug' => 'for-men-jackets', 'parent_id' => $parent->id, 'is_active' => true]);

        $post = Post::create([
            'title' => 'Leather Care Guide',
            'slug' => 'leather-care-guide',
            'content' => 'Content...',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $service = app(SitemapService::class);
        $sitemap = $service->buildSitemap();
        $urls = collect($sitemap->getTags())->map(fn ($t) => $t->url)->all();

        $this->assertContains(route('category.show', $parent->slug) . '?all=1', $urls);
        $this->assertContains(route('category.show', $child->slug), $urls);
        $this->assertContains(route('blog.index'), $urls);
        $this->assertContains(route('blog.show', $post->slug), $urls);
    }
}
