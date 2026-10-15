<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Services\Pricing\PricingBackfill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mavyn\Contracts\Pricing\PriceBook;
use Tests\TestCase;

final class CurrencyPriceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $actor = Admin::create(['name' => 'Review fixture', 'email' => 'review@example.test', 'password' => 'fixture-only-password', 'role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($actor, 'admin');
        auth()->shouldUse('web');
    }

    public function test_review_backfill_must_not_restore_a_deliberately_deleted_price(): void
    {
        $category = Category::create(['name' => 'Review category']);
        $product = Product::create(['category_id' => $category->id, 'title' => 'Review jacket', 'price' => '150.00', 'is_active' => true, 'in_stock' => true]);
        $variant = $product->variants()->create(['name' => 'Regular', 'weight' => 'Regular', 'price' => '150.00', 'is_default' => true, 'is_active' => true]);
        $prices = app(PriceBook::class);
        $prices->save('PKR', [['kind' => 'variant', 'entity_id' => $variant->id, 'delete' => true]], $prices->context()->revision, (string) Str::uuid());
        $this->assertSame([], $prices->read('variant', [$variant->id], 'PKR'));
        app(PricingBackfill::class)->run();
        $this->assertSame([], $prices->read('variant', [$variant->id], 'PKR'), 'Backfill must preserve an intentional price deletion, rather than republishing the legacy amount.');
    }

    public function test_review_changing_coupon_type_must_not_leave_unusable_foreign_rules(): void
    {
        $prices = app(PriceBook::class);
        $coupon = Coupon::create(['code' => 'REVIEW', 'type' => 'percentage', 'amount' => '10.00', 'minimum_order' => '0.00', 'is_active' => true]);
        $prices->enable(['PKR', 'USD'], $prices->context()->revision, (string) Str::uuid());
        $prices->save('USD', [['kind' => 'coupon', 'entity_id' => $coupon->id, 'amount_minor' => null, 'minimum_minor' => 0]], $prices->context()->revision, (string) Str::uuid());
        $prices->select('USD', $prices->context()->revision, (string) Str::uuid());
        $coupon->update(['type' => 'fixed', 'amount' => '5.00']);
        $discount = $coupon->fresh()->calculateDiscount('100.00');
        $this->assertSame('0.00', $discount, 'An unpriced fixed coupon must become ineligible without crashing.');
    }

    public function test_review_unchanged_number_still_requires_coupon_kind_migration(): void
    {
        $coupon = Coupon::create(['code' => 'REVIEW2', 'type' => 'percentage', 'amount' => '10.00', 'minimum_order' => '0.00', 'is_active' => true]);
        $coupon->update(['type' => 'fixed']);
        $this->assertSame(1000, $coupon->fresh()->currencyRule()['amount_minor'], 'Changing a 10 percent coupon to a fixed 10 must write its fixed amount.');
    }
}
