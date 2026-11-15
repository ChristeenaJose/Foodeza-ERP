<?php

namespace App\Console\Commands;

use App\Support\EnsureGoogleFormOrderTables;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class EnsureGoogleFormOrderTablesCommand extends Command
{
    protected $signature = 'foodeza:ensure-google-form-tables';

    protected $description = 'Create google form order event tables if they are missing';

    public function handle(): int
    {
        $this->info('Database: '.config('database.connections.sqlite.database'));
        EnsureGoogleFormOrderTables::run();

        foreach ([
            'google_form_order_events',
            'google_form_order_updates',
            'google_form_orders',
        ] as $table) {
            $this->line($table.': '.(Schema::hasTable($table) ? 'OK' : 'MISSING'));
        }

        return self::SUCCESS;
    }
}
