<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The first google-form migration may already have run in its older shape
 * (orders only). This follow-up creates any missing event/update tables and
 * upgrades the orders table for the event-based flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('google_form_order_events')) {
            Schema::create('google_form_order_events', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('location');
                $table->date('event_date');
                $table->string('start_time', 5);
                $table->string('end_time', 5);
                $table->text('confirmation_template');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('google_form_order_updates')) {
            Schema::create('google_form_order_updates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained('google_form_order_events')->cascadeOnDelete();
                $table->string('title')->nullable();
                $table->text('message');
                $table->timestamp('last_sent_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('google_form_orders')) {
            $this->createOrdersTable();

            return;
        }

        if (! Schema::hasColumn('google_form_orders', 'event_id')) {
            Schema::drop('google_form_orders');
            $this->createOrdersTable();
        }
    }

    public function down(): void
    {
        // Keep data; this migration only ensures schema for upgrades.
    }

    private function createOrdersTable(): void
    {
        Schema::create('google_form_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('google_form_order_events')->cascadeOnDelete();
            $table->string('reference');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->date('submitted_on');
            $table->date('event_date');
            $table->string('service_type')->nullable();
            $table->text('items');
            $table->unsignedInteger('guest_count')->nullable();
            $table->string('location')->nullable();
            $table->string('status')->default('new');
            $table->string('form_response_id');
            $table->json('raw_payload')->nullable();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'form_response_id']);
            $table->unique('reference');
        });
    }
};
