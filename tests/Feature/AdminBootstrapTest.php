<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Coupon;
use App\Models\DeliveryZone;
use App\Models\Occasion;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Media\MediaLibraryService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_uses_bootstrap_assets_and_controls(): void
    {
        $response = $this->get(route('admin.login'))->assertOk();
        $this->assertBootstrapPage($response->getContent(), 'login');
        $response->assertSee('form-control', false)->assertSee('form-check-input', false);
    }

    public function test_all_admin_screens_render_bootstrap_forms_and_preserve_record_values(): void
    {
        Storage::fake('media_public');
        Bus::fake();
        $admin = Admin::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($admin, 'admin');

        $category = Category::create(['name' => 'Bootstrap category']);
        $product = Product::create(['title' => 'Bootstrap product', 'category_id' => $category->id, 'price' => 100, 'is_active' => true]);
        $product->variants()->create(['name' => 'Regular', 'weight' => 'Regular', 'price' => 100, 'is_default' => true, 'is_active' => true]);
        $occasion = Occasion::create(['name' => 'Birthday']);
        $coupon = Coupon::create(['code' => 'BOOTSTRAP', 'amount' => 10, 'is_active' => true]);
        $zone = DeliveryZone::create(['name' => 'Test zone']);
        $user = User::factory()->create();
        $message = ContactMessage::create(['name' => 'Sender', 'email' => 'sender@example.com', 'subject' => 'Question', 'message' => 'Hello.']);
        $order = Order::create(['order_number' => 'BOOTSTRAP-1', 'user_id' => $user->id, 'customer_name' => 'Buyer', 'customer_email' => 'buyer@example.com',
            'customer_phone' => '123', 'delivery_country' => 'Pakistan', 'delivery_city' => 'City', 'delivery_address' => 'Street',
            'subtotal' => 100, 'grand_total' => 100, 'payment_method' => 'cod', 'status' => 'pending', 'payment_status' => 'pending']);
        $order->items()->create(['product_id' => $product->id, 'product_title' => $product->title, 'product_sku' => 'SKU-01',
            'variant_name' => 'Regular', 'unit_price' => 100, 'quantity' => 1, 'total_price' => 100,
            'options_snapshot' => [['group_name' => 'Toppings', 'value_name' => 'Chocolate', 'price_adjustment' => 10]],
            'special_request' => 'Gift packaging']);
        $asset = app(MediaLibraryService::class)->upload(UploadedFile::fake()->image('preview.jpg'), ['title' => 'Preview image', 'alt_text' => 'Image alt text', 'visibility' => 'public'], $admin);

        $pages = [];
        foreach (['products', 'categories', 'occasions', 'delivery-zones', 'coupons'] as $section) {
            $pages['admin.'.$section.'.index'] = [];
            $pages['admin.'.$section.'.create'] = [];
        }
        foreach (['dashboard', 'workspace', 'orders.index', 'customers.index', 'newsletter.index', 'contact-messages.index', 'seo.index', 'settings.index', 'extensions.index', 'themes.index', 'staff.index', 'audit.index', 'media.index'] as $page) {
            $pages['admin.'.$page] = [];
        }
        foreach (['products' => $product, 'categories' => $category, 'occasions' => $occasion,
            'delivery-zones' => $zone, 'coupons' => $coupon] as $section => $record) {
            $pages['admin.'.$section.'.edit'] = [$record];
        }
        $pages['admin.orders.show'] = [$order];
        $pages['admin.customers.show'] = [$user];
        $pages['admin.contact-messages.show'] = [$message];
        $pages['admin.media.show'] = [$asset];

        foreach ($pages as $page => $parameters) {
            $response = $this->get(route($page, $parameters))->assertOk();
            $this->assertBootstrapPage($response->getContent(), $page);
            $response->assertSee('id="admin-sidebar"', false)->assertSee('data-bs-toggle="offcanvas"', false);
        }
        $this->get(route('admin.products.edit', $product))->assertSee('value="Bootstrap product"', false)->assertSee('productOptionsManager', false);
        $this->get(route('admin.media.show', $asset))->assertSee('value="Preview image"', false)->assertSee('value="Image alt text"', false);

        foreach (['pending', 'paid', 'failed', 'refunded'] as $paymentStatus) {
            $order->update(['payment_status' => $paymentStatus]);
            foreach (['admin.orders.index', 'admin.dashboard', 'admin.customers.show'] as $page) {
                $parameters = $page === 'admin.customers.show' ? [$user] : [];
                $this->get(route($page, $parameters))->assertOk()
                    ->assertSee('payment-status-'.$paymentStatus, false)
                    ->assertSee(ucfirst($paymentStatus));
            }
        }

        User::factory()->count(21)->create();
        $this->get(route('admin.customers.index'))->assertOk()->assertSee('pagination', false)->assertSee('page-link', false);
    }

    private function assertBootstrapPage(string $html, string $page): void
    {
        if ($directory = getenv('ADMIN_PREVIEW_DIR')) {
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            file_put_contents($directory.'/'.$page.'.html', $html);
        }
        $this->assertMatchesRegularExpression('/build\/assets\/admin-[^"\s]+\.css/', $html, $page);
        $this->assertDoesNotMatchRegularExpression('/build\/assets\/app-[^"\s]+\.(css|js)/', $html, $page);
        $document = new DOMDocument;
        // libxml's HTML4 parser cannot read Alpine's @/: attribute shorthand.
        $parseableHtml = preg_replace('/(\s)[@:]([\w.-]+)=/', '$1data-alpine-$2=', $html);
        @$document->loadHTML($parseableHtml);
        $xpath = new DOMXPath($document);
        foreach ($xpath->query('//*[@class]') as $element) {
            $this->assertDoesNotMatchRegularExpression('/(?:^|\s)(?:\w+:|(?:bg|text|border)-(?:cream|espresso)-|input-luxury|label-luxury|space-y-|grid-cols-|\w+-\[)/', $element->getAttribute('class'), $page);
        }
        foreach ($xpath->query('//input[not(@type="hidden")]|//select|//textarea') as $element) {
            $expected = $element->tagName === 'select' ? 'form-select' : (in_array($element->getAttribute('type'), ['checkbox', 'radio'], true) ? 'form-check-input' : 'form-control');
            $this->assertContains($expected, explode(' ', $element->getAttribute('class')), $page.' '.$element->getAttribute('name'));
        }
        foreach ($xpath->query('//button') as $element) {
            $this->assertContains('btn', explode(' ', $element->getAttribute('class')), $page.' button');
        }
    }
}
