<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create google-form order tables if missing, ignoring migration history.
 * Needed when an older google_form_orders migration already ran on the desktop DB.
 */
class EnsureGoogleFormOrderTables
{
    public static function run(): void
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
                $table->unsignedBigInteger('event_id');
                $table->string('title')->nullable();
                $table->text('message');
                $table->timestamp('last_sent_at')->nullable();
                $table->timestamps();
            });
        }

        $needsOrdersRebuild = ! Schema::hasTable('google_form_orders')
            || ! Schema::hasColumn('google_form_orders', 'event_id');

        if ($needsOrdersRebuild) {
            if (Schema::hasTable('google_form_orders')) {
                Schema::drop('google_form_orders');
            }

            Schema::create('google_form_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id');
                $table->string('reference');
                $table->unsignedBigInteger('customer_id')->nullable();
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
    }
}
