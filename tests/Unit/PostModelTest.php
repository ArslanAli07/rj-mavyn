<?php

namespace Tests\Unit;

use App\Models\Post;
use App\Services\Seo\StructuredDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_creation_and_article_graph(): void
    {
        $post = Post::create([
            'title' => 'How to Measure for a Custom Leather Jacket',
            'slug' => 'how-to-measure-custom-leather-jacket',
            'excerpt' => 'Step-by-step sizing guide for made-to-measure motorcycle jackets.',
            'content' => '<p>Detailed measuring guide instructions with chest, sleeve, waist...</p>',
            'meta_title' => 'How to Measure for a Custom Leather Jacket | Sizing Guide',
            'meta_description' => 'Get the perfect fit for your made-to-measure leather motorcycle jacket. Follow our master craftsman guide.',
            'meta_keywords' => 'leather jacket sizing, how to measure leather jacket, custom fit motorcycle jacket',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->assertEquals(1, $post->reading_time);
        $this->assertTrue(Post::published()->where('id', $post->id)->exists());

        \App\Models\Setting::set('site_name', 'RJ Motorbike');

        $service = app(StructuredDataService::class);
        $graph = $service->articleGraph($post);

        $this->assertEquals('BlogPosting', $graph['@type']);
        $this->assertEquals($post->title, $graph['headline']);
        $this->assertEquals('RJ Motorbike', $graph['publisher']['name']);
        $this->assertNotEmpty($graph['url']);
    }
}
