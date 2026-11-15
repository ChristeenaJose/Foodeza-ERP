<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->decimal('amount', 12, 2); // gross (paid) amount
            $table->string('supplier_name')->nullable();
            $table->string('section')->default('insurance'); // insurance|rent|other
            $table->unsignedTinyInteger('day_of_month')->default(1);
            $table->string('proof_type', 32)->default('bank_statement');
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_expenses');
    }
};
