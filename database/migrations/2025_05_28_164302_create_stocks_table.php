<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('symbol')->unique();
            $table->string('name')->nullable();
            $table->decimal('price', 12, 4)->nullable();
            $table->decimal('float', 15, 2)->nullable();
            $table->bigInteger('volume')->nullable();
            $table->decimal('relative_volume', 10, 2)->nullable();
            $table->decimal('gap_percent', 6, 2)->nullable();
            $table->decimal('change_percent', 6, 2)->nullable();
            $table->decimal('short_interest', 6, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
