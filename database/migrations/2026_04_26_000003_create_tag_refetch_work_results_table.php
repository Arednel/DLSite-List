<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tag_refetch_work_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_refetch_run_id')->constrained()->cascadeOnDelete();
            $table->string('product_id')->index();
            $table->string('status', 32)->default('pending')->index();
            $table->json('fetched_japanese_tags')->nullable();
            $table->json('fetched_english_tags')->nullable();
            $table->json('added_japanese_tags')->nullable();
            $table->json('added_english_tags')->nullable();
            $table->json('stale_japanese_tags')->nullable();
            $table->json('stale_english_tags')->nullable();
            $table->text('error')->nullable();
            $table->string('stale_japanese_action', 32)->default('move_to_custom');
            $table->string('stale_english_action', 32)->default('move_to_custom');
            $table->timestamps();

            $table->unique(['tag_refetch_run_id', 'product_id'], 'tag_refetch_result_run_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_refetch_work_results');
    }
};
