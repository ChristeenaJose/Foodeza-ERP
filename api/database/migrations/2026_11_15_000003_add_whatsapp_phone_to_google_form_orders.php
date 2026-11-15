<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('google_form_orders')) {
            return;
        }

        if (! Schema::hasColumn('google_form_orders', 'whatsapp_phone')) {
            Schema::table('google_form_orders', function (Blueprint $table) {
                $table->string('whatsapp_phone', 50)->nullable()->after('location');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('google_form_orders') && Schema::hasColumn('google_form_orders', 'whatsapp_phone')) {
            Schema::table('google_form_orders', function (Blueprint $table) {
                $table->dropColumn('whatsapp_phone');
            });
        }
    }
};
