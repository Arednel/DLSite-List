<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('genres', function (Blueprint $table): void {
            $table->boolean('hidden_on_index')->default(false)->after('order');
        });

        Schema::table('genre_groups', function (Blueprint $table): void {
            $table->boolean('hidden_on_index')->default(false)->after('order');
        });

        Schema::create('genre_group_genre', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('genre_group_id')->constrained('genre_groups')->cascadeOnDelete();
            $table->foreignId('genre_id')->constrained('genres')->cascadeOnDelete();
            $table->unsignedInteger('order')->default(1);
            $table->timestamps();

            $table->unique(['genre_group_id', 'genre_id']);
        });

        $this->normalizeGroupOrders();
        $this->normalizeGenreOrders();
        $this->backfillLegacyGenreGroupMemberships();

        Schema::table('genres', function (Blueprint $table): void {
            if (Schema::hasColumn('genres', 'group_id')) {
                $table->dropColumn('group_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('genre_group_genre');

        Schema::table('genres', function (Blueprint $table): void {
            if (! Schema::hasColumn('genres', 'group_id')) {
                $table->integer('group_id')->nullable()->default(null)->after('id');
            }
        });

        Schema::table('genres', function (Blueprint $table): void {
            $table->dropColumn('hidden_on_index');
        });

        Schema::table('genre_groups', function (Blueprint $table): void {
            $table->dropColumn('hidden_on_index');
        });
    }

    private function normalizeGroupOrders(): void
    {
        $nextOrder = (int) DB::table('genre_groups')->max('order') + 1;

        DB::table('genre_groups')
            ->whereNull('order')
            ->orderBy('title')
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $groupId) use (&$nextOrder): void {
                DB::table('genre_groups')
                    ->where('id', $groupId)
                    ->update(['order' => $nextOrder++]);
            });
    }

    private function backfillLegacyGenreGroupMemberships(): void
    {
        if (! Schema::hasColumn('genres', 'group_id')) {
            return;
        }

        $now = now();

        DB::table('genres')
            ->join('genre_groups', 'genre_groups.id', '=', 'genres.group_id')
            ->whereNotNull('genres.group_id')
            ->orderBy('genres.group_id')
            ->orderBy('genres.order')
            ->orderBy('genres.title')
            ->orderBy('genres.id')
            ->get([
                'genres.id as genre_id',
                'genres.group_id as genre_group_id',
                'genres.order',
            ])
            ->each(function (object $genre) use ($now): void {
                DB::table('genre_group_genre')->insert([
                    'genre_group_id' => (int) $genre->genre_group_id,
                    'genre_id' => (int) $genre->genre_id,
                    'order' => max(1, (int) $genre->order),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    private function normalizeGenreOrders(): void
    {
        DB::table('genres')
            ->get(['id', 'title', 'order'])
            ->sort(function (object $left, object $right): int {
                return [
                    $left->order === null ? PHP_INT_MAX : (int) $left->order,
                    $left->title,
                    (int) $left->id,
                ] <=> [
                    $right->order === null ? PHP_INT_MAX : (int) $right->order,
                    $right->title,
                    (int) $right->id,
                ];
            })
            ->pluck('id')
            ->values()
            ->each(function (int $genreId, int $index): void {
                DB::table('genres')
                    ->where('id', $genreId)
                    ->update(['order' => $index + 1]);
            });
    }
};
