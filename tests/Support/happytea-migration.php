<?php

// Rehearse against a private disposable copy. Never migrate or write to the source store.
use App\Models\Admin;
use App\Models\Order;
use App\Services\Media\LegacyMediaMigrationService;
use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$sourcePath = $argv[1] ?? dirname($root).'/happytea';
$keep = in_array('--keep', $argv, true);
$source = Dotenv::parse(file_get_contents($sourcePath.'/.env'));
$server = Dotenv::parse(file_get_contents($root.'/.env'));
foreach ([$source, $server] as $config) {
    if (($config['DB_CONNECTION'] ?? '') !== 'mysql' || ! in_array($config['DB_HOST'] ?? '', ['127.0.0.1', 'localhost'], true)) {
        throw new RuntimeException('This rehearsal supports local MySQL only.');
    }
}
$database = 'mavyn_happytea_stage_'.bin2hex(random_bytes(8));
$directory = sys_get_temp_dir().'/'.$database;
mkdir($directory, 0700);
$fs = new Filesystem;
$connect = static fn (array $config, ?string $name = null) => new PDO('mysql:host='.$config['DB_HOST'].';port='.($config['DB_PORT'] ?? '3306').($name ? ';dbname='.$name : '').';charset=utf8mb4', $config['DB_USERNAME'], $config['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_STRINGIFY_FETCHES => true]);
$created = false;
$exitCode = 0;
$snapshot = static function (PDO $pdo): array {
    $result = [];
    foreach (['products' => ['id', 'title', 'slug', 'sku', 'price', 'discount_price'], 'product_variants' => ['id', 'product_id', 'name', 'weight', 'price', 'discount_price'],
        'orders' => ['id', 'order_number', 'tracking_token', 'user_id', 'customer_name', 'customer_email', 'subtotal', 'delivery_fee', 'discount_amount', 'tax_amount', 'grand_total', 'payment_method', 'payment_status', 'status'],
        'order_items' => ['id', 'order_id', 'product_id', 'product_title', 'product_sku', 'weight', 'variant_name', 'unit_price', 'quantity', 'total_price', 'options_snapshot', 'special_request'],
        'users' => ['id', 'name', 'email', 'password']] as $table => $candidates) {
        $columns = array_column($pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC), 'Field');
        $fields = array_values(array_intersect($candidates, $columns));
        $rows = $pdo->query('SELECT `'.implode('`,`', $fields).'` FROM `'.$table.'` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $result[$table] = ['count' => count($rows), 'hash' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)), 'fields' => $fields];
    }

    return $result;
};
try {
    $sourcePdo = $connect($source, $source['DB_DATABASE']);
    $adminPdo = $connect($server);
    $sourcePdo->exec('SET TRANSACTION READ ONLY');
    $sourcePdo->beginTransaction();
    $before = $snapshot($sourcePdo);
    $sourcePdo->rollBack();
    $dump = new Process(['mysqldump', '--host='.$source['DB_HOST'], '--port='.($source['DB_PORT'] ?? '3306'), '--user='.$source['DB_USERNAME'], '--single-transaction', '--skip-lock-tables', '--no-tablespaces', '--skip-add-locks', $source['DB_DATABASE']], env: ['MYSQL_PWD' => $source['DB_PASSWORD']]);
    $dump->setTimeout(120);
    $dump->mustRun();
    file_put_contents($directory.'/source.sql', $dump->getOutput());
    chmod($directory.'/source.sql', 0600);
    $adminPdo->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    $restore = new Process(['mysql', '--host='.$server['DB_HOST'], '--port='.($server['DB_PORT'] ?? '3306'), '--user='.$server['DB_USERNAME'], $database], env: ['MYSQL_PWD' => $server['DB_PASSWORD']]);
    $restore->setInput(fopen($directory.'/source.sql', 'r'));
    $restore->setTimeout(120);
    $restore->mustRun();
    $replica = $connect($server, $database);
    if ($snapshot($replica) !== $before) {
        throw new RuntimeException('Restored source snapshot does not match.');
    }
    $fs->copyDirectory($sourcePath.'/storage/app', $directory.'/storage/app');
    foreach (['framework/cache/data', 'framework/views', 'framework/sessions', 'logs', 'app/private', 'app/public'] as $path) {
        $fs->ensureDirectoryExists($directory.'/storage/'.$path);
    }
    $fs->ensureDirectoryExists($directory.'/public');
    foreach (['assets', 'images'] as $path) {
        if (is_dir($sourcePath.'/public/'.$path)) {
            $fs->copyDirectory($sourcePath.'/public/'.$path, $directory.'/public/'.$path);
        }
    }
    $fs->copyDirectory($root.'/public/build', $directory.'/public/build');
    symlink($directory.'/storage/app/public', $directory.'/public/storage');
    $fs->ensureDirectoryExists($directory.'/bootstrap/cache');
    $env = ['APP_ENV' => 'local', 'APP_NAME' => 'Happy Tea staging', 'APP_KEY' => $source['APP_KEY'], 'APP_DEBUG' => 'false', 'APP_URL' => 'http://127.0.0.1:8187',
        'DB_CONNECTION' => 'mysql', 'DB_HOST' => $server['DB_HOST'], 'DB_PORT' => $server['DB_PORT'] ?? '3306', 'DB_DATABASE' => $database, 'DB_USERNAME' => $server['DB_USERNAME'], 'DB_PASSWORD' => $server['DB_PASSWORD'],
        'CACHE_STORE' => 'database', 'CACHE_PREFIX' => 'happytea-staging:', 'SESSION_DRIVER' => 'database', 'SESSION_COOKIE' => 'happytea-staging_session', 'SESSION_DOMAIN' => 'null',
        'QUEUE_CONNECTION' => 'sync', 'DB_QUEUE' => 'happytea-staging', 'NOTIFICATION_QUEUE_CONNECTION' => 'database', 'NOTIFICATION_QUEUE' => 'happytea-staging',
        'MAVYN_CLIENT_ID' => 'happytea-staging', 'MAIL_MAILER' => 'array'];
    file_put_contents($directory.'/.env', implode("\n", array_map(fn ($key, $value) => $key.'='.json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), array_keys($env), $env))."\n");
    chmod($directory.'/.env', 0600);
    foreach (['APP_CONFIG_CACHE' => 'config.php', 'APP_ROUTES_CACHE' => 'routes.php', 'APP_EVENTS_CACHE' => 'events.php', 'APP_SERVICES_CACHE' => 'services.php', 'APP_PACKAGES_CACHE' => 'packages.php'] as $key => $file) {
        putenv($key.'='.$directory.'/bootstrap/cache/'.$file);
        $_ENV[$key] = $_SERVER[$key] = $directory.'/bootstrap/cache/'.$file;
    }
    $app = require $root.'/bootstrap/app.php';
    $app->useEnvironmentPath($directory);
    $app->useStoragePath($directory.'/storage');
    $app->usePublicPath($directory.'/public');
    $app->make(Kernel::class)->bootstrap();
    if (DB::connection()->getDatabaseName() !== $database) {
        throw new RuntimeException('Refusing to operate outside the disposable database.');
    }
    Artisan::call('migrate', ['--force' => true]);
    foreach ($before as $table => $data) {
        // Added legacy columns may appear after migration; compare only original fields.
        $rows = $replica->query('SELECT `'.implode('`,`', $data['fields']).'` FROM `'.$table.'` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== $data['count'] || hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)) !== $data['hash']) {
            throw new RuntimeException('Migration changed protected historical data: '.$table);
        }
    }
    $messages = [];
    $report = static function ($message, $warning) use (&$messages) {
        if ($warning) {
            $messages[] = $message;
        }
    };
    $media = app(LegacyMediaMigrationService::class);
    $dryRun = $media->run(false, $report);
    $apply = $media->run(true, $report);
    $repeat = $media->run(true, $report);
    foreach ($before as $table => $data) {
        $rows = $replica->query('SELECT `'.implode('`,`', $data['fields']).'` FROM `'.$table.'` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== $data['count'] || hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)) !== $data['hash']) {
            throw new RuntimeException('Media backfill changed protected historical data: '.$table);
        }
    }
    Artisan::call('platform:themes:assets');
    $password = Str::password(32);
    $admin = Admin::create(['name' => 'Staging verifier', 'email' => 'staging-verifier@example.test', 'password' => $password, 'role' => 'super_admin', 'is_active' => true]);
    $state = ['directory' => $directory, 'database' => $database, 'admin_email' => $admin->email, 'admin_password' => $password, 'counts' => array_map(fn ($data) => $data['count'], $before)];
    file_put_contents($directory.'/state.json', json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    chmod($directory.'/state.json', 0600);
    file_put_contents($directory.'/media-warnings.json', json_encode(array_unique($messages), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    chmod($directory.'/media-warnings.json', 0600);
    $staticRouter = <<<'PHP'
$requested = realpath(__DIR__.'/public'.parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$publicRoot = realpath(__DIR__.'/public');
$storageRoot = realpath(__DIR__.'/storage/app/public');
if ($requested && is_file($requested) && !str_ends_with($requested, '.php') && (str_starts_with($requested, $publicRoot.'/') || str_starts_with($requested, $storageRoot.'/'))) {
    return false;
}
PHP;
    $launcher = '<?php'."\n".$staticRouter."\n".'require '.var_export($root.'/vendor/autoload.php', true).';'."\n";
    foreach (['APP_CONFIG_CACHE' => 'config.php', 'APP_ROUTES_CACHE' => 'routes.php', 'APP_EVENTS_CACHE' => 'events.php', 'APP_SERVICES_CACHE' => 'services.php', 'APP_PACKAGES_CACHE' => 'packages.php'] as $key => $file) {
        $launcher .= 'putenv('.var_export($key.'='.$directory.'/bootstrap/cache/'.$file, true).');'."\n";
    }
    $launcher .= '$app = require '.var_export($root.'/bootstrap/app.php', true).';'."\n".'$app->useEnvironmentPath(__DIR__); $app->useStoragePath(__DIR__."/storage"); $app->usePublicPath(__DIR__."/public");'."\n".'$app->handleRequest(Illuminate\Http\Request::capture());'."\n";
    file_put_contents($directory.'/router.php', $launcher);
    $result = ['result' => $apply['errors'] ? 'requires-source-reconciliation' : 'passed', 'source_unchanged' => true, 'restored_mysql_backup_verified' => true,
        'historical_counts_preserved' => $state['counts'], 'media_report' => $dryRun, 'media_apply' => $apply, 'media_repeat' => $repeat,
        'private_receipts' => Order::whereNotNull('payment_receipt')->where('payment_receipt_disk', 'local')->count(), 'state' => $keep ? $directory.'/state.json' : null];
    file_put_contents($directory.'/result.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $exception) {
    file_put_contents($directory.'/failure.txt', (string) $exception);
    chmod($directory.'/failure.txt', 0600);
    echo json_encode(['result' => 'failed', 'exception' => get_class($exception), 'private_diagnostics' => $keep ? $directory.'/failure.txt' : null])."\n";
    $exitCode = 1;
} finally {
    if (! $keep) {
        if ($created) {
            $adminPdo->exec('DROP DATABASE `'.$database.'`');
        }
        $fs->deleteDirectory($directory);
    }
}

exit($exitCode);
