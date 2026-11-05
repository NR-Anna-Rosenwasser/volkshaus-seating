<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendees', function (Blueprint $table) {
            $table->boolean('needs_wheelchair')->default(false);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('wheelchair_notified_at')->nullable();
        });

        $this->setMailTypes(['link', 'confirmation', 'reminder', 'auto_assigned', 'final', 'wheelchair_requested', 'wheelchair_cancelled']);
    }

    public function down(): void
    {
        DB::table('mail_log')->whereIn('type', ['wheelchair_requested', 'wheelchair_cancelled'])->delete();
        $this->setMailTypes(['link', 'confirmation', 'reminder', 'auto_assigned', 'final']);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('wheelchair_notified_at');
        });

        Schema::table('attendees', function (Blueprint $table) {
            $table->dropColumn('needs_wheelchair');
        });
    }

    /**
     * @param  list<string>  $types
     */
    private function setMailTypes(array $types): void
    {
        $list = implode(', ', array_map(fn (string $type): string => "'{$type}'", $types));

        DB::statement('ALTER TABLE mail_log DROP CONSTRAINT mail_log_type_check');
        DB::statement("ALTER TABLE mail_log ADD CONSTRAINT mail_log_type_check CHECK (type IN ({$list}))");
    }
};
