<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onam_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('share_percent', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('onam_entries', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('type'); // income | expense
            $table->foreignId('partner_id')->nullable()->constrained('onam_partners')->nullOnDelete();
            $table->string('paid_by')->nullable(); // free text fallback if no partner
            $table->decimal('amount', 12, 2); // amount paid / received (gross)
            $table->string('description')->nullable();
            $table->string('proof_type', 32)->default('none');
            $table->foreignId('receipt_attachment_id')->nullable()->constrained('attachments')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onam_entries');
        Schema::dropIfExists('onam_partners');
    }
};
