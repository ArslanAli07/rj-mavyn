<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOptionGroup;
use App\Services\CartService;
use App\Services\CheckoutAttemptService;
use App\Services\PaymentMethodService;
use App\Services\Plugins\PluginRuntime;
use App\Services\ProductCustomizationService;
use App\Services\Themes\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mavyn\Contracts\Plugins\PaymentInstructions;
use Mavyn\Contracts\SettingsStore;
use Tests\TestCase;

/** The Style / Size / Extras builder: swatches, text inputs, conditional groups, design files. */
class ProductBuilderTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    /** @var array<string, int> value ids by "Group: Value" */
    private array $v = [];

    private array $g = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $category = Category::create(['name' => 'Race suits', 'is_active' => true]);
        $this->product = Product::create(['category_id' => $category->id, 'title' => 'Custom race suit', 'price' => '899.00', 'is_active' => true, 'in_stock' => true, 'allows_design_upload' => true]);
        $this->product->variants()->create(['name' => 'Regular', 'weight' => 'Regular', 'price' => '899.00', 'is_default' => true, 'is_active' => true]);

        $this->group('Product', 'single', 1, 1, 'style', 'tiles', [['1 Piece Suit', 0], ['2 Piece Suit', 25]]);
        $this->group('Colors', 'multiple', 1, 4, 'style', 'swatches', [['Black', 0, '#111111'], ['Red', 0, '#d71f26'], ['White', 0, '#ffffff'], ['Custom Color', 50, null, 'Custom colour (Pantone)']]);
        $this->group('Matching Gear', 'multiple', 0, null, 'extras', 'tiles', [['Gloves', 125], ['Boots', 175]]);
        $this->group('Gloves size', 'single', 1, 1, 'extras', 'tiles', [['M', 0], ['L', 0]], dependsOn: 'Matching Gear: Gloves');
    }

    /** @param list<array> $values [name, price, swatch?, input_label?] */
    private function group(string $name, string $type, int $min, ?int $max, string $step, string $display, array $values, ?string $dependsOn = null): void
    {
        $group = $this->product->optionGroups()->create([
            'name' => $name, 'selection_type' => $type, 'min_selections' => $min, 'max_selections' => $max, 'is_required' => $min > 0, 'is_active' => true,
            'sort_order' => count($this->g), 'step' => $step, 'display' => $display, 'depends_on_value_id' => $dependsOn ? $this->v[$dependsOn] : null,
        ]);
        $this->g[$name] = $group->id;
        foreach ($values as $i => $value) {
            $this->v["$name: {$value[0]}"] = $group->values()->create([
                'name' => $value[0], 'price_adjustment' => $value[1], 'is_active' => true, 'sort_order' => $i,
                'swatch' => $value[2] ?? null, 'input_label' => $value[3] ?? null,
            ])->id;
        }
    }

    private function ids(string ...$names): array
    {
        return array_map(fn ($n) => $this->v[$n], $names);
    }

    private function add(array $overrides = [], ?array $files = null)
    {
        $data = array_replace([
            'product_id' => $this->product->id, 'quantity' => 1,
            'option_value_ids' => $this->ids('Product: 2 Piece Suit', 'Colors: Black', 'Colors: Red'),
        ], $overrides);
        if ($files !== null) {
            $data['design_files'] = $files;
        }

        return $this->postJson(route('cart.add'), $data);
    }

    public function test_priced_options_add_up_and_the_cart_shows_pick_order_and_text(): void
    {
        $this->add(['option_value_ids' => $this->ids('Product: 2 Piece Suit', 'Colors: Red', 'Colors: Custom Color', 'Colors: Black'),
            'option_inputs' => [$this->v['Colors: Custom Color'] => '  PMS   186 C '],
            'pick_order' => [$this->g['Colors'] => implode(',', $this->ids('Colors: Red', 'Colors: Custom Color', 'Colors: Black'))],
            'special_request' => 'white stripes',
        ])->assertOk();

        $item = CartItem::sole();
        $this->assertSame('974.00', $item->price, '899 + 25 two-piece + 50 custom colour');
        $this->assertSame([['value_id' => $this->v['Colors: Custom Color'], 'label' => 'Custom colour (Pantone)', 'value' => 'PMS 186 C']], $item->customization['inputs']);
        $this->assertSame(['Red', 'Custom Color', 'Black'], $item->customization['picks'][0]['values']);
        $this->get(route('cart.index'))->assertOk()->assertSee('Colors (in order)')->assertSee('1. Red')->assertSee('PMS 186 C');
    }

    public function test_option_that_asks_for_text_requires_it(): void
    {
        $this->add(['option_value_ids' => $this->ids('Product: 1 Piece Suit', 'Colors: Custom Color')])
            ->assertUnprocessable()->assertJsonPath('message', 'Please enter: Custom colour (Pantone).');
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_conditional_group_is_required_with_its_parent_and_rejected_without_it(): void
    {
        $this->add(['option_value_ids' => $this->ids('Product: 1 Piece Suit', 'Colors: Black', 'Matching Gear: Gloves')])
            ->assertUnprocessable()->assertJsonPath('message', 'Please select at least 1 option(s) for Gloves size.');
        $this->add(['option_value_ids' => $this->ids('Product: 1 Piece Suit', 'Colors: Black', 'Gloves size: M')])
            ->assertUnprocessable()->assertJsonPath('message', 'Gloves size is only available with the option it belongs to.');
        $this->add(['option_value_ids' => $this->ids('Product: 1 Piece Suit', 'Colors: Black', 'Matching Gear: Gloves', 'Gloves size: L')])->assertOk();
        $this->assertSame('1024.00', CartItem::sole()->price, '899 + 125 gloves');
    }

    public function test_colour_limits_and_required_groups_are_enforced(): void
    {
        $this->add(['option_value_ids' => $this->ids('Product: 1 Piece Suit')])->assertUnprocessable();
        $this->add(['option_value_ids' => $this->ids('Colors: Black')])->assertUnprocessable();
        $this->add(['option_value_ids' => [...$this->ids('Product: 1 Piece Suit', 'Colors: Black', 'Colors: Red', 'Colors: White'), $this->v['Colors: Custom Color']],
            'option_inputs' => [$this->v['Colors: Custom Color'] => 'x'], ])->assertOk();
        $this->add(['option_value_ids' => [...$this->ids('Product: 1 Piece Suit', 'Colors: Black', 'Colors: Red', 'Colors: White', 'Colors: Custom Color'), 99999]])->assertUnprocessable();
    }

    public function test_design_files_are_stored_privately_validated_and_cleaned_up(): void
    {
        $png = UploadedFile::fake()->create('my design.png', 120, 'image/png');
        $this->add(files: [$png])->assertOk();
        $item = CartItem::sole();
        $path = $item->customization['files'][0]['path'];
        $this->assertStringStartsWith('designs/', $path);
        $this->assertSame('my design.png', $item->customization['files'][0]['name']);
        Storage::disk('local')->assertExists($path);

        // A second add with a different file is a different line; an identical option set without a file is too.
        $this->add(files: [UploadedFile::fake()->create('other.pdf', 80, 'application/pdf')])->assertOk();
        $this->add()->assertOk();
        $this->assertDatabaseCount('cart_items', 3);
        $this->add()->assertOk();
        $this->assertDatabaseCount('cart_items', 3);
        $this->assertSame(2, CartItem::all()->filter(fn ($line) => empty($line->customization['files']))->sum('quantity'));

        // Removing a line removes its file; others stay.
        $this->deleteJson(route('cart.remove', $item->id))->assertOk();
        Storage::disk('local')->assertMissing($path);
        $this->assertCount(1, Storage::disk('local')->allFiles('designs'));
    }

    public function test_bad_design_files_are_rejected_without_leaving_files_or_lines(): void
    {
        $this->add(files: [UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')])->assertUnprocessable();
        $this->add(files: [UploadedFile::fake()->create('big.png', 11000, 'image/png')])->assertUnprocessable();
        $this->add(files: array_map(fn ($i) => UploadedFile::fake()->create("f$i.png", 10, 'image/png'), range(1, 4)))->assertUnprocessable();
        $this->add(['option_value_ids' => $this->ids('Product: 1 Piece Suit', 'Colors: Custom Color')], [UploadedFile::fake()->create('a.png', 10, 'image/png')])->assertUnprocessable();
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('designs'));

        $this->product->update(['allows_design_upload' => false]);
        $this->add(files: [UploadedFile::fake()->create('a.png', 10, 'image/png')])->assertUnprocessable();
        $this->assertSame([], Storage::disk('local')->allFiles('designs'));
    }

    public function test_customization_survives_checkout_into_the_order_and_staff_can_download_the_design(): void
    {
        $this->add(['option_value_ids' => $this->ids('Product: 1 Piece Suit', 'Colors: Custom Color', 'Matching Gear: Gloves', 'Gloves size: M'),
            'option_inputs' => [$this->v['Colors: Custom Color'] => 'PMS 186 C'], 'special_request' => 'note'],
            [UploadedFile::fake()->createWithContent('design.png', 'PNGDATA')])->assertOk();
        $line = CartItem::sole();

        // Re-pricing (what checkout does) keeps the customization intact.
        app(CartService::class)->reprice(collect([$line]), strict: true);
        $this->assertSame('PMS 186 C', $line->customization['inputs'][0]['value']);

        $order = $this->orderFromCart();
        $item = $order->items->sole();
        $this->assertSame('PMS 186 C', $item->customization['inputs'][0]['value']);
        $this->assertSame($line->customization['files'][0]['path'], $item->customization['files'][0]['path']);
        Storage::disk('local')->assertExists($item->customization['files'][0]['path']);

        $owner = Admin::create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'a-long-test-password', 'role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($owner, 'admin');
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Custom colour (Pantone)')->assertSee('PMS 186 C')->assertSee('Download design.png');
        $this->get(route('admin.orders.design', [$order, $item, 0]))->assertOk()->assertHeader('content-disposition');
        $this->get(route('admin.orders.design', [$order, $item, 5]))->assertNotFound();
        $this->get(route('admin.orders.invoice', $order))->assertOk();

        // Basic staff without the receipts permission cannot download private customer files.
        $viewer = Admin::create(['name' => 'Support', 'email' => 'support@example.test', 'password' => 'a-long-test-password', 'role' => 'custom', 'is_active' => true, 'permissions' => ['orders.view']]);
        $this->actingAs($viewer, 'admin')->get(route('admin.orders.design', [$order, $item, 0]))->assertForbidden();
    }

    public function test_admin_can_edit_builder_fields_and_older_forms_do_not_wipe_them(): void
    {
        $owner = Admin::create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'a-long-test-password', 'role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($owner, 'admin')->get(route('admin.products.edit', $this->product))->assertOk()->assertSee('Builder step')->assertSee('Customers can upload a design file');

        // A form from before this feature (no builder keys) keeps step, swatches, labels and dependencies.
        $payload = $this->adminPayload(withBuilder: false);
        $this->put(route('admin.products.update', $this->product), $payload)->assertSessionHasNoErrors();
        $group = ProductOptionGroup::find($this->g['Colors']);
        $this->assertSame('swatches', $group->display);
        $this->assertSame('#d71f26', $group->values->firstWhere('name', 'Red')->swatch);
        $this->assertSame('Custom colour (Pantone)', $group->values->firstWhere('name', 'Custom Color')->input_label);
        $this->assertSame($this->v['Matching Gear: Gloves'], ProductOptionGroup::find($this->g['Gloves size'])->depends_on_value_id);

        // The new form edits them, and a condition on a foreign value is dropped.
        $payload = $this->adminPayload(withBuilder: true);
        $payload['option_groups'][1]['values'][0]['swatch'] = '#000001';
        $payload['option_groups'][1]['help_text'] = 'Pick colours';
        $payload['option_groups'][0]['depends_on_value_id'] = 999999;
        $this->put(route('admin.products.update', $this->product), $payload)->assertSessionHasNoErrors();
        $this->assertSame('#000001', ProductOptionGroup::find($this->g['Colors'])->values->firstWhere('name', 'Black')->swatch);
        $this->assertSame('Pick colours', ProductOptionGroup::find($this->g['Colors'])->help_text);
        $this->assertNull(ProductOptionGroup::find($this->g['Product'])->depends_on_value_id);

        $payload['option_groups'][1]['values'][0]['swatch'] = 'red';
        $this->put(route('admin.products.update', $this->product), $payload)->assertSessionHasErrors('option_groups.1.values.0.swatch');
        $payload['option_groups'][1]['values'][0]['swatch'] = '#000001';
        $payload['allows_design_upload'] = '0';
        $this->put(route('admin.products.update', $this->product), $payload)->assertSessionHasNoErrors();
        $this->assertFalse($this->product->fresh()->allows_design_upload);
    }

    public function test_builder_page_renders_and_classic_products_keep_the_dropdown_layout(): void
    {
        // The builder lives in the RJ Motorbike storefront theme.
        app(ThemeManager::class)->activate('rjleather.theme-storefront');
        $this->get(route('product.show', $this->product->slug))->assertOk()->assertSee('rj-bld-steps', false)->assertSee('Add files or drop here')->assertSee('Gloves size')->assertSee('Custom colour (Pantone)');

        // Size step: Men / Women tiles labelled by EU size, and Custom Size with its extra cost; a nested size select under Gloves; the sticky bar.
        foreach (['US 40 · EU 50 · M' => '899.00', 'US 8 · EU 40 · XS' => '899.00', 'Custom Size (+ $125.00)' => '1024.00'] as $name => $price) {
            $this->product->variants()->create(['name' => $name, 'weight' => $name, 'price' => $price, 'is_active' => true]);
        }
        $this->get(route('product.show', $this->product->slug))->assertOk()
            ->assertSeeInOrder(['>Men<', '>Women<', 'EU 50', 'US 40 · M', 'EU 40', 'US 8 · XS', 'Or made to your measurements', 'Custom Size'], false)
            ->assertSee('bld-select-'.$this->g['Gloves size'], false)
            ->assertSee('rj-bld-bar', false)->assertSee('Still to choose');

        $classic = Product::create(['category_id' => $this->product->category_id, 'title' => 'Plain jacket', 'price' => '100.00', 'is_active' => true, 'in_stock' => true]);
        $classic->variants()->create(['name' => 'Regular', 'weight' => 'Regular', 'price' => '100.00', 'is_default' => true, 'is_active' => true]);
        $group = $classic->optionGroups()->create(['name' => 'Lining', 'selection_type' => 'single', 'min_selections' => 0, 'is_active' => true]);
        $group->values()->create(['name' => 'None', 'price_adjustment' => 0, 'is_active' => true]);
        $group->values()->create(['name' => 'Viscose', 'price_adjustment' => 0, 'is_active' => true]);
        $this->get(route('product.show', $classic->slug))->assertOk()->assertDontSee('rj-bld-steps', false)->assertSee('rj-option-dropdown', false);
    }

    private function adminPayload(bool $withBuilder): array
    {
        $product = $this->product->fresh(['variants', 'optionGroups.values']);
        $payload = [
            'title' => $product->title, 'category_id' => $product->category_id, 'price' => '899.00', 'display_order' => 0, 'stock_quantity' => 0,
            'in_stock' => 1, 'is_active' => 1, 'allows_design_upload' => '1', 'inventory_mode' => $product->inventory_mode,
            'variants' => $product->variants->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'price' => '899.00', 'is_active' => 1, 'is_default' => 1, 'in_stock' => 1])->all(),
            'option_groups' => $product->optionGroups->map(function ($g) use ($withBuilder) {
                $group = ['id' => $g->id, 'name' => $g->name, 'selection_type' => $g->selection_type, 'min_selections' => $g->min_selections, 'max_selections' => $g->max_selections,
                    'is_required' => (int) $g->is_required, 'is_active' => 1,
                    'values' => $g->values->map(function ($v) use ($withBuilder) {
                        $value = ['id' => $v->id, 'name' => $v->name, 'price_adjustment' => (float) $v->price_adjustment, 'is_active' => 1];

                        return $withBuilder ? $value + ['swatch' => $v->swatch ?? '', 'input_label' => $v->input_label ?? ''] : $value;
                    })->all()];

                return $withBuilder ? $group + ['step' => $g->step ?? '', 'display' => $g->display, 'help_text' => $g->help_text ?? '', 'depends_on_value_id' => $g->depends_on_value_id ?? ''] : $group;
            })->all(),
        ];

        return $payload;
    }

    /** Place the current cart as an order through the real placement service. */
    private function orderFromCart(): Order
    {
        $input = ['idempotency_key' => app(CheckoutAttemptService::class)->issue(),
            'first_name' => 'Test', 'last_name' => 'Rider', 'email' => 'rider@example.test', 'phone' => '+1 555 0100',
            'country' => 'United States', 'city' => 'Austin', 'address_line' => '1 Main St', 'payment_method' => 'cod'];

        // No payment plugin is installed in this fixture, so register one method for the ordering step only.
        $this->app->bind(PaymentMethodService::class, fn () => new class(app(PluginRuntime::class), app(SettingsStore::class)) extends PaymentMethodService
        {
            public function choices(?string $currency = null): array
            {
                return ['cod' => ['provider_key' => 'test.cod', 'instructions' => new PaymentInstructions('Cash on delivery', 'Pay on delivery'), 'gateway' => false, 'provider_class' => self::class]];
            }
        });
        $quote = $this->postJson(route('checkout.quote'), $input)->assertOk();
        $input['review_token'] = $quote->json('review_token');
        $response = $this->postJson(route('checkout.place-order'), $this->withHoneypot($input))->assertOk();

        return Order::with('items')->findOrFail($response->json('order_id'));
    }

    public function test_service_constants_are_sane(): void
    {
        $this->assertSame(3, ProductCustomizationService::MAX_FILES);
        $this->assertSame(10240, ProductCustomizationService::MAX_KB);
    }
}
