<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\HomeSlide;
use App\Models\Occasion;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Deployment\ReleaseManifest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlaceholderImageTest extends TestCase
{
    use RefreshDatabase;

    public static function imageModels(): array
    {
        return [[Product::class, 'main_image_url'], [Category::class, 'image_url'], [HomeSlide::class, 'image_url'],
            [Occasion::class, 'image_url'], [ProductImage::class, 'image_url'], [OrderItem::class, 'product_image_url']];
    }

    #[DataProvider('imageModels')]
    public function test_empty_image_resolves_to_a_shipped_image(string $class, string $accessor): void
    {
        $url = (new $class)->getAttribute($accessor);
        $path = public_path(ltrim(parse_url($url, PHP_URL_PATH), '/'));
        $this->assertFileExists($path, 'Fallback URL must point to an existing public asset.');
        $this->assertSame('image/svg+xml', (new \finfo(FILEINFO_MIME_TYPE))->file($path));
        $this->assertNotFalse(simplexml_load_file($path));
        $this->assertArrayHasKey('public/images/placeholder.svg', app(ReleaseManifest::class)->inventory());
    }
}
