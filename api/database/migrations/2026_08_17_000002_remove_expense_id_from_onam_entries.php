<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('onam_entries', 'expense_id')) {
            DB::table('onam_entries')->whereNotNull('expense_id')->delete();

            Schema::table('onam_entries', function (Blueprint $table) {
                $table->dropUnique(['expense_id']);
                $table->dropConstrainedForeignId('expense_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('onam_entries', function (Blueprint $table) {
            $table->foreignId('expense_id')->nullable()->after('id')->constrained('expenses')->nullOnDelete();
            $table->unique('expense_id');
        });
    }
};
