<?php

// Runs before vendor dependencies are installed.
$root = dirname(__DIR__);

try {
    $read = static fn (string $name): array => json_decode(file_get_contents($root.'/'.$name), true, 512, JSON_THROW_ON_ERROR);
    $manifest = $read('composer.shared.json');
    if (is_file($root.'/composer.client.json')) {
        $client = $read('composer.client.json');
        foreach (array_keys($client) as $key) {
            if (! in_array($key, ['require', 'repositories'], true)) {
                throw new RuntimeException('Client configuration supports only require and repositories.');
            }
        }
        foreach ($client['require'] ?? [] as $name => $constraint) {
            if (array_key_exists($name, $manifest['require'])) {
                // Older clones listed the bundled default theme themselves.
                if ($name === 'mavyn/theme-default' && $constraint === $manifest['require'][$name]) {
                    continue;
                }
                throw new RuntimeException('Client configuration cannot override shared requirement: '.$name);
            }
            $manifest['require'][$name] = $constraint;
        }
        $manifest['repositories'] = array_merge($manifest['repositories'], $client['repositories'] ?? []);
    }

    require __DIR__.'/copy-default-theme.php';

    // Keep an existing clone lockfile: it includes that clone's package graph.
    if (! is_file($root.'/composer.lock') && ! copy($root.'/composer.shared.lock', $root.'/composer.lock')) {
        throw new RuntimeException('Could not initialize composer.lock.');
    }
    if (! is_file($root.'/composer.json') || $read('composer.json') !== $manifest) {
        if (file_put_contents($root.'/composer.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n") === false) {
            throw new RuntimeException('Could not write composer.json.');
        }
        echo "Prepared composer.json; run composer install with the existing lock, or composer update when changing dependencies.\n";
    } else {
        echo "composer.json is current; the clone's lockfile was preserved.\n";
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
}
