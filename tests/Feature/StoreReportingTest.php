<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Services\OrderStateService;
use App\Services\SalesReport;
use App\Support\Countries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_country_names_aliases_and_codes_resolve_to_iso_codes(): void
    {
        foreach (['United States' => 'US', 'usa' => 'US', 'U.S.A.' => 'US', 'us' => 'US', 'Pakistan' => 'PK', 'England' => 'GB',
            'the netherlands' => 'NL', 'Deutschland' => 'DE', 'Côte d’Ivoire' => 'CI'] as $input => $code) {
            $this->assertSame($code, Countries::code($input), $input);
        }
        $this->assertNull(Countries::code('Narnia'));
        $this->assertNull(Countries::code(''));
        $this->assertSame('United States', Countries::name('us'));
        $this->assertNull(Countries::name('ZZ'));
    }

    public function test_collected_revenue_excludes_unpaid_cancelled_and_refunded_money(): void
    {
        Carbon::setTestNow('2026-11-19 12:00:00');
        $paid = $this->order(['grand_total' => '998.00', 'delivery_fee' => '10.00', 'payment_status' => 'paid', 'status' => 'delivered']);
        $this->order(['grand_total' => '504.00', 'discount_amount' => '20.00', 'payment_status' => 'pending', 'status' => 'pending']);
        $this->order(['grand_total' => '300.00', 'payment_status' => 'pending', 'status' => 'cancelled']);
        $this->order(['grand_total' => '200.00', 'payment_status' => 'refunded', 'status' => 'cancelled']);
        $this->order(['grand_total' => '50.00', 'payment_status' => 'paid', 'currency' => 'EUR', 'currency_symbol' => '€']);
        $this->gatewayRefund($paid, 9800);

        $stats = (new SalesReport('USD'))->summary();

        $this->assertSame('900.00', $stats['collected'], 'paid 998 minus a 98 gateway refund');
        $this->assertSame('504.00', $stats['awaiting_payment']);
        $this->assertSame(1, $stats['awaiting_orders']);
        $this->assertSame('1502.00', $stats['booked_sales'], 'cancelled orders are not booked; other currencies excluded');
        $this->assertSame($stats['booked_sales'], $stats['total_sales']);
        $this->assertSame(2, $stats['booked_orders']);
        $this->assertSame('751.00', $stats['average_order_value']);
        $this->assertSame('20.00', $stats['total_discounts']);
        $this->assertSame('10.00', $stats['delivery_fees']);
        $this->assertSame('298.00', $stats['refunded'], '98 gateway refund plus a 200 order marked refunded');
    }

    public function test_today_and_daily_buckets_follow_the_store_timezone(): void
    {
        Setting::set('store_timezone', 'Asia/Karachi');
        // 2026-11-19 20:30 UTC is already 2026-11-20 01:30 in Karachi (UTC+5).
        Carbon::setTestNow('2026-11-19 20:30:00');
        $this->order(['grand_total' => '100.00', 'payment_status' => 'paid', 'created_at' => '2026-11-19 19:30:00']);
        $this->order(['grand_total' => '40.00', 'payment_status' => 'paid', 'created_at' => '2026-11-19 18:00:00']);

        $report = new SalesReport('USD');
        $stats = $report->summary();
        $this->assertSame('100.00', $stats['today_collected'], '19:30 UTC is 00:30 the next store day; 18:00 UTC is 23:00 the day before');
        $this->assertSame('100.00', $stats['today_booked']);

        $days = $report->daily(7);
        $this->assertSame('Nov 20', $days[6]['date']);
        $this->assertSame('100', $days[6]['revenue']);
        $this->assertSame('100', $days[6]['collected']);
        $this->assertSame('40', $days[5]['revenue']);
    }

    public function test_low_stock_ignores_untracked_made_to_order_products(): void
    {
        $admin = $this->admin();
        Product::query()->update(['is_active' => false]);
        $category = Category::create(['name' => 'Jackets', 'is_active' => true]);
        $untracked = Product::create(['category_id' => $category->id, 'title' => 'Made to order jacket', 'price' => '399.00', 'is_active' => true, 'in_stock' => true]);
        $shared = Product::create(['category_id' => $category->id, 'title' => 'Stocked gloves', 'price' => '40.00', 'is_active' => true, 'in_stock' => true]);
        DB::table('products')->where('id', $shared->id)->update(['inventory_mode' => 'shared', 'stock_quantity' => 3]);

        $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['low_stock'] === 1);
        $this->assertSame('untracked', $untracked->fresh()->inventory_mode);
    }

    public function test_order_address_method_and_fee_labels(): void
    {
        $order = $this->order(['delivery_address' => '123 Example Street', 'delivery_city' => 'Austin', 'delivery_postal_code' => '73301',
            'delivery_country' => 'United States', 'delivery_area' => 'Home Delivery', 'delivery_fee' => '0.00',
            'shipping_snapshot' => ['provider_key' => 'mavyn.home-delivery.standard', 'option_id' => 'home', 'label' => 'Home Delivery (Free)', 'fee_minor' => 0, 'currency' => 'USD']]);

        $this->assertSame('123 Example Street, Austin 73301, United States', $order->full_delivery_address);
        $this->assertSame('Home Delivery (Free)', $order->delivery_method_label);
        $this->assertSame('Free', $order->delivery_fee_label);

        $legacy = $this->order(['delivery_fee' => '0.00', 'shipping_snapshot' => null, 'delivery_area' => null]);
        $this->assertSame('To be confirmed', $legacy->delivery_fee_label);
    }

    public function test_cancelling_an_order_removes_its_units_from_best_seller_counts(): void
    {
        $category = Category::create(['name' => 'Jackets', 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'title' => 'Bomber', 'price' => '494.00', 'is_active' => true, 'in_stock' => true]);
        DB::table('products')->where('id', $product->id)->update(['sales_count' => 3]);
        $order = $this->order(['grand_total' => '988.00']);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_title' => 'Bomber', 'unit_price' => '494.00', 'quantity' => 2, 'total_price' => '988.00']);

        app(OrderStateService::class)->updateMany([$order->id], ['status' => 'cancelled', 'payment_status' => 'pending'], null);
        $this->assertSame(1, $product->fresh()->sales_count);

        // Saving an already-cancelled order again must not subtract twice.
        app(OrderStateService::class)->updateMany([$order->id], ['status' => 'cancelled', 'payment_status' => 'pending'], null);
        $this->assertSame(1, $product->fresh()->sales_count);
    }

    private function order(array $overrides = []): Order
    {
        $createdAt = $overrides['created_at'] ?? null;
        unset($overrides['created_at']);
        $order = Order::create(array_replace([
            'order_number' => 'ORD-'.Str::ulid(), 'customer_name' => 'Test Buyer', 'customer_email' => 'buyer@example.com',
            'customer_phone' => '+1 555 0100', 'delivery_country' => 'US', 'delivery_city' => 'Austin', 'delivery_address' => '1 Main St',
            'subtotal' => '0.00', 'delivery_fee' => '0.00', 'discount_amount' => '0.00', 'tax_amount' => '0.00', 'grand_total' => '0.00',
            'payment_method' => 'cod', 'payment_status' => 'pending', 'status' => 'pending', 'currency' => 'USD', 'currency_symbol' => '$',
            'tax_rate' => '0.00', 'inventory_managed' => false,
        ], $overrides));
        if ($createdAt) {
            DB::table('orders')->where('id', $order->id)->update(['created_at' => $createdAt]);
        }

        return $order->fresh();
    }

    private function gatewayRefund(Order $order, int $amountMinor): void
    {
        $attempt = (string) Str::uuid();
        DB::table('gateway_payment_attempts')->insert(array_intersect_key([
            'id' => $attempt, 'order_id' => $order->id, 'plugin_id' => 'test', 'provider_key' => 'test.gateway', 'provider_class' => 'Test', 'sequence' => 1,
            'amount_minor' => 99800, 'currency' => 'USD', 'status' => 'succeeded', 'captured' => true, 'owner_reference' => str_repeat('a', 64), 'owner_hash' => str_repeat('a', 64),
            'created_at' => now(), 'updated_at' => now(),
        ], array_flip(DB::getSchemaBuilder()->getColumnListing('gateway_payment_attempts'))));
        DB::table('gateway_refunds')->insert(['id' => (string) Str::uuid(), 'attempt_id' => $attempt, 'amount_minor' => $amountMinor,
            'currency' => 'USD', 'status' => 'succeeded', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function admin()
    {
        return Admin::create(['name' => 'Owner', 'email' => uniqid().'@example.com', 'password' => 'a-long-test-password', 'role' => 'super_admin', 'is_active' => true]);
    }
}
