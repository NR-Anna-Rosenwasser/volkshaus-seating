<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_entries', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->jsonb('value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_entries');
    }
};
