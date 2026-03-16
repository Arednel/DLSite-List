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

        DB::table(self::PRODUCTS_TABLE)
            ->select(['id', 'genre', 'genre_english', 'genre_custom'])
            ->orderBy('id')
            ->lazy()
            ->each(function (object $product): void {
                $genreIds = array_merge(
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
                    $this->resolveGenreIds(
                        $this->decodeJsonArray($product->genre_custom),
                        self::TYPE_CUSTOM,
                        self::LANGUAGE_ENGLISH
                    ),
                );

                foreach (array_values(array_unique($genreIds)) as $genreId) {
                    DB::table(self::GENRE_PRODUCT_TABLE)->insertOrIgnore([
                        'product_id' => $product->id,
                        'genre_id' => $genreId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
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

        DB::table(self::PRODUCTS_TABLE)
            ->select(['id'])
            ->orderBy('id')
            ->lazy()
            ->each(function (object $product): void {
                $genres = DB::table(self::GENRE_PRODUCT_TABLE)
                    ->join(self::GENRES_TABLE, self::GENRES_TABLE . '.id', '=', self::GENRE_PRODUCT_TABLE . '.genre_id')
                    ->where(self::GENRE_PRODUCT_TABLE . '.product_id', $product->id)
                    ->select([
                        self::GENRES_TABLE . '.title',
                        self::GENRES_TABLE . '.type',
                    ])
                    ->orderBy(self::GENRES_TABLE . '.id')
                    ->get();

                DB::table(self::PRODUCTS_TABLE)
                    ->where('id', $product->id)
                    ->update([
                        'genre' => json_encode(
                            $genres
                                ->where('type', self::TYPE_AUTO_GENERATED_JAPANESE)
                                ->pluck('title')
                                ->values()
                                ->all()
                        ),
                        'genre_english' => json_encode(
                            $genres
                                ->where('type', self::TYPE_AUTO_GENERATED_ENGLISH)
                                ->pluck('title')
                                ->values()
                                ->all()
                        ),
                        'genre_custom' => json_encode(
                            $genres
                                ->where('type', self::TYPE_CUSTOM)
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
