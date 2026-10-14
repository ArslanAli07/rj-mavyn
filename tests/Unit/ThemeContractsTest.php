<?php

namespace Tests\Unit;

use InvalidArgumentException;
use Mavyn\Contracts\Themes\TemplateInventory;
use Mavyn\Core\Themes\ThemeCatalog;
use Mavyn\Core\Themes\ThemeSetting;
use PHPUnit\Framework\TestCase;

class ThemeContractsTest extends TestCase
{
    public function test_core_can_be_installed_before_client_themes(): void
    {
        $catalog = new ThemeCatalog([]);
        $this->assertSame([], $catalog->all());
        $this->expectException(InvalidArgumentException::class);
        $catalog->get(ThemeCatalog::DEFAULT);
    }

    private function packages(): array
    {
        $default = ['package' => 'mavyn/theme-default', 'version' => '0.1.0', 'manifest' => ['kind' => 'theme', 'id' => ThemeCatalog::DEFAULT, 'name' => 'Default', 'contract' => '^0.1', 'parent' => null, 'requires_active' => [], 'settings' => ['accent_color' => ['type' => 'color', 'default' => '#123456']], 'templates' => array_combine(TemplateInventory::NAMES, array_map(fn ($name) => 'resources/views/'.str_replace('.', '/', $name).'.blade.php', TemplateInventory::NAMES)), 'asset_manifest' => 'dist/manifest.json']];
        $parent = $default;
        $parent['manifest']['id'] = 'test.parent';
        $parent['manifest']['templates'] = [];
        $child = $parent;
        $child['manifest']['id'] = 'test.child';
        $child['manifest']['parent'] = 'test.parent';
        $child['manifest']['settings']['accent_color']['default'] = '#ABCDEF';

        return [$default, $parent, $child];
    }

    public function test_inheritance_and_settings_work_without_a_laravel_application(): void
    {
        $catalog = new ThemeCatalog($this->packages());
        $this->assertSame(['test.child', 'test.parent', ThemeCatalog::DEFAULT], $catalog->chain('test.child'));
        $this->assertSame('#ABCDEF', $catalog->settings('test.child')['accent_color']->definition['default']);
        $this->assertSame('#654321', $catalog->settings('test.child')['accent_color']->normalize('#654321'));
    }

    public function test_manifest_rejects_cycles_multiple_levels_missing_default_and_unsafe_templates(): void
    {
        $mutations = [
            fn (&$p) => $p[0]['manifest']['templates'] = [],
            fn (&$p) => $p[1]['manifest']['parent'] = 'test.child',
            fn (&$p) => $p[2]['manifest']['parent'] = 'missing.parent',
            fn (&$p) => $p[1]['manifest']['templates']['admin.dashboard'] = 'views/dashboard.blade.php',
            fn (&$p) => $p[1]['manifest']['templates']['pages.home'] = '../outside.blade.php',
            fn (&$p) => $p[1]['manifest']['contract'] = '^9.0',
            fn (&$p) => $p[2]['manifest']['settings']['accent_color'] = ['type' => 'integer', 'default' => 2],
        ];
        foreach ($mutations as $mutation) {
            $packages = $this->packages();
            $mutation($packages);
            try {
                new ThemeCatalog($packages);
                $this->fail('Invalid manifest accepted');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_parent_plugin_requirements_apply_to_child(): void
    {
        $packages = $this->packages();
        $packages[1]['manifest']['requires_active'] = ['test.provider' => '^1.0'];
        $catalog = new ThemeCatalog($packages);
        $catalog->validatePlugins('test.child', ['test.provider' => '1.2.0']);
        $this->addToAssertionCount(1);
        $this->expectException(InvalidArgumentException::class);
        $catalog->validatePlugins('test.child', ['test.provider' => '2.0.0']);
    }

    public function test_settings_reject_executable_css_and_unsupported_types(): void
    {
        foreach ([['color', 'red;url(x)'], ['script', '<script>bad</script>'], ['color', '#123'], ['integer', '1.2']] as [$type, $value]) {
            try {
                new ThemeSetting('visual', ['type' => $type, 'default' => $value]);
                $this->fail('Invalid value accepted');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
