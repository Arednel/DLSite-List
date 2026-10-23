<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            // DLsite's average star rating (0-5) and rating count; null until fetched or when unrated.
            $table->decimal('dlsite_rating', 3, 2)->nullable()->after('brand');
            $table->unsignedInteger('dlsite_rating_count')->nullable()->after('dlsite_rating');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['dlsite_rating', 'dlsite_rating_count']);
        });
    }
};
