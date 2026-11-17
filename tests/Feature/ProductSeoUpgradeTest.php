<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSeoUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fixes_short_titles_and_duplicate_titles_and_generates_rich_keywords(): void
    {
        $category = Category::create(['name' => 'Biker Jackets', 'slug' => 'biker-jackets', 'is_active' => true]);

        $p1 = Product::create([
            'category_id' => $category->id,
            'title' => 'Short Jacket',
            'slug' => 'short-jacket-black-101',
            'price' => 199.00,
            'meta_title' => 'Short Jacket',
            'meta_description' => 'Too short.',
            'meta_keywords' => 'Biker Jackets, leather, handmade, RJ Leather',
            'is_active' => true,
        ]);

        $p2 = Product::create([
            'category_id' => $category->id,
            'title' => 'Duplicate Jacket',
            'slug' => 'duplicate-jacket-brown-102',
            'price' => 199.00,
            'meta_title' => 'Duplicate Jacket',
            'meta_description' => 'Another short one.',
            'meta_keywords' => 'Biker Jackets, leather, handmade, RJ Leather',
            'is_active' => true,
        ]);

        $p3 = Product::create([
            'category_id' => $category->id,
            'title' => 'Duplicate Jacket',
            'slug' => 'duplicate-jacket-vintage-103',
            'price' => 199.00,
            'meta_title' => 'Duplicate Jacket',
            'meta_description' => 'Also short.',
            'meta_keywords' => 'Biker Jackets, leather, handmade, RJ Leather',
            'is_active' => true,
        ]);

        $this->artisan('seo:upgrade-products')
            ->assertSuccessful();

        $p1->refresh();
        $p2->refresh();
        $p3->refresh();

        $this->assertGreaterThanOrEqual(30, strlen($p1->meta_title));
        $this->assertNotEquals($p2->meta_title, $p3->meta_title);
        $this->assertGreaterThanOrEqual(120, strlen($p1->meta_description));
        $this->assertStringContainsString('RJ Motorbike', $p1->meta_description);
        $this->assertStringContainsString('RJ Motorbike', $p1->meta_keywords);
        $this->assertStringNotContainsString('RJ Leather', $p1->meta_keywords);
    }
}
