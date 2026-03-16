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
        Schema::create('genres', function (Blueprint $table) {
            $table->id();
            $table->integer('group_id')->nullable()->default(null);
            $table->string('title', 255)->unique();
            $table->string('description', 1000)->nullable()->default(null);
            $table->integer('order')->nullable()->default(null);
            $table->string('type', 255)->nullable()->default(null);
            $table->string('language', 255);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('genres');
    }
};
