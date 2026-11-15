<?php

use App\Support\EnsureGoogleFormOrderTables;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('foodeza:ensure-google-form-tables', function () {
    $this->info('Database: '.config('database.connections.sqlite.database'));
    EnsureGoogleFormOrderTables::run();
    foreach ([
        'google_form_order_events',
        'google_form_order_updates',
        'google_form_orders',
    ] as $table) {
        $this->line($table.': '.(Schema::hasTable($table) ? 'OK' : 'MISSING'));
    }
})->purpose('Create google form order event tables if they are missing');
