<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('genre_relations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_genre_id')->constrained('genres')->cascadeOnDelete();
            $table->foreignId('child_genre_id')->constrained('genres')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['parent_genre_id', 'child_genre_id']);
            $table->index(['child_genre_id', 'parent_genre_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('genre_relations');
    }
};
