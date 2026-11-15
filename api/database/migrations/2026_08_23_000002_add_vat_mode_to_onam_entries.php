<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onam_entries', function (Blueprint $table) {
            $table->string('vat_mode', 16)->default('none')->after('vat_amount');
        });

        DB::table('onam_entries')->where('type', 'income')->update(['vat_mode' => 'including']);
    }

    public function down(): void
    {
        Schema::table('onam_entries', function (Blueprint $table) {
            $table->dropColumn('vat_mode');
        });
    }
};
