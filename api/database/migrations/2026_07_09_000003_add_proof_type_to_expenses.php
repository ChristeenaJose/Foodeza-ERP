<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('proof_type', 32)->default('none')->after('receipt_attachment_id');
        });

        if (Schema::hasColumn('expenses', 'receipt_on_hand')) {
            DB::table('expenses')->where('receipt_on_hand', true)->update(['proof_type' => 'paper_pdf']);
            DB::table('expenses')->whereNotNull('receipt_attachment_id')->update(['proof_type' => 'uploaded']);

            Schema::table('expenses', function (Blueprint $table) {
                $table->dropColumn('receipt_on_hand');
            });
        } else {
            DB::table('expenses')->whereNotNull('receipt_attachment_id')->update(['proof_type' => 'uploaded']);
        }
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->boolean('receipt_on_hand')->default(false)->after('receipt_attachment_id');
        });

        DB::table('expenses')->where('proof_type', 'paper_pdf')->update(['receipt_on_hand' => true]);

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('proof_type');
        });
    }
};
