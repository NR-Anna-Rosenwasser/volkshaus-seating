<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            // Hi.Events identifiers are strings, as on orders.
            $table->string('hievents_event_id')->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('venue_name')->nullable();
            // A snapshot: changing a plan must never move seats of a running event.
            $table->jsonb('layout')->nullable();
            $table->jsonb('seated_product_ids')->default('[]');
            $table->jsonb('wheelchair_question_ids')->default('[]');
            $table->jsonb('wheelchair_notify_emails')->default('[]');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('doors_at')->nullable();
            $table->timestamp('selection_opens_at')->nullable();
            $table->timestamp('selection_deadline')->nullable();
            $table->jsonb('reminder_offsets_days')->default('[14, 7, 2]');
            $table->unsignedSmallInteger('final_mail_offset_days')->default(2);
            $table->string('contact_email')->nullable();
            $table->unsignedSmallInteger('purge_after_days')->default(30);
            $table->timestamp('purged_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
