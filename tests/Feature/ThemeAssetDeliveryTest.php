<?php

namespace Tests\Feature;

use App\Services\Themes\ThemeAssets;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class ThemeAssetDeliveryTest extends TestCase
{
    private string $directory;

    private string $publicDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/theme-assets-'.Str::random(12));
        $this->publicDirectory = $this->directory.'/unused-public';
        File::ensureDirectoryExists($this->directory.'/dist/assets');
        File::put($this->directory.'/dist/assets/theme.css', 'body { background: url(./picture.svg) }');
        File::put($this->directory.'/dist/assets/theme.js', 'import "./chunk.js";');
        File::put($this->directory.'/dist/assets/chunk.js', 'window.assetFixture = true;');
        File::put($this->directory.'/dist/assets/picture.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        File::put($this->directory.'/private.php', '<?php echo "private";');
        $entry = fn ($name) => ['path' => 'dist/assets/'.$name, 'sha256' => hash_file('sha256', $this->directory.'/dist/assets/'.$name)];
        File::put($this->directory.'/dist/manifest.json', json_encode(['format' => 1, 'styles' => [$entry('theme.css')], 'scripts' => [$entry('theme.js')],
            'files' => ['picture' => $entry('picture.svg'), 'chunk' => $entry('chunk.js')]], JSON_THROW_ON_ERROR));
        $catalog = json_decode(file_get_contents(config('themes.catalog')), true, 512, JSON_THROW_ON_ERROR);
        $package = $catalog['packages'][0];
        $package['path'] = substr($this->directory, strlen(base_path()) + 1);
        $package['manifest'] = ['kind' => 'theme', 'id' => 'test.theme-assets', 'name' => 'Asset fixture', 'contract' => '^0.1', 'parent' => null,
            'templates' => [], 'settings' => [], 'requires_active' => [], 'asset_manifest' => 'dist/manifest.json'];
        $catalog['packages'][] = $package;
        File::put($this->directory.'/catalog.json', json_encode($catalog, JSON_THROW_ON_ERROR));
        config(['themes.catalog' => $this->directory.'/catalog.json']);
        $this->app->usePublicPath($this->publicDirectory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function urls(): array
    {
        return app(ThemeAssets::class)->urls(['test.theme-assets']);
    }

    public function test_assets_load_from_packages_without_public_copies_or_session_cookies(): void
    {
        $this->artisan('platform:themes:assets')->assertSuccessful();
        $this->assertDirectoryDoesNotExist($this->publicDirectory);
        $urls = $this->urls();
        foreach ([$urls['styles'][0] => 'text/css; charset=UTF-8', $urls['scripts'][0] => 'text/javascript; charset=UTF-8', $urls['files']['picture'] => 'image/svg+xml'] as $url => $mime) {
            $response = $this->get($url)->assertOk()->assertHeader('Content-Type', $mime)->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('immutable', $response->headers->get('Cache-Control'));
            $this->assertFalse($response->headers->has('Set-Cookie'));
            $this->assertStringStartsWith($this->directory, $response->baseResponse->getFile()->getPathname());
        }
        $etag = $this->get($urls['styles'][0])->headers->get('ETag');
        $this->get($urls['styles'][0], ['If-None-Match' => $etag])->assertStatus(304);
        $this->assertDirectoryDoesNotExist($this->publicDirectory);
    }

    public function test_relative_images_and_module_imports_share_the_same_directory_and_revision(): void
    {
        $urls = $this->urls();
        $this->assertSame(dirname($urls['styles'][0]).'/picture.svg', $urls['files']['picture']);
        $this->assertSame(dirname($urls['scripts'][0]).'/chunk.js', $urls['files']['chunk']);
        $this->get($urls['files']['chunk'])->assertOk()->assertHeader('Content-Type', 'text/javascript; charset=UTF-8');
    }

    public function test_undeclared_files_wrong_revisions_unknown_themes_and_traversal_fail_closed(): void
    {
        $url = $this->urls()['styles'][0];
        $base = substr($url, 0, strpos($url, '/dist/'));
        $this->get($base.'/private.php')->assertNotFound();
        $this->get($base.'/dist/manifest.json')->assertNotFound();
        $this->get($base.'/dist/assets/../../private.php')->assertNotFound();
        $this->get(str_replace('test.theme-assets', 'unknown.theme', $url))->assertNotFound();
        $this->get(preg_replace('~/[a-f0-9]{64}/~', '/'.str_repeat('0', 64).'/', $url))->assertNotFound();
    }

    public function test_tampered_or_symlinked_assets_cannot_be_delivered(): void
    {
        $url = $this->urls()['styles'][0];
        File::put($this->directory.'/dist/assets/theme.css', 'changed without updating manifest');
        $this->get($url)->assertNotFound();
        unlink($this->directory.'/dist/assets/theme.css');
        symlink(base_path('.env'), $this->directory.'/dist/assets/theme.css');
        $this->get($url)->assertNotFound();
    }

    public function test_a_declared_asset_cannot_alias_private_php_even_inside_its_package(): void
    {
        unlink($this->directory.'/dist/assets/theme.css');
        symlink($this->directory.'/private.php', $this->directory.'/dist/assets/theme.css');
        $manifest = json_decode(file_get_contents($this->directory.'/dist/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $manifest['styles'][0]['sha256'] = hash_file('sha256', $this->directory.'/private.php');
        File::put($this->directory.'/dist/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        $revision = hash('sha256', json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $this->get(route('theme-assets.show', ['theme' => 'test.theme-assets', 'revision' => $revision, 'path' => 'dist/assets/theme.css']))->assertNotFound();
    }
}
