<?php

/**
 * Load App\* classes that live under /modules even when Composer classmap is stale.
 * Run after pulling new module files without requiring `composer dump-autoload`.
 */
spl_autoload_register(static function (string $class): void {
    if (! str_starts_with($class, 'App\\')) {
        return;
    }

    $modulesRoot = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'modules';
    if (! is_dir($modulesRoot)) {
        return;
    }

    $relative = substr($class, strlen('App\\'));
    $parts = explode('\\', $relative);
    $paths = [];

    // App\Http\Controllers\Api\Foo -> modules/*/Http/Foo.php
    if (($parts[0] ?? null) === 'Http'
        && ($parts[1] ?? null) === 'Controllers'
        && ($parts[2] ?? null) === 'Api'
        && isset($parts[3])
        && count($parts) === 4
    ) {
        $paths[] = 'Http'.DIRECTORY_SEPARATOR.$parts[3].'.php';
    }

    // App\Models\Foo -> modules/*/Models/Foo.php
    if (($parts[0] ?? null) === 'Models' && count($parts) === 2) {
        $paths[] = 'Models'.DIRECTORY_SEPARATOR.$parts[1].'.php';
    }

    // App\Services\Foo -> modules/*/Services/Foo.php
    if (($parts[0] ?? null) === 'Services' && count($parts) === 2) {
        $paths[] = 'Services'.DIRECTORY_SEPARATOR.$parts[1].'.php';
    }

    // App\Support\Foo -> modules/*/Support/Foo.php (if used later)
    if (($parts[0] ?? null) === 'Support' && count($parts) === 2) {
        $paths[] = 'Support'.DIRECTORY_SEPARATOR.$parts[1].'.php';
    }

    foreach ($paths as $relativePath) {
        foreach (glob($modulesRoot.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.$relativePath) ?: [] as $file) {
            if (is_file($file)) {
                require_once $file;

                return;
            }
        }
    }
});
