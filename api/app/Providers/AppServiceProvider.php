<?php

namespace App\Providers;

use App\Support\EnsureGoogleFormOrderTables;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    private static bool $ranDesktopMigrations = false;

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
        $this->ensureDesktopSchema();
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

        if (! file_exists($target)) {
            touch($target);
            if (file_exists($default) && filesize($default) > 0) {
                copy($default, $target);
            }
        }
    }

    /**
     * Desktop API uses FOODEZA_DATA_DIR sqlite. Keep its schema current so new
     * module tables appear without a manual migrate against the wrong file.
     */
    private function ensureDesktopSchema(): void
    {
        if (self::$ranDesktopMigrations) {
            return;
        }

        self::$ranDesktopMigrations = true;

        // Only for the desktop data directory DB — never during phpunit :memory: tests.
        if (! env('FOODEZA_DATA_DIR')) {
            return;
        }

        try {
            // Never Artisan::call() while booting another artisan command — it breaks the console.
            if (! $this->app->runningInConsole()) {
                Artisan::call('migrate', ['--force' => true]);
            }
            // Direct create if migrate was skipped (older migration already recorded).
            EnsureGoogleFormOrderTables::run();
        } catch (\Throwable) {
            // Ignore during early boot without a writable DB.
        }
    }
}
