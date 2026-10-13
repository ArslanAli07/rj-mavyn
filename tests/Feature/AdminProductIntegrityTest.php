<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOptionGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProductIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = Admin::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($admin, 'admin');
        $category = Category::create(['name' => 'Accessories', 'slug' => 'accessories']);
        $this->product = Product::create(['category_id' => $category->id, 'title' => 'Bag', 'price' => 100, 'is_active' => true, 'in_stock' => true]);
        $this->product->variants()->create(['name' => 'Standard', 'weight' => 'Standard', 'price' => 100, 'is_default' => true, 'is_active' => true]);
    }

    private function group(Product $product, string $name): ProductOptionGroup
    {
        $group = $product->optionGroups()->create(['name' => $name, 'selection_type' => 'multiple', 'min_selections' => 0, 'is_active' => true]);
        $group->values()->create(['name' => 'Original choice', 'price_adjustment' => 0, 'is_active' => true]);

        return $group;
    }

    private function payload(array $groups = []): array
    {
        return ['title' => 'Bag', 'category_id' => $this->product->category_id, 'display_order' => 0, 'stock_quantity' => 0, 'is_active' => true, 'in_stock' => true,
            'variants' => [['id' => $this->product->variants()->first()->id, 'name' => 'Standard', 'price' => 100, 'is_default' => true, 'is_active' => true]],
            'option_groups' => $groups];
    }

    private function groupPayload(ProductOptionGroup $group, int $valueId): array
    {
        return ['id' => $group->id, 'name' => $group->name, 'selection_type' => 'multiple', 'min_selections' => 0, 'is_active' => true,
            'values' => [['id' => $valueId, 'name' => 'Submitted choice', 'price_adjustment' => 0, 'is_active' => true]]];
    }

    public function test_foreign_option_value_is_rejected_without_deleting_existing_choices(): void
    {
        $own = $this->group($this->product, 'Own group');
        $other = Product::create(['category_id' => $this->product->category_id, 'title' => 'Other bag', 'price' => 100]);
        $foreign = $this->group($other, 'Foreign group');
        $this->putJson(route('admin.products.update', $this->product), $this->payload([$this->groupPayload($own, $foreign->values()->first()->id)]))->assertUnprocessable()->assertJsonValidationErrors('option_groups.0.values.0.id');
        $this->assertSame('Original choice', $own->values()->sole()->name);
        $this->assertSame('Original choice', $foreign->values()->sole()->name);
    }

    public function test_option_value_from_another_group_on_same_product_is_rejected(): void
    {
        $first = $this->group($this->product, 'First group');
        $second = $this->group($this->product, 'Second group');
        $this->putJson(route('admin.products.update', $this->product), $this->payload([$this->groupPayload($first, $second->values()->first()->id)]))->assertUnprocessable()->assertJsonValidationErrors('option_groups.0.values.0.id');
        $this->assertSame(2, $this->product->optionGroups()->count());
        $this->assertSame(1, $first->values()->count());
    }

    public function test_duplicate_variant_ids_are_rejected(): void
    {
        $payload = $this->payload();
        $payload['variants'][] = array_replace($payload['variants'][0], ['name' => 'Second name']);
        $this->putJson(route('admin.products.update', $this->product), $payload)->assertUnprocessable()->assertJsonValidationErrors('variants.0.id');
        $this->assertSame('Standard', $this->product->variants()->sole()->name);
    }

    public function test_duplicate_option_group_ids_are_rejected(): void
    {
        $group = $this->group($this->product, 'Options');
        $first = $this->groupPayload($group, $group->values()->first()->id);
        $second = array_replace($first, ['name' => 'Duplicate group']);
        $this->putJson(route('admin.products.update', $this->product), $this->payload([$first, $second]))->assertUnprocessable()->assertJsonValidationErrors('option_groups.0.id');
        $this->assertSame('Options', $group->fresh()->name);
    }

    public function test_replacing_legacy_main_image_preserves_the_historical_original(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/historical.jpg', 'historical bytes');
        $this->product->update(['main_image' => 'products/historical.jpg']);
        $payload = $this->payload();
        $payload['main_image'] = UploadedFile::fake()->image('new.jpg');
        $this->put(route('admin.products.update', $this->product), $payload)->assertSessionHasNoErrors();
        $this->assertNotSame('products/historical.jpg', $this->product->fresh()->main_image);
        Storage::disk('public')->assertExists('products/historical.jpg');
        Storage::disk('public')->assertExists($this->product->fresh()->main_image);
    }
}
