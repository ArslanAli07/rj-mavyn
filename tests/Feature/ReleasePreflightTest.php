<?php

namespace Tests\Feature;

use App\Services\Deployment\ReleaseManifest;
use App\Services\Deployment\ReleasePreflight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ReleasePreflightTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    private array $manifest;

    private array $backup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/mavyn-release-tests-'.bin2hex(random_bytes(8));
        foreach (['storage/framework/cache', 'storage/app/private', 'storage/app/public', 'backup'] as $path) {
            File::ensureDirectoryExists($this->directory.'/'.$path);
        }
        $this->app->useStoragePath($this->directory.'/storage');
        config(['platform.client' => 'stationery-pilot', 'session.cookie' => 'stationery-pilot_session', 'session.domain' => null, 'cache.prefix' => 'stationery-pilot:', 'queue.default' => 'database', 'queue.connections.database.queue' => 'stationery-pilot', 'notifications.queue' => 'stationery-pilot']);
        $this->manifest = app(ReleaseManifest::class)->build('test-candidate');
        file_put_contents($this->directory.'/manifest.json', json_encode($this->manifest));
        file_put_contents($this->directory.'/backup/database.dump', 'Synthetic backup fixture');
        $this->backup = ['format' => 1, 'client' => 'stationery-pilot', 'scope_hash' => app(ReleasePreflight::class)->scopeHash(), 'created_at' => time(), 'restore_verified' => true, 'files' => ['database.dump' => hash_file('sha256', $this->directory.'/backup/database.dump')]];
        $this->writeBackup();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function writeBackup(): void
    {
        file_put_contents($this->directory.'/backup/backup.json', json_encode($this->backup));
    }

    private function report(): array
    {
        return app(ReleasePreflight::class)->check($this->directory.'/manifest.json', $this->directory.'/backup/backup.json');
    }

    public function test_source_inventory_is_deterministic_and_excludes_client_data_and_runtime_caches(): void
    {
        $again = app(ReleaseManifest::class)->build('test-candidate');
        $this->assertSame($this->manifest, $again);
        $this->assertEmpty(array_filter(array_keys($again['files']), fn ($path) => str_starts_with($path, 'storage/') || str_starts_with($path, 'bootstrap/cache/') || str_starts_with($path, 'public/storage/') || str_starts_with($path, 'public/assets/uploads/') || $path === '.env'));
        $this->assertArrayHasKey('mavyn/core', $again['versions']);
        $this->assertArrayHasKey('public/index.php', $again['files']);
        $this->assertNotEmpty($again['vendor_hash']);
    }

    public function test_verified_read_only_check_and_optional_record_keep_orders_untouched(): void
    {
        $before = DB::table('orders')->count();
        $report = $this->report();
        $this->assertSame('verified', $report['outcome'], json_encode($report['checks']));
        $this->assertDatabaseCount('deployment_runs', 0);
        app(ReleasePreflight::class)->record($report);
        $this->assertDatabaseHas('deployment_runs', ['client_id' => 'stationery-pilot', 'release_id' => 'test-candidate', 'outcome' => 'verified']);
        $this->assertSame($before, DB::table('orders')->count());
    }

    public function test_changed_inventory_never_reports_or_records_a_verified_release(): void
    {
        $this->manifest['files']['app/Helpers/helpers.php'] = str_repeat('0', 64);
        file_put_contents($this->directory.'/manifest.json', json_encode($this->manifest));
        $report = $this->report();
        $this->assertSame('failed', $report['outcome']);
        $this->assertFalse($report['checks']['source_inventory']);
        $this->assertNull($report['release']);
        $this->assertSame([], $report['versions']);
        app(ReleasePreflight::class)->record($report);
        $this->assertDatabaseHas('deployment_runs', ['outcome' => 'failed', 'release_id' => null]);
    }

    public function test_wrong_client_old_unrestored_and_tampered_backups_fail(): void
    {
        foreach ([['client', 'different-pilot'], ['scope_hash', str_repeat('0', 64)], ['created_at', time() - 90000], ['restore_verified', false]] as [$key, $value]) {
            $before = $this->backup;
            $this->backup[$key] = $value;
            $this->writeBackup();
            $this->assertFalse($this->report()['checks']['backup']);
            $this->backup = $before;
        }
        $this->writeBackup();
        file_put_contents($this->directory.'/backup/database.dump', 'Changed backup');
        $this->assertFalse($this->report()['checks']['backup']);
    }

    public function test_backup_cannot_reference_files_outside_its_directory(): void
    {
        file_put_contents($this->directory.'/outside.dump', 'Outside');
        $this->backup['files'] = ['../outside.dump' => hash_file('sha256', $this->directory.'/outside.dump')];
        $this->writeBackup();
        $this->assertFalse($this->report()['checks']['backup']);
    }

    public function test_unknown_client_and_unscoped_cookie_cache_and_queues_fail_without_configuration_values(): void
    {
        config(['platform.client' => null, 'session.cookie' => 'shared-session', 'cache.prefix' => 'shared:', 'queue.connections.database.queue' => 'default', 'notifications.queue' => 'default', 'app.key' => 'fixture-key-marker']);
        $report = $this->report();
        $this->assertSame('failed', $report['outcome']);
        $this->assertFalse($report['checks']['client_identity']);
        $this->assertFalse($report['checks']['namespaces']);
        $this->assertStringNotContainsString('fixture-key-marker', json_encode($report));
        $this->assertArrayNotHasKey('exceptions', $report);
    }

    public function test_missing_migration_and_artifact_fail_and_command_returns_nonzero(): void
    {
        DB::table('migrations')->where('migration', '2026_10_09_000015_create_deployment_runs')->delete();
        $this->assertFalse($this->report()['checks']['migrations']);
        $this->assertSame(1, Artisan::call('platform:release:check', ['--manifest' => $this->directory.'/missing.json', '--backup' => $this->directory.'/missing-backup.json']));
        $result = json_decode(Artisan::output(), true);
        $this->assertSame('failed', $result['outcome']);
        $this->assertFalse($result['checks']['source_inventory']);
        $this->assertFalse($result['checks']['backup']);
        $this->assertDatabaseCount('deployment_runs', 0);
    }
}
