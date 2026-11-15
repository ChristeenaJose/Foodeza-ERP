<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_form_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->date('submitted_on');
            $table->date('event_date');
            $table->string('service_type');
            $table->text('items');
            $table->unsignedInteger('guest_count')->nullable();
            $table->string('location')->nullable();
            $table->string('status')->default('new');
            $table->string('form_response_id')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_form_orders');
    }
};
