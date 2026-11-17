<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BlogStorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_blog_index_and_post_detail_with_article_schema(): void
    {
        DB::table('theme_selection')->where('id', 1)->update(['active_theme' => 'rjleather.theme-storefront']);

        $post = Post::create([
            'title' => 'How to Measure for a Custom Leather Jacket',
            'slug' => 'how-to-measure-custom-leather-jacket',
            'excerpt' => 'Step-by-step sizing guide for made-to-measure motorcycle jackets.',
            'content' => '<p>Detailed measuring guide instructions with chest, sleeve, waist...</p>',
            'meta_title' => 'How to Measure for a Custom Leather Jacket | Sizing Guide',
            'meta_description' => 'Get the perfect fit for your made-to-measure leather motorcycle jacket.',
            'meta_keywords' => 'leather jacket sizing, how to measure leather jacket',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->get(route('blog.index'));
        $response->assertOk();
        $response->assertSee($post->title);

        $detail = $this->get(route('blog.show', $post->slug));
        $detail->assertOk();
        $detail->assertSee($post->title);
        $detail->assertSee('BlogPosting', false);
    }
}

