<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CartMutationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconfiguration_merge_rolls_back_if_source_deletion_fails(): void
    {
        $category = Category::create(['name' => 'Cart fixture']);
        $product = Product::create(['category_id' => $category->id, 'title' => 'Bag', 'price' => 100, 'is_active' => true, 'in_stock' => true]);
        $product->variants()->create(['name' => 'Standard', 'weight' => 'Standard', 'price' => 100, 'is_active' => true, 'is_default' => true]);
        $this->actingAs(User::factory()->create(), 'web');
        $cart = app(CartService::class);
        $cart->add($product, 2);
        $cart->add($product, specialRequest: 'Gift');
        $source = CartItem::where('special_request', 'Gift')->sole();
        $target = CartItem::whereNull('special_request')->sole();
        CartItem::deleting(function ($item) use ($source) {
            if ($item->id === $source->id) {
                throw new RuntimeException('Injected source deletion failure');
            }
        });
        try {
            $cart->update($source->id, specialRequest: null, updateSpecialRequest: true);
            $this->fail('Expected deletion failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected source deletion failure', $exception->getMessage());
        }
        $this->assertSame(2, $target->fresh()->quantity);
        $this->assertSame(1, $source->fresh()->quantity);
        $this->assertSame('Gift', $source->fresh()->special_request);
        $this->assertDatabaseCount('cart_items', 2);
    }
}
