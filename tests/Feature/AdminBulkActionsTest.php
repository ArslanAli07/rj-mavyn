<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsAdmin(): Admin
    {
        $admin = Admin::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin');

        return $admin;
    }

    public function test_guest_cannot_run_bulk_actions(): void
    {
        $this->post(route('admin.products.bulk'), [
            'ids' => [1],
            'bulk_action' => 'delete',
        ])->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_bulk_deactivate_and_delete_products(): void
    {
        $this->actingAsAdmin();
        $category = Category::create([
            'name' => 'Milk Tea',
            'slug' => 'milk-tea',
            'is_active' => true,
            'display_order' => 1,
        ]);
        $keep = Product::create([
            'category_id' => $category->id,
            'title' => 'Keep Me',
            'price' => 100,
            'is_active' => true,
            'in_stock' => true,
            'stock_quantity' => 10,
        ]);
        $deactivate = Product::create([
            'category_id' => $category->id,
            'title' => 'Deactivate Me',
            'price' => 120,
            'is_active' => true,
            'in_stock' => true,
            'stock_quantity' => 10,
        ]);
        $delete = Product::create([
            'category_id' => $category->id,
            'title' => 'Delete Me',
            'price' => 80,
            'is_active' => true,
            'in_stock' => true,
            'stock_quantity' => 10,
        ]);

        $this->from(route('admin.products.index'))
            ->post(route('admin.products.bulk'), [
                'ids' => [$deactivate->id],
                'bulk_action' => 'deactivate',
            ])
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('success');

        $this->assertFalse($deactivate->fresh()->is_active);
        $this->assertTrue($keep->fresh()->is_active);

        $this->from(route('admin.products.index'))
            ->post(route('admin.products.bulk'), [
                'ids' => [$delete->id],
                'bulk_action' => 'delete',
            ])
            ->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseMissing('products', ['id' => $delete->id]);
        $this->assertDatabaseHas('products', ['id' => $keep->id]);
    }

    public function test_admin_can_bulk_update_order_status_and_payment(): void
    {
        $this->actingAsAdmin();
        $first = $this->makeOrder('HT-1001', 'pending', 'pending');
        $second = $this->makeOrder('HT-1002', 'pending', 'pending');

        $this->from(route('admin.orders.index'))
            ->post(route('admin.orders.bulk'), [
                'ids' => [$first->id, $second->id],
                'bulk_action' => 'set_status',
                'status' => 'confirmed',
            ])
            ->assertRedirect(route('admin.orders.index'))
            ->assertSessionHas('success');

        $this->assertSame('confirmed', $first->fresh()->status);
        $this->assertSame('confirmed', $second->fresh()->status);

        $this->from(route('admin.orders.index'))
            ->post(route('admin.orders.bulk'), [
                'ids' => [$first->id],
                'bulk_action' => 'set_payment',
                'payment_status' => 'paid',
            ])
            ->assertRedirect(route('admin.orders.index'));

        $this->assertSame('paid', $first->fresh()->payment_status);
        $this->assertSame('pending', $second->fresh()->payment_status);
    }

    public function test_bulk_delete_skips_categories_that_still_have_products(): void
    {
        $this->actingAsAdmin();
        $empty = Category::create([
            'name' => 'Empty',
            'slug' => 'empty',
            'is_active' => true,
            'display_order' => 1,
        ]);
        $used = Category::create([
            'name' => 'Used',
            'slug' => 'used',
            'is_active' => true,
            'display_order' => 2,
        ]);
        Product::create([
            'category_id' => $used->id,
            'title' => 'Boba',
            'price' => 150,
            'is_active' => true,
            'in_stock' => true,
            'stock_quantity' => 5,
        ]);

        $this->from(route('admin.categories.index'))
            ->post(route('admin.categories.bulk'), [
                'ids' => [$empty->id, $used->id],
                'bulk_action' => 'delete',
            ])
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('categories', ['id' => $empty->id]);
        $this->assertDatabaseHas('categories', ['id' => $used->id]);
    }

    public function test_products_index_includes_bulk_controls(): void
    {
        $this->actingAsAdmin();
        $category = Category::create([
            'name' => 'Tea',
            'slug' => 'tea',
            'is_active' => true,
            'display_order' => 1,
        ]);
        Product::create([
            'category_id' => $category->id,
            'title' => 'Jasmine',
            'price' => 90,
            'is_active' => true,
            'in_stock' => true,
            'stock_quantity' => 4,
        ]);

        $this->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('data-bulk-form', false)
            ->assertSee('data-bulk-select-all', false)
            ->assertSee('name="bulk_action"', false);
    }

    protected function makeOrder(string $number, string $status, string $paymentStatus): Order
    {
        return Order::create([
            'order_number' => $number,
            'customer_name' => 'Test Customer',
            'customer_email' => 'customer@example.com',
            'customer_phone' => '03001234567',
            'delivery_city' => 'Sialkot',
            'delivery_address' => 'Test street',
            'subtotal' => 500,
            'delivery_fee' => 50,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'grand_total' => 550,
            'payment_method' => 'cod',
            'payment_status' => $paymentStatus,
            'status' => $status,
        ]);
    }
}
