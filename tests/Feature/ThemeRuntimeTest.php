<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Plugins\PluginManager;
use App\Services\Themes\InstalledThemeCatalog;
use App\Services\Themes\ThemeManager;
use App\Services\Themes\ThemeRuntime;
use App\Services\Themes\ThemeSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mavyn\Core\Themes\ThemeCatalog;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ThemeRuntimeTest extends TestCase
{
    use RefreshDatabase;

    private const HAPPY = 'mavyn.theme-happy-tea';

    private const CHILD = 'mavyn.theme-happy-tea-child';

    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('ordering_enabled', true);
        Setting::set('ordering_start_time', '00:00');
        Setting::set('ordering_end_time', '23:59');
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    private function actor(array $permissions = ['themes.view', 'themes.manage']): Admin
    {
        return Admin::create(['name' => 'Theme operator', 'email' => uniqid().'@example.com', 'password' => 'password', 'role' => 'custom', 'is_active' => true, 'permissions' => $permissions]);
    }

    private function configureCatalog(callable $change): void
    {
        $data = json_decode(file_get_contents(config('themes.catalog')), true);
        $change($data);
        $path = tempnam(sys_get_temp_dir(), 'mavyn-theme-test-');
        file_put_contents($path, json_encode($data));
        $this->temporaryFiles[] = $path;
        config(['themes.catalog' => $path]);
        app(ThemeRuntime::class)->reset();
    }

    private function previewUrl(Admin $actor, string $theme = self::CHILD, array $draft = []): string
    {
        $response = $this->actingAs($actor, 'admin')->post(route('admin.themes.preview', $theme), ['settings' => $draft]);
        $response->assertRedirect();

        return $response->headers->get('Location');
    }

    public function test_extracted_storefront_is_initially_selected_and_admin_stays_independent(): void
    {
        $this->assertSame(self::HAPPY, DB::table('theme_selection')->value('active_theme'));
        $this->assertCount(3, app(InstalledThemeCatalog::class)->load()->all());
        $this->get('/')->assertOk()->assertSee('data-storefront-theme="'.self::HAPPY.'"', false);
        $this->get(route('admin.login'))->assertOk()->assertDontSee('data-storefront-theme', false);
        $this->actingAs($this->actor(), 'admin')->get(route('admin.themes.index'))->assertOk()->assertSee('Preview draft');
    }

    public function test_child_overrides_home_and_inherits_product_layout_and_settings(): void
    {
        $category = Category::create(['name' => 'Catalog', 'slug' => 'catalog', 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'title' => 'Example', 'slug' => 'example', 'price' => 10, 'in_stock' => true, 'is_active' => true]);
        app(ThemeSettings::class)->save(self::HAPPY, ['welcome_text' => 'Parent content']);
        app(ThemeManager::class)->activate(self::CHILD);
        $this->get('/')->assertOk()->assertSee('data-child-theme-example', false)->assertSee('Parent content')->assertSee('#28735A');
        $this->get(route('product.show', $product->slug))->assertOk()->assertSee('Example')->assertSee('data-storefront-theme="'.self::CHILD.'"', false);
        $this->assertStringContainsString('/happy-tea/resources/views/pages/product.blade.php', app(ThemeRuntime::class)->template('pages.product'));
        $this->assertSame(self::HAPPY, DB::table('theme_selection')->value('previous_theme'));
    }

    public function test_missing_override_uses_default_and_settings_survive_switches(): void
    {
        $this->configureCatalog(function (&$data) {
            foreach ($data['packages'] as &$package) {
                if ($package['manifest']['id'] === self::HAPPY) {
                    $package['manifest']['templates'] = ['pages.home' => $package['manifest']['templates']['pages.home']];
                }
            }
        });
        app(ThemeSettings::class)->save(self::HAPPY, ['welcome_text' => 'Retained welcome']);
        $this->get(route('about'))->assertOk()->assertSee('Welcome to our store.');
        $this->assertStringContainsString('/default/resources/views/pages/about.blade.php', app(ThemeRuntime::class)->template('pages.about'));
        app(ThemeManager::class)->activate(ThemeCatalog::DEFAULT);
        $this->get('/')->assertOk()->assertSee('Explore our collection')->assertDontSee('data-child-theme-example', false);
        app(ThemeManager::class)->activate(self::HAPPY);
        $this->get('/')->assertOk()->assertSee('Retained welcome');
    }

    public function test_invalid_active_selection_falls_back_without_changing_database(): void
    {
        DB::table('theme_selection')->update(['active_theme' => 'missing.theme']);
        $this->get('/')->assertOk()->assertSee('data-storefront-theme="'.ThemeCatalog::DEFAULT.'"', false);
        $this->assertSame('missing.theme', DB::table('theme_selection')->value('active_theme'));
        app(ThemeManager::class)->activate(ThemeCatalog::DEFAULT);
        $this->assertSame(ThemeCatalog::DEFAULT, DB::table('theme_selection')->value('active_theme'));
    }

    public function test_missing_active_resource_uses_default_and_rejects_reactivation(): void
    {
        $this->configureCatalog(function (&$data) {
            foreach ($data['packages'] as &$package) {
                if ($package['manifest']['id'] === self::HAPPY) {
                    $package['manifest']['templates']['pages.about'] = 'resources/views/pages/missing.blade.php';
                }
            }
        });
        $this->get('/')->assertOk()->assertSee('data-storefront-theme="'.ThemeCatalog::DEFAULT.'"', false);
        $this->expectException(\RuntimeException::class);
        app(ThemeManager::class)->activate(self::HAPPY);
    }

    public function test_draft_preview_is_private_authorized_and_does_not_persist_or_leak(): void
    {
        $url = $this->previewUrl($this->actor(), self::CHILD, ['welcome_text' => 'Private draft', 'accent_color' => '#112233']);
        $this->get($url)->assertOk()->assertSee('Private draft')->assertSee('data-child-theme-example', false)->assertSee('#112233')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertSame(self::HAPPY, DB::table('theme_selection')->value('active_theme'));
        $this->assertDatabaseCount('theme_settings', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->get('/')->assertOk()->assertDontSee('Private draft')->assertDontSee('data-child-theme-example', false);
    }

    public function test_preview_token_rejects_tampering_expiry_another_admin_and_revoked_grant(): void
    {
        $actor = $this->actor();
        $url = $this->previewUrl($actor);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->get('/?_theme_preview=broken')->assertForbidden();
        $this->actingAs($this->actor(), 'admin')->get($url)->assertForbidden();
        $this->actingAs($actor, 'admin');
        $payload = json_decode(Crypt::decryptString($query['_theme_preview']), true);
        $payload['expires'] = now()->subSecond()->timestamp;
        $expired = Crypt::encryptString(json_encode($payload));
        $this->get(route('home', ['_theme_preview' => $expired]))->assertForbidden();
        $actor->update(['permissions' => ['themes.view']]);
        $this->get($url)->assertForbidden();
    }

    public function test_preview_cannot_mutate_or_grant_customer_order_access(): void
    {
        $url = $this->previewUrl($this->actor());
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->postJson(route('cart.add', $query), [])->assertForbidden();
        $this->get(route('checkout.index', $query))->assertForbidden();
        $this->get(route('orders.track-guest', ['token' => 'unknown', ...$query]))->assertForbidden();
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_theme_permissions_and_atomic_invalid_setting_batch(): void
    {
        $viewer = $this->actor(['themes.view']);
        $this->actingAs($viewer, 'admin')->get(route('admin.themes.index'))->assertOk();
        $this->post(route('admin.themes.activate', self::CHILD))->assertForbidden();
        $this->putJson(route('admin.themes.settings', self::HAPPY), ['settings' => ['welcome_text' => 'No']])->assertForbidden();
        $this->actingAs($this->actor(), 'admin')->putJson(route('admin.themes.settings', self::HAPPY), ['settings' => ['welcome_text' => 'Must not save', 'accent_color' => 'red; background:url(x)']])->assertUnprocessable()->assertJsonValidationErrors('settings.accent_color');
        $this->assertDatabaseCount('theme_settings', 0);
        $this->put(route('admin.themes.settings', self::HAPPY), ['settings' => ['welcome_text' => '<script>unsafe</script>']])->assertSessionHasNoErrors();
        auth()->shouldUse('web');
        $this->get('/')->assertOk()->assertSee('&lt;script&gt;unsafe&lt;/script&gt;', false)->assertDontSee('<script>unsafe</script>', false);
    }

    public function test_required_plugin_blocks_activation_and_disabling_active_theme_dependency(): void
    {
        $this->configureCatalog(function (&$data) {
            foreach ($data['packages'] as &$package) {
                if ($package['manifest']['id'] === self::CHILD) {
                    $package['manifest']['requires_active'] = ['mavyn.local-delivery' => '^0.1'];
                }
            }
        });
        app(ThemeManager::class)->activate(self::CHILD);
        try {
            app(PluginManager::class)->change('mavyn.local-delivery', false);
            $this->fail('Active dependency disabled');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('plugin', $exception->errors());
        }
        $this->assertDatabaseHas('plugin_states', ['plugin_id' => 'mavyn.local-delivery', 'active' => true]);
        app(ThemeManager::class)->activate(ThemeCatalog::DEFAULT);
        app(PluginManager::class)->change('mavyn.local-delivery', false);
        $this->expectException(ValidationException::class);
        app(ThemeManager::class)->activate(self::CHILD);
    }

    public function test_repeated_activation_is_noop_and_audit_failure_rolls_back(): void
    {
        app(ThemeManager::class)->activate(self::CHILD);
        $revision = DB::table('extension_runtime')->value('revision');
        $count = AdminAuditLog::count();
        app(ThemeManager::class)->activate(self::CHILD);
        $this->assertSame($revision, DB::table('extension_runtime')->value('revision'));
        $this->assertSame($count, AdminAuditLog::count());
        $dispatcher = AdminAuditLog::getEventDispatcher();
        AdminAuditLog::setEventDispatcher(clone $dispatcher);
        AdminAuditLog::creating(fn () => throw new \RuntimeException('Injected audit failure'));
        try {
            app(ThemeManager::class)->activate(ThemeCatalog::DEFAULT);
            $this->fail('Audit failure accepted');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected audit failure', $exception->getMessage());
        } finally {
            AdminAuditLog::setEventDispatcher($dispatcher);
        }
        $this->assertSame(self::CHILD, DB::table('theme_selection')->value('active_theme'));
        $this->assertSame($revision, DB::table('extension_runtime')->value('revision'));
    }

    public function test_versioned_asset_urls_are_ordered_and_namespaced(): void
    {
        app(ThemeManager::class)->activate(self::CHILD);
        $state = app(ThemeRuntime::class)->state();
        $styles = $state['assets']['styles'];
        $this->assertStringContainsString('/themes/'.ThemeCatalog::DEFAULT.'/', $styles[0]);
        $this->assertStringContainsString('/themes/'.self::HAPPY.'/', $styles[2]);
        $this->assertStringContainsString('/themes/'.self::CHILD.'/', $styles[3]);
        $this->assertMatchesRegularExpression('~/[a-f0-9]{64}/theme.css$~', $styles[3]);
        $this->assertStringContainsString('/themes/'.self::HAPPY.'/', theme_asset('assets/Logo_Happytea.svg'));
    }

    public function test_store_content_is_escaped_and_retained_across_theme_switches(): void
    {
        $this->actingAs($this->actor(['settings.manage']), 'admin')->put(route('admin.settings.update'), ['about_text' => 'Merchant story <script>bad</script>'])->assertSessionHasNoErrors();
        app(ThemeManager::class)->activate(ThemeCatalog::DEFAULT);
        auth()->shouldUse('web');
        $this->get(route('about'))->assertOk()->assertSee('Merchant story &lt;script&gt;bad&lt;/script&gt;', false);
        app(ThemeManager::class)->activate(self::HAPPY);
        $this->assertSame('Merchant story <script>bad</script>', Setting::get('about_text'));
    }

    public function test_every_installed_template_compiles_to_valid_php(): void
    {
        $catalogs = app(InstalledThemeCatalog::class);
        foreach ($catalogs->load()->all() as $package) {
            foreach ($package['manifest']['templates'] as $name => $path) {
                $compiled = app('blade.compiler')->compileString(file_get_contents($catalogs->resource($package, $path)));
                $file = tempnam(sys_get_temp_dir(), 'mavyn-theme-lint-');
                $this->temporaryFiles[] = $file;
                file_put_contents($file, $compiled);
                $process = new Process([PHP_BINARY, '-l', $file]);
                $process->run();
                $this->assertTrue($process->isSuccessful(), $package['manifest']['id'].' '.$name.': '.$process->getOutput().$process->getErrorOutput());
            }
        }
    }
}
