<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per counter key, e.g. "project:2026" or "invoice:2026".
        // Rows are locked with SELECT ... FOR UPDATE to keep numbers unique and sequential.
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
