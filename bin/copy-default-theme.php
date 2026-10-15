<?php

// Runs without Composer dependencies, before path packages are installed.
$root = dirname(__DIR__);
$target = $root.'/themes/default';

try {
    // A clone owns its installed theme; never merge or overwrite local changes.
    if (file_exists($target) || is_link($target)) {
        if (! is_dir($target) || ! is_file($target.'/composer.json')) {
            throw new RuntimeException('themes/default exists but is not a theme package. Restore it or move it aside before setup.');
        }
    } else {
        $source = $root.'/resources/themes/default';
        if (! is_file($source.'/composer.json')) {
            throw new RuntimeException('The bundled default theme is missing.');
        }

        if (! is_dir($root.'/themes') && ! mkdir($root.'/themes', 0755, true)) {
            throw new RuntimeException('Could not create the themes directory.');
        }
        // Copy completely before exposing the package, so failures can be retried.
        $temporary = $root.'/themes/.default-'.bin2hex(random_bytes(8));
        if (! mkdir($temporary, 0755)) {
            throw new RuntimeException('Could not create the default theme staging directory.');
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($files as $file) {
            $destination = $temporary.substr($file->getPathname(), strlen($source));
            if ($file->isLink()) {
                throw new RuntimeException('The bundled default theme must not contain symbolic links.');
            }
            if ($file->isDir() ? ! mkdir($destination, 0755) : ! copy($file->getPathname(), $destination)) {
                throw new RuntimeException('Could not copy bundled theme resource: '.$file->getPathname());
            }
        }
        if (! rename($temporary, $target)) {
            throw new RuntimeException('Could not install the default theme directory.');
        }
        unset($temporary);
        echo "Copied the bundled default theme to themes/default.\n";
    }
} catch (Throwable $exception) {
    if (isset($temporary) && is_dir($temporary)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($temporary);
    }
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
}
