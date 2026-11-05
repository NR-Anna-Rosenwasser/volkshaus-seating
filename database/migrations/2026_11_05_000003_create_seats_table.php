<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seats', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('section');
            $table->unsignedSmallInteger('row');
            $table->unsignedSmallInteger('number');
            $table->unsignedSmallInteger('segment');
            $table->double('x');
            $table->double('y');
            $table->jsonb('flags')->default('[]');
            $table->enum('block_reason', ['vip', 'security', 'press', 'tech', 'wheelchair', 'staff', 'other'])->nullable();
            $table->string('block_note')->nullable();
            // One seat per attendee and one attendee per seat, enforced by the database.
            $table->foreignId('attendee_id')->nullable()->unique()->constrained();
            $table->timestamp('assigned_at')->nullable();
            $table->enum('assigned_by', ['buyer', 'auto', 'admin'])->nullable();

            $table->index(['section', 'row', 'segment']);
        });

        DB::statement('ALTER TABLE seats ADD CONSTRAINT seats_blocked_or_assigned_check CHECK (NOT (block_reason IS NOT NULL AND attendee_id IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};
