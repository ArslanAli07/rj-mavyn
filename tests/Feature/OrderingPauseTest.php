<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderingPauseTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function createSimpleProduct(): Product
    {
        $category = Category::create([
            'name' => 'Fruit Tea',
            'slug' => 'fruit-tea',
            'is_active' => true,
            'display_order' => 1,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'title' => 'Peach Tea',
            'slug' => 'peach-tea',
            'price' => 450,
            'short_description' => 'Peachy',
            'description' => 'Peachy tea',
            'is_active' => true,
            'in_stock' => true,
            'stock_quantity' => 10,
        ]);

        $product->variants()->create([
            'name' => 'Regular',
            'weight' => 'Regular',
            'price' => 450,
            'servings' => '1 Serving',
            'sort_order' => 0,
            'is_default' => true,
            'is_active' => true,
        ]);

        return $product->fresh('variants');
    }

    protected function setOrderingHours(string $start = '09:00', string $end = '21:00'): void
    {
        Setting::set('ordering_start_time', $start, 'ordering', 'text');
        Setting::set('ordering_end_time', $end, 'ordering', 'text');
    }

    protected function freezeKarachiTime(string $time): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03 '.$time, 'Asia/Karachi'));
    }

    public function test_cart_and_checkout_are_blocked_when_ordering_is_disabled(): void
    {
        Setting::set('ordering_enabled', '0', 'ordering', 'boolean');
        $this->setOrderingHours();
        $this->freezeKarachiTime('12:00');
        $product = $this->createSimpleProduct();

        $this->get(route('shop'))
            ->assertOk()
            ->assertSee('Online ordering is paused', false)
            ->assertSee('Checkout on Foodpanda')
            ->assertSee('popup-image.png', false);

        $this->post(route('cart.add'), [
            'product_id' => $product->id,
            'product_variant_id' => $product->variants->first()->id,
            'quantity' => 1,
        ])->assertRedirect(route('shop'));

        $this->get(route('cart.index'))->assertRedirect(route('shop'));
        $this->get(route('checkout.index'))->assertRedirect(route('shop'));
    }

    public function test_cart_works_when_ordering_is_enabled_inside_hours(): void
    {
        Setting::set('ordering_enabled', '1', 'ordering', 'boolean');
        $this->setOrderingHours();
        $this->freezeKarachiTime('12:00');
        $product = $this->createSimpleProduct();

        $this->get(route('shop'))
            ->assertOk()
            ->assertDontSee('Online ordering is paused', false)
            ->assertDontSee('The shop is currently closed', false)
            ->assertDontSee('popup-image.png', false);

        $this->post(route('cart.add'), [
            'product_id' => $product->id,
            'product_variant_id' => $product->variants->first()->id,
            'quantity' => 1,
        ])->assertRedirect();

        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_shop_closed_outside_hours_blocks_cart_without_popup(): void
    {
        Setting::set('ordering_enabled', '1', 'ordering', 'boolean');
        $this->setOrderingHours('09:00', '21:00');
        $this->freezeKarachiTime('22:00');
        $product = $this->createSimpleProduct();

        $this->get(route('shop'))
            ->assertOk()
            ->assertSee('The shop is currently closed', false)
            ->assertSee('9:00 AM to 9:00 PM', false)
            ->assertDontSee('Online ordering is paused', false)
            ->assertDontSee('popup-image.png', false);

        $this->post(route('cart.add'), [
            'product_id' => $product->id,
            'product_variant_id' => $product->variants->first()->id,
            'quantity' => 1,
        ])->assertRedirect(route('shop'));

        $this->get(route('cart.index'))->assertRedirect(route('shop'));
        $this->get(route('checkout.index'))->assertRedirect(route('shop'));
    }

    public function test_overnight_hours_keep_ordering_open_after_midnight(): void
    {
        Setting::set('ordering_enabled', '1', 'ordering', 'boolean');
        $this->setOrderingHours('22:00', '02:00');
        $this->freezeKarachiTime('01:00');
        $product = $this->createSimpleProduct();

        $this->post(route('cart.add'), [
            'product_id' => $product->id,
            'product_variant_id' => $product->variants->first()->id,
            'quantity' => 1,
        ])->assertRedirect();

        $this->assertDatabaseCount('cart_items', 1);
    }
}
