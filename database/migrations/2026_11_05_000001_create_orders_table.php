<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // Hi.Events identifiers are strings until docs/hievents-api.md confirms their exact types.
            $table->string('hievents_order_id')->unique();
            $table->string('hievents_public_id')->nullable();
            $table->string('short_id')->nullable()->index();
            // Personal data is nullable so that seating:purge can anonymize it.
            $table->string('email')->nullable()->index();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('locale')->nullable();
            $table->enum('status', ['active', 'cancelled'])->default('active');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('seats_confirmed_at')->nullable();
            $table->unsignedInteger('reminders_sent')->default(0);
            $table->timestamp('last_reminder_at')->nullable();
            // The Hi.Events order date, used for fairness ordering.
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
