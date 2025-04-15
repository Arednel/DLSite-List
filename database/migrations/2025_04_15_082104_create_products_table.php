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
            $table->id();
            $table->string('dlsite_product_id', 200);
            $table->string('maker_id', 200);
            $table->string('work_name', 1000);
            $table->string('work_name_english', 1000);
            $table->string('age_category', 200);
            $table->string('circle', 200);
            $table->string('work_image', 200);
            $table->json('genre');
            $table->json('genre_english');
            $table->string('description', 1000);
            $table->string('description_english', 1000);
            $table->string('sample_images', 1000);
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
