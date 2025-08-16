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
        Schema::create('products', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('maker_id', 200)->nullable();
            $table->string('work_name', 1000);
            $table->string('work_name_english', 1000)->nullable();
            $table->string('age_category', 200)->nullable();
            $table->string('circle', 200)->nullable();
            $table->string('work_image', 200)->nullable();
            $table->json('genre');
            $table->json('genre_english');
            $table->json('genre_custom');
            $table->string('description', 1000)->nullable();
            $table->string('description_english', 1000)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->json('sample_images', 1000)->nullable();
            $table->integer('score')->nullable()->default(null);
            $table->string('series', 1000)->nullable();
            $table->string('progress', 200)->nullable()->default('Plan to Listen');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
