<?php

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Mavyn\Core\Themes\ThemeCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ProjectSetupTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/mavyn-setup-'.bin2hex(random_bytes(8));
        $files = new Filesystem;
        $root = dirname(__DIR__, 2);
        $files->makeDirectory($this->directory.'/bin', 0755, true);
        foreach (['prepare-composer.php', 'copy-default-theme.php'] as $script) {
            $files->copy($root.'/bin/'.$script, $this->directory.'/bin/'.$script);
        }
        foreach (['composer.shared.json', 'composer.shared.lock'] as $file) {
            $files->copy($root.'/'.$file, $this->directory.'/'.$file);
        }
        $files->copyDirectory($root.'/resources/themes/default', $this->directory.'/resources/themes/default');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function prepare(): Process
    {
        $process = new Process([PHP_BINARY, 'bin/prepare-composer.php'], $this->directory);
        $process->run();

        return $process;
    }

    public function test_fresh_setup_copies_a_complete_prebuilt_theme_without_vendor_or_git(): void
    {
        $process = $this->prepare();
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('Copied the bundled default theme', $process->getOutput());
        $theme = $this->directory.'/themes/default';
        $package = json_decode(file_get_contents($theme.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        new ThemeCatalog([['package' => $package['name'], 'version' => '0.1.0', 'manifest' => $package['extra']['mavyn']]]);
        foreach ($package['extra']['mavyn']['templates'] as $path) {
            $this->assertFileExists($theme.'/'.$path);
        }
        $assets = json_decode(file_get_contents($theme.'/dist/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ([...$assets['styles'], ...$assets['scripts'], ...array_values($assets['files'])] as $entry) {
            $this->assertSame($entry['sha256'], hash_file('sha256', $theme.'/'.$entry['path']));
        }
        foreach (['app', 'auth', 'guest'] as $layout) {
            $html = file_get_contents($theme.'/resources/views/layouts/'.$layout.'.blade.php');
            $this->assertStringNotContainsString('@vite', $html);
            $this->assertStringContainsString('<script type="module"', $html);
        }
        $manifest = json_decode(file_get_contents($this->directory.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('^0.1', $manifest['require']['mavyn/theme-default']);
        $this->assertSame('@php bin/prepare-composer.php', $manifest['scripts']['setup'][0]);
        $this->assertSame('composer install', $manifest['scripts']['setup'][1]);
        $lock = json_decode(file_get_contents($this->directory.'/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
        $lockedTheme = array_values(array_filter($lock['packages'], fn ($package) => $package['name'] === 'mavyn/theme-default'));
        $this->assertCount(1, $lockedTheme);
        $this->assertSame('themes/default', $lockedTheme[0]['dist']['url']);
    }

    public function test_repeated_setup_preserves_customizations_removed_files_and_clone_lockfile(): void
    {
        $this->assertSame(0, $this->prepare()->getExitCode());
        $theme = $this->directory.'/themes/default';
        file_put_contents($theme.'/resources/views/pages/home.blade.php', 'My custom storefront');
        unlink($theme.'/resources/views/pages/about.blade.php');
        file_put_contents($this->directory.'/composer.lock', 'existing clone dependency graph');

        $process = $this->prepare();
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringNotContainsString('Copied the bundled default theme', $process->getOutput());
        $this->assertSame('My custom storefront', file_get_contents($theme.'/resources/views/pages/home.blade.php'));
        $this->assertFileDoesNotExist($theme.'/resources/views/pages/about.blade.php');
        $this->assertSame('existing clone dependency graph', file_get_contents($this->directory.'/composer.lock'));
    }

    public function test_existing_non_package_is_reported_without_overwriting_it(): void
    {
        mkdir($this->directory.'/themes');
        file_put_contents($this->directory.'/themes/default', 'keep this file');

        $process = $this->prepare();
        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('themes/default exists but is not a theme package', $process->getErrorOutput());
        $this->assertSame('keep this file', file_get_contents($this->directory.'/themes/default'));
        $this->assertFileDoesNotExist($this->directory.'/composer.json');
    }

    public function test_missing_bundle_fails_before_writing_generated_dependency_files(): void
    {
        unlink($this->directory.'/resources/themes/default/composer.json');
        $process = $this->prepare();
        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('The bundled default theme is missing', $process->getErrorOutput());
        $this->assertDirectoryDoesNotExist($this->directory.'/themes/default');
        $this->assertFileDoesNotExist($this->directory.'/composer.json');
        $this->assertFileDoesNotExist($this->directory.'/composer.lock');
    }

    public function test_older_client_default_requirement_and_custom_packages_are_preserved(): void
    {
        $client = ['require' => ['mavyn/theme-default' => '^0.1', 'acme/store-theme' => '^1.0'],
            'repositories' => [['type' => 'path', 'url' => 'themes/*']]];
        file_put_contents($this->directory.'/composer.client.json', json_encode($client, JSON_THROW_ON_ERROR));
        $process = $this->prepare();
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $manifest = json_decode(file_get_contents($this->directory.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('^0.1', $manifest['require']['mavyn/theme-default']);
        $this->assertSame('^1.0', $manifest['require']['acme/store-theme']);
        $this->assertContains($client['repositories'][0], $manifest['repositories']);

        $client['require']['mavyn/theme-default'] = '^9.0';
        file_put_contents($this->directory.'/composer.client.json', json_encode($client, JSON_THROW_ON_ERROR));
        $process = $this->prepare();
        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('cannot override shared requirement: mavyn/theme-default', $process->getErrorOutput());
    }
}
