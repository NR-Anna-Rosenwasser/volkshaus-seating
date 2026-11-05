<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->enum('actor', ['buyer', 'admin', 'system']);
            $table->string('action');
            $table->string('seat_id')->nullable()->index();
            $table->foreign('seat_id')->references('id')->on('seats');
            $table->foreignId('attendee_id')->nullable()->index()->constrained();
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
