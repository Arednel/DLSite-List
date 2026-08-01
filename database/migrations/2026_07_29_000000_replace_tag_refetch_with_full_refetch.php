<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('tag_refetch_work_results');
        Schema::dropIfExists('tag_refetch_runs');

        Schema::create('refetch_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('batch_id')->nullable()->index();
            $table->string('status', 32)->default('running')->index();
            $table->boolean('check_images')->default(false);
            $table->json('resolved_tabs')->nullable();
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('fetched_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('refetch_work_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('refetch_run_id')->constrained()->cascadeOnDelete();
            $table->string('product_id')->index();
            $table->string('status', 32)->default('pending')->index();
            $table->json('changes')->nullable();
            $table->json('decisions')->nullable();
            $table->json('warnings')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['refetch_run_id', 'product_id'], 'refetch_result_run_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refetch_work_results');
        Schema::dropIfExists('refetch_runs');

        Schema::create('tag_refetch_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('batch_id')->nullable()->index();
            $table->string('status', 32)->default('running')->index();
            $table->json('selected_product_ids');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('fetched_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tag_refetch_work_results', function (Blueprint $table): void {
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
            $table->json('custom_to_fetched_japanese_tags')->nullable();
            $table->json('custom_to_fetched_english_tags')->nullable();
            $table->text('error')->nullable();
            $table->string('added_japanese_action', 32)->default('add_as_fetched');
            $table->string('added_english_action', 32)->default('add_as_fetched');
            $table->string('stale_japanese_action', 32)->default('move_to_custom');
            $table->string('stale_english_action', 32)->default('move_to_custom');
            $table->string('custom_to_fetched_action', 32)->default('promote_to_fetched');
            $table->timestamps();

            $table->unique(['tag_refetch_run_id', 'product_id'], 'tag_refetch_result_run_product_unique');
        });
    }
};
