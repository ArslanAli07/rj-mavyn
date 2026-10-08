<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Mavyn\Core\Plugins\PluginCatalog;

class MakePluginCommand extends Command
{
    protected $signature = 'platform:plugin:make {plugin}';

    protected $description = 'Scaffold a disabled Composer plugin; installation remains a build operation';

    public function handle(): int
    {
        $id = $this->argument('plugin');
        if (! PluginCatalog::validId($id)) {
            $this->error('Use a namespaced plugin ID such as acme.example.');

            return self::FAILURE;
        }
        $directory = base_path('plugins/'.$id);
        if (file_exists($directory)) {
            $this->error('Plugin directory already exists.');

            return self::FAILURE;
        }
        $parts = explode('.', $id);
        $vendor = array_shift($parts);
        $slug = implode('-', $parts);
        $namespace = implode('\\', array_map(fn ($part) => Str::studly($part), [$vendor, ...$parts]));
        $manifest = ['name' => $vendor.'/'.$slug, 'description' => 'Plugin '.$id, 'type' => 'library', 'license' => 'MIT', 'require' => ['php' => '^8.3', 'mavyn/core' => '^0.1', 'mavyn/contracts' => '^0.1'], 'autoload' => ['psr-4' => [$namespace.'\\' => 'src/']], 'extra' => ['mavyn' => ['kind' => 'plugin', 'id' => $id, 'name' => Str::headline($slug), 'provider' => $namespace.'\\Plugin', 'requires_active' => (object) [], 'conflicts_with' => [], 'capabilities' => [], 'permissions' => [$id.'.manage' => 'Manage '.Str::headline($slug)], 'settings' => (object) [], 'routes' => (object) [], 'migrations' => []]]];
        File::makeDirectory($directory.'/src', 0755, true);
        File::put($directory.'/composer.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        File::put($directory.'/src/Plugin.php', "<?php\n\nnamespace ".$namespace.";\n\nuse Mavyn\\Contracts\\Plugins\\Plugin as PluginContract;\nuse Mavyn\\Contracts\\Plugins\\PluginContext;\n\nfinal class Plugin implements PluginContract\n{\n    public function register(PluginContext \$context): void\n    {\n        // Declare contributions; defer business operations until invoked.\n    }\n}\n");
        File::put($directory.'/README.md', '# '.$id."\n\nAdd a root Composer path repository for plugins/* and require this package at its development branch/version. Run platform:extensions:build, deploy migrations, validate, then activate. Scaffolding does not install or activate code. See docs/PLUGIN_RUNTIME.md in the host for supported contracts.\n");
        $this->info('Plugin scaffold created at plugins/'.$id.'; not installed or active.');

        return self::SUCCESS;
    }
}
