<?php

namespace App\Support;

use App\Models\Genre;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class GenreHierarchy
{
    /**
     * @param  list<int|string>  $genreIds
     * @return list<int>
     */
    public function ancestorIds(array $genreIds): array
    {
        return $this->relatedIds($genreIds, 'child_genre_id', 'parent_genre_id');
    }

    /**
     * @param  list<int|string>  $genreIds
     * @return list<int>
     */
    public function descendantIds(array $genreIds): array
    {
        return $this->relatedIds($genreIds, 'parent_genre_id', 'child_genre_id');
    }

    /**
     * Replace both sides of one tag's hierarchy and return children from newly added relations.
     *
     * @param  list<int|string>  $parentIds
     * @param  list<int|string>  $childIds
     * @return list<int>
     */
    public function sync(Genre $genre, array $parentIds, array $childIds): array
    {
        $parentIds = $this->normalizeIds($parentIds);
        $childIds = $this->normalizeIds($childIds);
        $genreId = $genre->getKey();

        $this->ensureAcyclicReplacement($genreId, $parentIds, $childIds);

        $parentChanges = $genre->parents()->sync($parentIds);
        $childChanges = $genre->children()->sync($childIds);
        $affectedChildIds = $childChanges['attached'];

        if ($parentChanges['attached'] !== []) {
            $affectedChildIds[] = $genreId;
        }

        return $this->normalizeIds($affectedChildIds);
    }

    /**
     * @param  list<int|string>  $genreIds
     * @return list<int>
     */
    private function relatedIds(array $genreIds, string $fromColumn, string $toColumn): array
    {
        $originIds = $this->normalizeIds($genreIds);
        $visitedIds = [];
        $frontierIds = $originIds;

        while ($frontierIds !== []) {
            $relatedIds = DB::table('genre_relations')
                ->whereIn($fromColumn, $frontierIds)
                ->pluck($toColumn)
                ->map(fn(int|string $genreId): int => (int) $genreId)
                ->all();

            $frontierIds = array_values(array_diff(
                $this->normalizeIds($relatedIds),
                $originIds,
                $visitedIds,
            ));
            $visitedIds = $this->normalizeIds([...$visitedIds, ...$frontierIds]);
        }

        return $visitedIds;
    }

    /**
     * @param  list<int>  $parentIds
     * @param  list<int>  $childIds
     */
    private function ensureAcyclicReplacement(int $genreId, array $parentIds, array $childIds): void
    {
        $relations = DB::table('genre_relations')
            ->where('parent_genre_id', '<>', $genreId)
            ->where('child_genre_id', '<>', $genreId)
            ->get(['parent_genre_id', 'child_genre_id'])
            ->map(fn(object $relation): array => [
                'parent' => (int) $relation->parent_genre_id,
                'child' => (int) $relation->child_genre_id,
            ])
            ->all();

        foreach ($parentIds as $parentId) {
            $relations[] = ['parent' => $parentId, 'child' => $genreId];
        }

        foreach ($childIds as $childId) {
            $relations[] = ['parent' => $genreId, 'child' => $childId];
        }

        $parentsByChild = [];

        foreach ($relations as $relation) {
            $parentsByChild[$relation['child']][] = $relation['parent'];
        }

        $visited = [];
        $visiting = [];
        $hasCycle = function (int $childId) use (&$hasCycle, &$visited, &$visiting, $parentsByChild): bool {
            if (isset($visiting[$childId])) {
                return true;
            }

            if (isset($visited[$childId])) {
                return false;
            }

            $visiting[$childId] = true;

            foreach ($parentsByChild[$childId] ?? [] as $parentId) {
                if ($hasCycle($parentId)) {
                    return true;
                }
            }

            unset($visiting[$childId]);
            $visited[$childId] = true;

            return false;
        };

        foreach (array_keys($parentsByChild) as $childId) {
            if ($hasCycle((int) $childId)) {
                throw ValidationException::withMessages([
                    'editingTagRelationships' => __('Parent/child tag relationships cannot contain a cycle.'),
                ]);
            }
        }
    }

    /**
     * @param  list<int|string>  $genreIds
     * @return list<int>
     */
    private function normalizeIds(array $genreIds): array
    {
        return collect($genreIds)
            ->map(fn(int|string $genreId): int => (int) $genreId)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
