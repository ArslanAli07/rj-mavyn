<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductMediaSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_page_images_have_descriptive_alt_attributes(): void
    {
        DB::table('theme_selection')->where('id', 1)->update(['active_theme' => 'rjleather.theme-storefront']);

        $category = Category::create(['name' => 'Jackets', 'slug' => 'jackets', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'title' => 'Vintage Racing Jacket',
            'slug' => 'vintage-racing-jacket',
            'price' => 250.00,
            'is_active' => true,
        ]);

        $response = $this->get(route('product.show', $product->slug));
        $response->assertOk();
        $response->assertSee('data-gallery-main-image', false);
        $response->assertSee('alt="Vintage Racing Jacket — Custom Leather Motorcycle Gear | RJ Motorbike"', false);
    }
}

