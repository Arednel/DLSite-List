<?php

use App\Models\Genre;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('genre_product', 'source')) {
            return;
        }

        Schema::table('genre_product', function (Blueprint $table) {
            $table->string('source', 32)
                ->default(Genre::PIVOT_SOURCE_FETCHED)
                ->after('genre_id');
        });

        DB::table('genres')
            ->where('type', Genre::TYPE_CUSTOM)
            ->pluck('id')
            ->chunk(500)
            ->each(function ($customGenreIds): void {
                DB::table('genre_product')
                    ->whereIn('genre_id', $customGenreIds)
                    ->update(['source' => Genre::PIVOT_SOURCE_CUSTOM]);
            });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('genre_product', 'source')) {
            return;
        }

        Schema::table('genre_product', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
