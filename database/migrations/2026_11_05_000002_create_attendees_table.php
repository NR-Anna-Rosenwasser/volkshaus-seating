<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->index()->constrained();
            $table->string('hievents_attendee_id')->unique();
            $table->string('hievents_product_id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->enum('status', ['active', 'cancelled'])->default('active');
            $table->boolean('needs_seat')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendees');
    }
};
