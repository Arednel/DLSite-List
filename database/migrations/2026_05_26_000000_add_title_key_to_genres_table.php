<?php

use App\Models\Genre;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TITLE_UNIQUE_INDEX = 'genres_title_unique';

    private const TITLE_KEY_UNIQUE_INDEX = 'genres_title_key_unique';

    public function up(): void
    {
        if (! Schema::hasColumn('genres', 'title_key')) {
            $this->assertNoDuplicateTitleKeys();

            Schema::table('genres', function (Blueprint $table): void {
                $table->string('title_key', 255)
                    ->nullable()
                    ->collation('utf8mb4_bin')
                    ->after('title');
            });

            $this->backfillTitleKeys();

            DB::statement(
                'ALTER TABLE genres MODIFY title_key VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL'
            );
        }

        if (! $this->indexExists(self::TITLE_KEY_UNIQUE_INDEX)) {
            Schema::table('genres', function (Blueprint $table): void {
                $table->unique('title_key', self::TITLE_KEY_UNIQUE_INDEX);
            });
        }

        if ($this->indexExists(self::TITLE_UNIQUE_INDEX)) {
            Schema::table('genres', function (Blueprint $table): void {
                $table->dropUnique(self::TITLE_UNIQUE_INDEX);
            });
        }
    }

    public function down(): void
    {
        if (! $this->indexExists(self::TITLE_UNIQUE_INDEX)) {
            // This can fail if kana-distinct tags were added after this migration.
            Schema::table('genres', function (Blueprint $table): void {
                $table->unique('title', self::TITLE_UNIQUE_INDEX);
            });
        }

        if (Schema::hasColumn('genres', 'title_key')) {
            Schema::table('genres', function (Blueprint $table): void {
                if ($this->indexExists(self::TITLE_KEY_UNIQUE_INDEX)) {
                    $table->dropUnique(self::TITLE_KEY_UNIQUE_INDEX);
                }

                $table->dropColumn('title_key');
            });
        }
    }

    private function assertNoDuplicateTitleKeys(): void
    {
        $duplicates = DB::table('genres')
            ->orderBy('id')
            ->get(['id', 'title'])
            ->groupBy(fn(object $genre): string => Genre::titleKey($genre->title))
            ->filter(fn(Collection $genres): bool => $genres->count() > 1);

        if ($duplicates->isEmpty()) {
            return;
        }

        $summary = $duplicates
            ->map(fn(Collection $genres, string $titleKey): string => sprintf(
                '%s: %s',
                $titleKey,
                $genres
                    ->map(fn(object $genre): string => "{$genre->id}={$genre->title}")
                    ->implode(', ')
            ))
            ->implode('; ');

        throw new RuntimeException(
            "Cannot add genres.title_key because duplicate case-insensitive tag titles exist: {$summary}"
        );
    }

    private function backfillTitleKeys(): void
    {
        DB::table('genres')
            ->orderBy('id')
            ->select(['id', 'title'])
            ->chunkById(500, function ($genres): void {
                foreach ($genres as $genre) {
                    DB::table('genres')
                        ->where('id', $genre->id)
                        ->update(['title_key' => Genre::titleKey($genre->title)]);
                }
            });
    }

    private function indexExists(string $indexName): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::connection()->getDatabaseName())
            ->where('table_name', 'genres')
            ->where('index_name', $indexName)
            ->exists();
    }
};
