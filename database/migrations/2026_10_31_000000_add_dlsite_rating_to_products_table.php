<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing rows are not backfilled because rating metadata was not fetched before the dlsite-async v1.0.0b4 dependency update.
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('dlsite_rating_5', 3, 2)->nullable()->after('score')->index();
            $table->decimal('dlsite_rating_10', 4, 2)->nullable()->after('dlsite_rating_5')->index();
            $table->unsignedInteger('dlsite_rating_count')->nullable()->after('dlsite_rating_10')->index();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['dlsite_rating_5']);
            $table->dropIndex(['dlsite_rating_10']);
            $table->dropIndex(['dlsite_rating_count']);
            $table->dropColumn(['dlsite_rating_5', 'dlsite_rating_10', 'dlsite_rating_count']);
        });
    }
};
