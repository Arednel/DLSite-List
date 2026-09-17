<?php

namespace App\Support;

final class GraphCycleValidator
{
    /**
     * @param  list<array{int|string, int|string}>  $edges
     */
    public static function isAcyclic(array $edges): bool
    {
        $children = [];
        $degree = [];
        foreach ($edges as [$parent, $child]) {
            $children[$parent][] = $child;
            $degree[$parent] ??= 0;
            $degree[$child] = ($degree[$child] ?? 0) + 1;
        }

        $queue = array_keys(array_filter($degree, fn($count) => $count === 0));
        $seen = 0;
        while ($queue !== []) {
            $parent = array_pop($queue);
            $seen++;
            foreach ($children[$parent] ?? [] as $child) {
                if (--$degree[$child] === 0) {
                    $queue[] = $child;
                }
            }
        }

        return $seen === count($degree);
    }
}
