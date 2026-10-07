<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryHomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_shows_categories_configured_for_it_in_correct_order(): void
    {
        // Create three categories with different homepage configurations and display orders
        $categoryA = Category::create([
            'name' => 'Category A (Second)',
            'display_order' => 2,
            'is_active' => true,
            'show_on_homepage' => true,
        ]);

        $categoryB = Category::create([
            'name' => 'Category B (First)',
            'display_order' => 1,
            'is_active' => true,
            'show_on_homepage' => true,
        ]);

        $categoryC = Category::create([
            'name' => 'Category C (Hidden)',
            'display_order' => 3,
            'is_active' => true,
            'show_on_homepage' => false,
        ]);

        // Add a product to each category so that they render (since homepage checks isNotEmpty())
        Product::create([
            'category_id' => $categoryA->id,
            'title' => 'Product A',
            'price' => 100,
            'short_description' => 'Test desc A',
            'description' => 'Test desc A long',
            'is_active' => true,
            'in_stock' => true,
            'stock_quantity' => 10,
        ]);

        Product::create([
            'category_id' => $categoryB->id,
            'title' => 'Product B',
            'price' => 200,
            'short_description' => 'Test desc B',
            'description' => 'Test desc B long',
            'is_active' => true,
            'in_stock' => true,
            'stock_quantity' => 10,
        ]);

        Product::create([
            'category_id' => $categoryC->id,
            'title' => 'Product C',
            'price' => 300,
            'short_description' => 'Test desc C',
            'description' => 'Test desc C long',
            'is_active' => true,
            'in_stock' => true,
            'stock_quantity' => 10,
        ]);

        // Get the home page
        $response = $this->get(route('home'));

        $response->assertStatus(200);

        // Verify that the view has the correct home categories collection in the correct order
        $response->assertViewHas('homeCategories', function ($homeCategories) use ($categoryA, $categoryB, $categoryC) {
            return $homeCategories->count() === 2 &&
                   $homeCategories[0]->id === $categoryB->id && // display_order = 1
                   $homeCategories[1]->id === $categoryA->id && // display_order = 2
                   !$homeCategories->contains('id', $categoryC->id); // show_on_homepage = false
        });
    }
}
