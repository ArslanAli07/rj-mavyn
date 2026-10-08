<?php

namespace Tests\Unit;

use InvalidArgumentException;
use Mavyn\Contracts\Plugins\ProductDisplay;
use Mavyn\Core\Plugins\HookBus;
use Mavyn\Core\Plugins\PluginCatalog;
use Mavyn\Core\Plugins\PluginSetting;
use Mavyn\Core\Plugins\ProviderRegistry;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Plugins\FixturePaymentProvider;
use Tests\Fixtures\Plugins\FixturePlugin;
use UnexpectedValueException;

class PluginContractsTest extends TestCase
{
    private function package(string $id, array $extra = []): array
    {
        return ['package' => 'test/fixture', 'version' => '1.0.0', 'manifest' => ['kind' => 'plugin', 'id' => $id, 'name' => $id, 'provider' => FixturePlugin::class, ...$extra]];
    }

    public function test_dependency_order_is_deterministic(): void
    {
        $catalog = new PluginCatalog([$this->package('test.z'), $this->package('test.a', ['requires_active' => ['test.z' => '^1.0']]), $this->package('test.b')]);
        $this->assertSame(['test.z', 'test.a', 'test.b'], $catalog->order(['test.b', 'test.a', 'test.z']));
    }

    public function test_missing_and_incompatible_dependencies_conflicts_and_cycles_are_rejected(): void
    {
        $cases = [
            [[$this->package('test.a', ['requires_active' => ['test.b' => '^1']])], ['test.a']],
            [[$this->package('test.a', ['requires_active' => ['test.b' => '^2']]), $this->package('test.b')], ['test.a', 'test.b']],
            [[$this->package('test.a', ['conflicts_with' => ['test.b']]), $this->package('test.b')], ['test.a', 'test.b']],
            [[$this->package('test.a', ['requires_active' => ['test.b' => '*']]), $this->package('test.b', ['requires_active' => ['test.a' => '*']])], ['test.a', 'test.b']],
        ];
        foreach ($cases as [$packages,$active]) {
            try {
                (new PluginCatalog($packages))->order($active);
                $this->fail('Invalid combination accepted');
            } catch (InvalidArgumentException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function test_manifest_namespace_resource_and_secret_rules(): void
    {
        foreach ([['id' => 'invalid'], ['routes' => ['admin' => '../outside.php']], ['permissions' => ['core.manage' => 'Bad']], ['settings' => ['token' => ['type' => 'text', 'default' => 'secret', 'secret' => true]]], ['unknown' => true]] as $extra) {
            try {
                new PluginCatalog([$this->package('test.fixture', $extra)]);
                $this->fail('Invalid manifest accepted');
            } catch (InvalidArgumentException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
        $this->expectException(InvalidArgumentException::class);
        new PluginCatalog([$this->package('test.fixture'), $this->package('test.fixture')]);
    }

    public function test_hooks_use_priority_dependency_rank_and_contribution_id(): void
    {
        $hooks = new HookBus;
        $hooks->define('action', 'test.event', ProductDisplay::class);
        $order = [];
        foreach ([[10, 1, 'test.b', 'z'], [10, 0, 'test.a', 'z'], [10, 0, 'test.a', 'a'], [1, 2, 'test.c', 'x']] as [$priority,$rank,$plugin,$contribution]) {
            $hooks->add('action', 'test.event', $plugin, $contribution, function () use (&$order, $plugin, $contribution) {
                $order[] = $plugin.'.'.$contribution;
            }, $priority, $rank);
        }
        $hooks->dispatch('test.event', new ProductDisplay(1));
        $this->assertSame(['test.c.x', 'test.a.a', 'test.a.z', 'test.b.z'], $order);
        $this->expectException(InvalidArgumentException::class);
        $hooks->add('action', 'test.event', 'test.a', 'a', fn () => null, 10, 0);
    }

    public function test_filter_rejects_wrong_return_type(): void
    {
        $hooks = new HookBus;
        $hooks->define('filter', 'test.display', ProductDisplay::class);
        $hooks->add('filter', 'test.display', 'test.plugin', 'bad', fn () => [], 10, 0);
        $this->expectException(UnexpectedValueException::class);
        $hooks->apply('test.display', new ProductDisplay(1));
    }

    public function test_typed_providers_are_lazy_and_exclusive(): void
    {
        FixturePaymentProvider::$constructed = 0;
        $providers = new ProviderRegistry(fn ($class) => new $class);
        $providers->add('payment', 'test.payment', FixturePaymentProvider::class);
        $this->assertSame(0, FixturePaymentProvider::$constructed);
        $this->assertTrue($providers->get('payment', 'test.payment')->instructions()->requiresReceipt);
        $this->assertSame(1, FixturePaymentProvider::$constructed);
        $this->expectException(InvalidArgumentException::class);
        $providers->add('payment', 'test.payment', FixturePaymentProvider::class);
    }

    public function test_registries_reject_changes_after_registration_is_sealed(): void
    {
        $hooks = new HookBus;
        $hooks->define('action', 'test.event', ProductDisplay::class);
        $hooks->seal();
        try {
            $hooks->add('action', 'test.event', 'test.plugin', 'late', fn () => null, 10, 0);
            $this->fail('Late hook accepted');
        } catch (InvalidArgumentException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }
        $providers = new ProviderRegistry(fn ($class) => new $class);
        $providers->seal();
        $this->expectException(InvalidArgumentException::class);
        $providers->add('payment', 'test.late', FixturePaymentProvider::class);
    }

    public function test_plugin_setting_types_bounds_and_secret_metadata(): void
    {
        $setting = new PluginSetting('fee', ['type' => 'decimal', 'default' => '0.00', 'min' => 0, 'max' => 1000]);
        $this->assertSame('2.50', $setting->normalize('2.50'));
        foreach (['2.501', '-1', '11.00', [], true] as $invalid) {
            try {
                $setting->normalize($invalid);
                $this->fail('Invalid amount accepted');
            } catch (InvalidArgumentException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
        $this->assertFalse((new PluginSetting('enabled', ['type' => 'boolean', 'default' => true]))->normalize('0'));
    }
}
