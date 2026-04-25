<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PRODUCTS_TABLE = 'products';
    private const GENRES_TABLE = 'genres';
    private const GENRE_PRODUCT_TABLE = 'genre_product';
    private const TYPE_AUTO_GENERATED_JAPANESE = 'auto_generated_japanese';
    private const TYPE_AUTO_GENERATED_ENGLISH = 'auto_generated_english';
    private const TYPE_CUSTOM = 'custom';
    private const LANGUAGE_JAPANESE = 'jp';
    private const LANGUAGE_ENGLISH = 'en';
    private const SOURCE_FETCHED = 'fetched';
    private const SOURCE_CUSTOM = 'custom';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (
            !Schema::hasTable(self::PRODUCTS_TABLE)
            || !Schema::hasTable(self::GENRES_TABLE)
            || !Schema::hasTable(self::GENRE_PRODUCT_TABLE)
            || !$this->hasLegacyGenreColumns()
        ) {
            return;
        }

        $hasPivotSource = Schema::hasColumn(self::GENRE_PRODUCT_TABLE, 'source');

        DB::table(self::PRODUCTS_TABLE)
            ->select(['id', 'genre', 'genre_english', 'genre_custom'])
            ->orderBy('id')
            ->lazy()
            ->each(function (object $product) use ($hasPivotSource): void {
                $fetchedGenreIds = array_merge(
                    $this->resolveGenreIds(
                        $this->decodeJsonArray($product->genre),
                        self::TYPE_AUTO_GENERATED_JAPANESE,
                        self::LANGUAGE_JAPANESE
                    ),
                    $this->resolveGenreIds(
                        $this->decodeJsonArray($product->genre_english),
                        self::TYPE_AUTO_GENERATED_ENGLISH,
                        self::LANGUAGE_ENGLISH
                    ),
                );
                $customGenreIds = $this->resolveGenreIds(
                    $this->decodeJsonArray($product->genre_custom),
                    self::TYPE_CUSTOM,
                    self::LANGUAGE_ENGLISH
                );

                foreach ($this->genreSyncPayload($fetchedGenreIds, $customGenreIds) as $genreId => $source) {
                    $row = [
                        'product_id' => $product->id,
                        'genre_id' => $genreId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if ($hasPivotSource) {
                        $row['source'] = $source;
                    }

                    DB::table(self::GENRE_PRODUCT_TABLE)->insertOrIgnore($row);
                }
            });

        Schema::table(self::PRODUCTS_TABLE, function (Blueprint $table) {
            $table->dropColumn(['genre', 'genre_english', 'genre_custom']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable(self::PRODUCTS_TABLE) || !Schema::hasTable(self::GENRES_TABLE) || !Schema::hasTable(self::GENRE_PRODUCT_TABLE)) {
            return;
        }

        if (!$this->hasLegacyGenreColumns()) {
            Schema::table(self::PRODUCTS_TABLE, function (Blueprint $table) {
                $table->json('genre')->nullable()->after('work_image');
                $table->json('genre_english')->nullable()->after('genre');
                $table->json('genre_custom')->nullable()->after('genre_english');
            });
        }

        $hasPivotSource = Schema::hasColumn(self::GENRE_PRODUCT_TABLE, 'source');

        DB::table(self::PRODUCTS_TABLE)
            ->select(['id'])
            ->orderBy('id')
            ->lazy()
            ->each(function (object $product) use ($hasPivotSource): void {
                $genreSelect = [
                    self::GENRES_TABLE . '.title',
                    self::GENRES_TABLE . '.type',
                ];

                if ($hasPivotSource) {
                    $genreSelect[] = self::GENRE_PRODUCT_TABLE . '.source';
                }

                $genres = DB::table(self::GENRE_PRODUCT_TABLE)
                    ->join(self::GENRES_TABLE, self::GENRES_TABLE . '.id', '=', self::GENRE_PRODUCT_TABLE . '.genre_id')
                    ->where(self::GENRE_PRODUCT_TABLE . '.product_id', $product->id)
                    ->select($genreSelect)
                    ->orderBy(self::GENRES_TABLE . '.id')
                    ->get();

                $japaneseGenres = $genres->where('type', self::TYPE_AUTO_GENERATED_JAPANESE);
                $englishGenres = $genres->where('type', self::TYPE_AUTO_GENERATED_ENGLISH);
                $customGenres = $genres->where('type', self::TYPE_CUSTOM);

                if ($hasPivotSource) {
                    $japaneseGenres = $japaneseGenres->where('source', '!=', self::SOURCE_CUSTOM);
                    $englishGenres = $englishGenres->where('source', '!=', self::SOURCE_CUSTOM);
                    $customGenres = $genres->where('source', self::SOURCE_CUSTOM);
                }

                DB::table(self::PRODUCTS_TABLE)
                    ->where('id', $product->id)
                    ->update([
                        'genre' => json_encode(
                            $japaneseGenres
                                ->pluck('title')
                                ->values()
                                ->all()
                        ),
                        'genre_english' => json_encode(
                            $englishGenres
                                ->pluck('title')
                                ->values()
                                ->all()
                        ),
                        'genre_custom' => json_encode(
                            $customGenres
                                ->pluck('title')
                                ->values()
                                ->all()
                        ),
                    ]);
            });

    }

    private function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function resolveGenreIds(array $values, string $preferredType, string $language): array
    {
        return collect($values)
            ->map(fn (mixed $value) => $this->normalizeValue($value))
            ->filter(fn (?string $value) => $value !== null)
            ->map(fn (string $value): int => $this->resolveGenreId($value, $preferredType, $language))
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function genreSyncPayload(array $fetchedGenreIds, array $customGenreIds): array
    {
        $payload = [];

        foreach (array_unique($fetchedGenreIds) as $genreId) {
            $payload[$genreId] = self::SOURCE_FETCHED;
        }

        foreach (array_unique($customGenreIds) as $genreId) {
            $payload[$genreId] ??= self::SOURCE_CUSTOM;
        }

        return $payload;
    }

    private function resolveGenreId(string $title, string $preferredType, string $language): int
    {
        $genre = DB::table(self::GENRES_TABLE)
            ->where('title', $title)
            ->first();

        if ($genre !== null) {
            if ($this->shouldPromoteType($genre->type, $preferredType)) {
                DB::table(self::GENRES_TABLE)
                    ->where('id', $genre->id)
                    ->update([
                        'type' => $preferredType,
                        'language' => $language,
                        'updated_at' => now(),
                    ]);
            }

            return (int) $genre->id;
        }

        $now = now();

        return (int) DB::table(self::GENRES_TABLE)->insertGetId([
            'group_id' => null,
            'title' => $title,
            'description' => null,
            'order' => null,
            // Auto-generated titles win over custom titles when the same text is reused.
            'type' => $preferredType,
            'language' => $language,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function shouldPromoteType(?string $currentType, string $preferredType): bool
    {
        $isAutoGeneratedType = in_array($preferredType, [
            self::TYPE_AUTO_GENERATED_JAPANESE,
            self::TYPE_AUTO_GENERATED_ENGLISH,
        ], true);

        return $isAutoGeneratedType && $currentType === self::TYPE_CUSTOM;
    }

    private function hasLegacyGenreColumns(): bool
    {
        return Schema::hasColumn(self::PRODUCTS_TABLE, 'genre')
            && Schema::hasColumn(self::PRODUCTS_TABLE, 'genre_english')
            && Schema::hasColumn(self::PRODUCTS_TABLE, 'genre_custom');
    }
};
