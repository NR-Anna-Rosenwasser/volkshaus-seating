<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Seats and orders now belong to an event, and a seat code like P-1-1 is
     * only unique within one. No event existed before this, so this runs on an
     * empty database and refuses anything else rather than guess an owner.
     */
    public function up(): void
    {
        if (DB::table('orders')->exists() || DB::table('audit_log')->exists()) {
            throw new RuntimeException('This migration needs a database without orders. Export what you need and run migrate:fresh.');
        }

        Schema::table('audit_log', function (Blueprint $table) {
            $table->dropForeign(['seat_id']);
            $table->dropColumn('seat_id');
        });

        Schema::drop('seats');

        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained();
            $table->string('code');
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

            $table->unique(['event_id', 'code']);
            $table->index(['event_id', 'section', 'row', 'segment']);
        });

        DB::statement('ALTER TABLE seats ADD CONSTRAINT seats_blocked_or_assigned_check CHECK (NOT (block_reason IS NOT NULL AND attendee_id IS NOT NULL))');

        Schema::table('audit_log', function (Blueprint $table) {
            $table->foreignId('seat_id')->nullable()->index()->constrained();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained();
            $table->dropUnique(['hievents_order_id']);
            $table->unique(['event_id', 'hievents_order_id']);
        });

        Schema::table('attendees', function (Blueprint $table) {
            $table->dropUnique(['hievents_attendee_id']);
            $table->unique(['order_id', 'hievents_attendee_id']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Not reversible: run migrate:fresh.');
    }
};
