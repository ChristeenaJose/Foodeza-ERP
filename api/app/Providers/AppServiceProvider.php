<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Find new module controllers/models/services without a fresh composer classmap.
        require_once __DIR__.'/../Support/ModuleAutoload.php';
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->ensureSharedDatabase();
    }

    private function ensureSharedDatabase(): void
    {
        $dir = env('FOODEZA_DATA_DIR');
        if (! $dir) {
            return;
        }

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $target = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'database.sqlite';
        $default = database_path('database.sqlite');

        if (! file_exists($target) && file_exists($default)) {
            copy($default, $target);
        }
    }
}
