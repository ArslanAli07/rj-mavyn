<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Occasion;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $category = Category::create(['name' => 'Accessories', 'slug' => 'accessories', 'is_active' => true]);
        $this->product = Product::create(['category_id' => $category->id, 'title' => 'Travel bag', 'slug' => 'travel-bag', 'price' => 100, 'discount_price' => 80, 'is_active' => true, 'in_stock' => true]);
        $this->product->variants()->create(['name' => 'Standard', 'weight' => 'Standard', 'price' => 100, 'discount_price' => 80, 'is_active' => true, 'is_default' => true]);
    }

    private function admin(): Admin
    {
        return Admin::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
    }

    public function test_bulk_deactivation_immediately_removes_cached_product_page(): void
    {
        $url = route('product.show', $this->product->slug);
        $this->get($url)->assertOk();
        $this->actingAs($this->admin(), 'admin')->post(route('admin.products.bulk'), ['ids' => [$this->product->id], 'bulk_action' => 'deactivate'])->assertSessionHasNoErrors();
        auth()->shouldUse('web');
        $this->get($url)->assertNotFound();
    }

    public function test_related_products_refresh_when_another_product_is_changed_or_deleted(): void
    {
        $related = Product::create(['category_id' => $this->product->category_id, 'title' => 'Related bag', 'price' => 120, 'is_active' => true, 'in_stock' => true]);
        $url = route('product.show', $this->product->slug);
        $this->get($url)->assertViewHas('related', fn ($items) => $items->contains('id', $related->id));
        $related->update(['title' => 'Renamed bag']);
        $this->get($url)->assertViewHas('related', fn ($items) => $items->firstWhere('id', $related->id)?->title === 'Renamed bag');
        $related->delete();
        $this->get($url)->assertViewHas('related', fn ($items) => ! $items->contains('id', $related->id));
    }

    public function test_cached_product_page_uses_current_variant_price(): void
    {
        $url = route('product.show', $this->product->slug);
        $this->get($url)->assertOk();
        $this->product->variants()->first()->update(['price' => 200, 'discount_price' => null]);
        $this->get($url)->assertViewHas('product', fn ($product) => $product->variants->first()->effective_price === '200.00');
    }

    public function test_unknown_category_and_occasion_filters_return_no_products(): void
    {
        foreach (['category', 'occasion'] as $filter) {
            $this->get(route('shop', [$filter => 'does-not-exist']))->assertOk()->assertViewHas('products', fn ($items) => $items->total() === 0);
        }
    }

    public function test_inactive_category_and_occasion_filters_return_no_products(): void
    {
        $this->product->category->update(['is_active' => false]);
        $occasion = Occasion::create(['name' => 'Private', 'slug' => 'private', 'is_active' => false]);
        $this->product->occasions()->attach($occasion);
        foreach (['category' => 'accessories', 'occasion' => 'private'] as $filter => $slug) {
            $this->get(route('shop', [$filter => $slug]))->assertOk()->assertViewHas('products', fn ($items) => $items->total() === 0);
        }
    }

    public function test_price_filters_use_the_displayed_discount_price(): void
    {
        $this->get(route('shop', ['max_price' => 90]))->assertOk()->assertViewHas('products', fn ($items) => $items->contains('id', $this->product->id));
        $this->get(route('shop', ['min_price' => 90]))->assertOk()->assertViewHas('products', fn ($items) => $items->total() === 0);
    }

    public function test_search_rejects_array_queries_instead_of_throwing_type_errors(): void
    {
        foreach (['search', 'search.suggestions'] as $route) {
            $this->getJson(route($route, ['q' => ['bag']]))->assertUnprocessable()->assertJsonValidationErrors('q');
        }
    }

    public function test_guest_wishlist_excludes_inactive_products(): void
    {
        $this->product->update(['is_active' => false]);
        $this->get(route('wishlist.index', ['ids' => $this->product->id]))->assertOk()->assertViewHas('wishlist', fn ($items) => $items->isEmpty());
    }
}
