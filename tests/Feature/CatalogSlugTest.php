<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Occasion;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogSlugTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = Admin::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($admin, 'admin');
    }

    public static function catalogTypes(): array
    {
        return [['products', Product::class, 'title'], ['categories', Category::class, 'name'], ['occasions', Occasion::class, 'name']];
    }

    private function payload(string $type, string $name): array
    {
        if ($type !== 'products') {
            return ['name' => $name, 'display_order' => 0, 'is_active' => true];
        }

        $category = Category::firstOrCreate(['slug' => 'test-parent'], ['name' => 'Test parent']);

        return ['title' => $name, 'category_id' => $category->id, 'display_order' => 0, 'stock_quantity' => 0, 'is_active' => true, 'in_stock' => true,
            'variants' => [['name' => 'Standard', 'price' => 100, 'is_default' => true, 'is_active' => true]]];
    }

    #[DataProvider('catalogTypes')]
    public function test_generated_slug_collisions_are_validation_errors(string $type, string $model, string $field): void
    {
        $this->postJson(route('admin.'.$type.'.store'), $this->payload($type, 'Travel Bag'))->assertRedirect()->assertSessionHasNoErrors();
        $original = $model::where('slug', 'travel-bag')->sole();
        foreach (['Travel Bag', 'Travel--Bag'] as $name) {
            $this->postJson(route('admin.'.$type.'.store'), $this->payload($type, $name))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertSame('Travel Bag', $original->fresh()->getAttribute($field));
        $this->assertSame(1, $model::where('slug', 'travel-bag')->count());
    }

    #[DataProvider('catalogTypes')]
    public function test_names_without_url_characters_are_rejected(string $type, string $model, string $field): void
    {
        $this->postJson(route('admin.'.$type.'.store'), $this->payload($type, '!!!'))->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame(0, $model::where('slug', '')->count());
    }

    #[DataProvider('catalogTypes')]
    public function test_slug_collision_after_validation_is_reported_as_validation_error(string $type, string $model, string $field): void
    {
        $payload = $this->payload($type, 'Race fixture');
        $model::creating(function ($record) {
            if ($record->slug === 'race-fixture') {
                // Reproduce another insert winning after the availability check.
                $attributes = $record->getAttributes();
                if ($record instanceof Product) {
                    $attributes['sku'] = 'RACE-WINNER-SKU';
                }
                DB::table($record->getTable())->insert($attributes);
            }
        });
        $this->postJson(route('admin.'.$type.'.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame(0, $model::where('slug', 'race-fixture')->count());
    }

    public function test_explicit_slugs_can_distinguish_categories_and_occasions_with_matching_names(): void
    {
        foreach (['categories' => Category::class, 'occasions' => Occasion::class] as $type => $model) {
            $this->postJson(route('admin.'.$type.'.store'), $this->payload($type, 'Travel Bag'))->assertRedirect();
            $this->postJson(route('admin.'.$type.'.store'), ['name' => 'Travel Bag', 'slug' => 'different-url'])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame(2, $model::where('name', 'Travel Bag')->count());
            $this->postJson(route('admin.'.$type.'.store'), ['name' => 'Other name', 'slug' => 'different-url'])->assertUnprocessable()->assertJsonValidationErrors('slug');
        }
    }

    public function test_blank_category_slug_on_update_preserves_the_existing_url(): void
    {
        $category = Category::create(['name' => 'Existing', 'slug' => 'existing']);
        $this->putJson(route('admin.categories.update', $category), ['name' => 'Renamed', 'slug' => ''])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('existing', $category->fresh()->slug);
    }
}
