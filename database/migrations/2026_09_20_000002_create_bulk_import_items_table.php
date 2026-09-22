<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_import_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bulk_import_run_id')->constrained('bulk_import_runs')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('product_id', 191);
            $table->string('status', 20)->index();
            $table->text('warning')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['bulk_import_run_id', 'position']);
            $table->unique(['bulk_import_run_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_import_items');
    }
};
