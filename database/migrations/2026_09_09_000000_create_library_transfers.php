<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_transfer_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('direction', 10);
            $table->string('status')->index();
            $table->unsignedBigInteger('generation')->default(1);
            $table->unsignedBigInteger('job_token')->default(0);
            $table->text('stage')->nullable();
            $table->uuid('archive_set_id')->nullable();
            $table->json('settings');
            $table->json('warnings')->nullable();
            $table->unsignedBigInteger('processed')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
        });
        Schema::create('library_transfer_parts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('library_transfer_run_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);
            $table->unsignedInteger('number');
            $table->string('status')->default('pending');
            $table->string('path')->nullable();
            $table->string('upload_path')->nullable();
            $table->string('filename');
            $table->unsignedBigInteger('bytes')->default(0);
            $table->unsignedInteger('entry_count')->default(0);
            $table->string('sha256', 64)->nullable();
            $table->json('manifest');
            $table->json('candidate')->nullable();
            $table->unsignedBigInteger('validation_token')->default(0);
            $table->text('error')->nullable();
            $table->unique(['library_transfer_run_id', 'kind', 'number'], 'transfer_part_unique');
            $table->timestamps();
        });
        Schema::create('library_transfer_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('library_transfer_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('library_transfer_part_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('generation');
            $table->string('kind', 10);
            $table->string('section', 30);
            $table->string('logical_key')->nullable();
            $table->unsignedInteger('fragment_number')->nullable();
            $table->unsignedInteger('fragment_count')->nullable();
            $table->unsignedBigInteger('position');
            $table->string('path', 240);
            $table->string('source_disk', 20)->default('local');
            $table->text('source_path');
            $table->unsignedBigInteger('bytes');
            $table->string('sha256', 64);
            $table->string('media_type', 100);
            $table->unique(['library_transfer_run_id', 'generation', 'path'], 'transfer_entry_path_unique');
            $table->index(['library_transfer_run_id', 'generation', 'kind', 'position'], 'transfer_entry_order_index');
            $table->index(['library_transfer_part_id', 'position'], 'transfer_part_entry_index');
            $table->index(['library_transfer_run_id', 'kind', 'logical_key'], 'transfer_entry_logical_index');
            $table->index(['library_transfer_run_id', 'kind', 'sha256'], 'transfer_entry_hash_index');
            $table->timestamps();
        });
        Schema::create('library_import_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('library_transfer_run_id')->constrained()->cascadeOnDelete();
            $table->string('section', 30);
            $table->string('category', 30);
            $table->text('entity_key');
            $table->string('identity_hash', 64);
            $table->string('record_identity', 64)->nullable();
            $table->json('baseline')->nullable();
            $table->json('incoming')->nullable();
            $table->json('baseline_preview')->nullable();
            $table->json('incoming_preview')->nullable();
            $table->json('metadata')->nullable();
            $table->json('result')->nullable();
            $table->boolean('collection')->default(false);
            $table->boolean('decision_override')->default(false);
            $table->string('decision', 12)->default('ignore');
            $table->string('status', 20)->default('pending');
            $table->text('error')->nullable();
            $table->unique(['library_transfer_run_id', 'identity_hash'], 'transfer_item_unique');
            $table->index(['library_transfer_run_id', 'section', 'category', 'status'], 'transfer_review_index');
            $table->index(['library_transfer_run_id', 'record_identity'], 'transfer_record_index');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_import_items');
        Schema::dropIfExists('library_transfer_entries');
        Schema::dropIfExists('library_transfer_parts');
        Schema::dropIfExists('library_transfer_runs');
    }
};
