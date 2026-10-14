<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\Category;
use App\Models\HomeSlide;
use App\Models\Product;
use App\Models\Setting;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class DatabaseBootstrapTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'bootstrap-fixture-password-123';

    protected function setUp(): void
    {
        parent::setUp();
        config(['site.name' => 'Store', 'platform.bootstrap_admin' => [
            'name' => 'Store Owner', 'email' => 'owner@example.test', 'password' => self::PASSWORD,
        ]]);
    }

    private function bootstrap(): void
    {
        $this->artisan('db:seed', ['--no-interaction' => true])->assertSuccessful();
    }

    public function test_migrations_and_default_seeding_never_add_demo_catalog_content(): void
    {
        // An old demo toggle must no longer turn default seeding into a store import.
        config(['platform.demo_seed' => true]);
        $this->bootstrap();
        $admin = Admin::sole();
        $this->assertSame('super_admin', $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check(self::PASSWORD, $admin->password));
        $this->assertStringNotContainsString(self::PASSWORD, AdminAuditLog::sole()->toJson());
        $this->assertSame('Store', Setting::get('site_name'));
        $this->assertSame('PKR', Setting::get('store_currency'));
        $this->assertSame('0.00', Setting::get('store_tax_rate'));
        $this->assertTrue(Setting::get('email_account_signup'));
        $this->assertDatabaseMissing('settings', ['key' => 'homepage_gallery']);
        $this->assertDatabaseMissing('settings', ['key' => 'site_logo']);
        foreach (['products', 'product_variants', 'product_images', 'product_option_groups', 'product_option_values', 'categories', 'occasions', 'home_slides', 'delivery_zones', 'coupons', 'users', 'addresses', 'orders', 'order_items', 'faqs', 'testimonials', 'newsletter_subscribers', 'contact_messages', 'seo_meta', 'media', 'media_assets', 'media_usages'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->get(route('home'))->assertOk();
        $this->get(route('shop'))->assertOk();
        $this->get(route('admin.login'))->assertOk();
    }

    public function test_seeding_does_not_reinterpret_existing_catalog_configuration(): void
    {
        config(['site.currency' => 'USD', 'site.currency_symbol' => '$', 'site.tax_rate' => '8.25',
            'site.country' => 'United States', 'site.country_code' => 'US', 'site.timezone' => 'America/New_York']);
        $category = Category::create(['name' => 'Existing USD catalog']);
        $product = Product::create(['category_id' => $category->id, 'title' => 'Existing product', 'price' => '10.00']);
        $this->bootstrap();
        $this->assertSame('USD', Setting::get('store_currency'));
        $this->assertSame('$', Setting::get('store_currency_symbol'));
        $this->assertSame('8.25', Setting::get('store_tax_rate'));
        $this->assertSame('US', Setting::get('store_country_code'));
        $this->assertSame('America/New_York', Setting::get('store_timezone'));
        $this->assertSame('10.00', $product->fresh()->price);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_repeated_seeding_preserves_accounts_configuration_and_existing_store_content(): void
    {
        $this->bootstrap();
        $admin = Admin::sole();
        $admin->update(['name' => 'Existing Owner']);
        $password = $admin->password;
        Setting::set('site_name', 'Existing Brand');
        Setting::set('store_tax_rate', '5.00');
        $category = Category::create(['name' => 'Existing category']);
        $product = Product::create(['title' => 'Existing product', 'category_id' => $category->id, 'price' => '10.00']);
        $slide = HomeSlide::create(['title' => 'Existing banner', 'image_path' => 'images/existing.jpg']);
        $settings = DB::table('settings')->orderBy('id')->get()->toJson();
        config(['platform.bootstrap_admin.email' => 'changed@example.test', 'platform.bootstrap_admin.password' => 'another-strong-password']);
        $this->bootstrap();
        $this->assertDatabaseCount('admins', 1);
        $this->assertSame('Existing Owner', $admin->fresh()->name);
        $this->assertSame($password, $admin->fresh()->password);
        $this->assertSame($settings, DB::table('settings')->orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('home_slides', 1);
        $this->assertSame('Existing product', $product->fresh()->title);
        $this->assertSame('images/existing.jpg', $slide->fresh()->image_path);
    }

    public function test_interactive_first_setup_prompts_for_credentials_without_a_fixed_password(): void
    {
        config(['platform.bootstrap_admin.email' => null, 'platform.bootstrap_admin.password' => null]);
        $this->artisan('db:seed')
            ->expectsQuestion('Initial admin email', 'prompted@example.test')
            ->expectsQuestion('Initial admin password (at least 12 characters)', self::PASSWORD)
            ->assertSuccessful();
        $this->assertSame('prompted@example.test', Admin::sole()->email);
        $this->assertTrue(Hash::check(self::PASSWORD, Admin::sole()->password));
    }

    public function test_noninteractive_setup_without_credentials_has_no_guessable_admin_account(): void
    {
        config(['platform.bootstrap_admin.email' => null, 'platform.bootstrap_admin.password' => null]);
        $this->bootstrap();
        $this->assertDatabaseCount('admins', 0);
        $this->assertDatabaseHas('settings', ['key' => 'store_currency', 'value' => 'PKR']);
    }

    public function test_invalid_initial_credentials_roll_back_bootstrap_changes(): void
    {
        config(['platform.bootstrap_admin.password' => 'short']);
        $before = DB::table('settings')->orderBy('id')->get()->toJson();
        try {
            (new DatabaseSeeder)->setContainer(app())->__invoke();
            $this->fail('Weak bootstrap credentials were accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('password', $exception->errors());
        }
        $this->assertDatabaseCount('admins', 0);
        $this->assertDatabaseCount('admin_audit_logs', 0);
        $this->assertSame($before, DB::table('settings')->orderBy('id')->get()->toJson());
    }

    public function test_explicit_demo_seeder_rejects_existing_store_data(): void
    {
        $category = Category::create(['name' => 'Real category']);
        $before = DB::table('settings')->orderBy('id')->get()->toJson();
        try {
            (new DemoDatabaseSeeder)->run();
            $this->fail('Demo seeding accepted existing store data.');
        } catch (LogicException) {
            $this->assertSame('Real category', $category->fresh()->name);
        }
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('admins', 0);
        $this->assertSame($before, DB::table('settings')->orderBy('id')->get()->toJson());
    }

    public function test_explicit_demo_seeder_cannot_run_in_production(): void
    {
        $this->app->instance('env', 'production');
        try {
            (new DemoDatabaseSeeder)->run();
            $this->fail('Production demo seeding was accepted.');
        } catch (LogicException) {
            $this->assertDatabaseCount('products', 0);
            $this->assertDatabaseCount('admins', 0);
        } finally {
            $this->app->instance('env', 'testing');
        }
    }
}
