<?php

namespace Tests\Feature;

use App\Models\Genre;
use App\Models\Option;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceSmokeTest extends TestCase
{
    use RefreshDatabase;

    // Adjust these three values to resize the performance smoke dataset.
    private const WORK_COUNT = 500;

    private const TAG_COUNT = 500;

    private const PIVOT_COUNT = 10000;

    private const PERFORMANCE_ITERATIONS = 3;

    private const WARNING_THRESHOLD_MS = 500;

    private const STRONG_WARNING_THRESHOLD_MS = 1000;

    private const HOT_TAG_NUMBER = 1;

    /**
     * @var list<string>
     */
    private array $performanceOutput = [];

    public function test_common_heavy_paths_report_average_response_time_warnings(): void
    {
        $this->seedPerformanceData();
        Option::setIndexPerPage(Option::DEFAULT_INDEX_PER_PAGE);

        $updateTargetNumber = $this->updateTargetProductNumber();
        $updateTargetId = $this->productId($updateTargetNumber);
        $updateReturnPage = $this->updateReturnPage();
        $wrongReturnPage = $updateReturnPage === 1 ? 2 : 1;
        $updateTagList = implode(', ', $this->tagTitlesForProductNumber($updateTargetNumber));
        $allTagFilter = implode(', ', array_slice($this->tagTitlesForProductNumber($updateTargetNumber), 0, 3));
        $allTagFilter = $allTagFilter !== '' ? $allTagFilter : $this->hotTagTitle();

        $measurements = [];
        $measurements['plain paginated index'] = $this->averageResponseTime(
            'plain paginated index',
            fn() => $this->get('/')->assertOk(),
        );
        $measurements['filtered index with tag and sort'] = $this->averageResponseTime(
            'filtered index with tag and sort',
            fn() => $this->get('/?' . http_build_query([
                'progress' => 'Listening',
                'tags' => $this->hotTagTitle(),
                'tag_match' => 'any',
                'sort_first_field' => 'score',
                'sort_first_direction' => 'desc',
            ]))->assertOk(),
        );
        $measurements['broad search index'] = $this->averageResponseTime(
            'broad search index',
            fn() => $this->get('/?' . http_build_query([
                'search' => 'PERF',
            ]))->assertOk(),
        );
        $measurements['hot tag index'] = $this->averageResponseTime(
            'hot tag index',
            fn() => $this->get('/?' . http_build_query([
                'tags' => $this->hotTagTitle(),
                'tag_match' => 'any',
            ]))->assertOk(),
        );
        $measurements['all-tags index'] = $this->averageResponseTime(
            'all-tags index',
            fn() => $this->get('/?' . http_build_query([
                'tags' => $allTagFilter,
                'tag_match' => 'all',
            ]))->assertOk(),
        );

        Option::setIndexPerPage(Option::INDEX_PER_PAGE_UNLIMITED);
        $measurements['unlimited index'] = $this->averageResponseTime(
            'unlimited index',
            fn() => $this->get('/')->assertOk(),
        );
        Option::setIndexPerPage(Option::DEFAULT_INDEX_PER_PAGE);

        $measurements['update redirect fast path'] = $this->averageResponseTime(
            'update redirect fast path',
            fn() => $this->post("/update/{$updateTargetId}", [
                'work_name' => sprintf('PERF_WORK_%d', $updateTargetNumber),
                'work_name_english' => sprintf('PERF_WORK_EN_%d', $updateTargetNumber),
                'progress' => $this->progressForProductNumber($updateTargetNumber),
                'score' => ($updateTargetNumber % 10) + 1,
                'series' => 'PERF_SERIES_' . ($updateTargetNumber % 20),
                'genre_custom' => $updateTagList,
                'return_query' => [
                    'page' => (string) $updateReturnPage,
                ],
                'return_fragment' => $updateTargetId,
            ])->assertRedirect($this->expectedUpdateRedirect($updateTargetId, $updateReturnPage)),
        );
        $measurements['update redirect page recalculation'] = $this->averageResponseTime(
            'update redirect page recalculation',
            fn() => $this->post("/update/{$updateTargetId}", [
                'work_name' => sprintf('PERF_WORK_%d', $updateTargetNumber),
                'work_name_english' => sprintf('PERF_WORK_EN_%d', $updateTargetNumber),
                'progress' => $this->progressForProductNumber($updateTargetNumber),
                'score' => ($updateTargetNumber % 10) + 1,
                'series' => 'PERF_SERIES_' . ($updateTargetNumber % 20),
                'genre_custom' => $updateTagList,
                'return_query' => [
                    'page' => (string) $wrongReturnPage,
                ],
                'return_fragment' => $updateTargetId,
            ])->assertRedirect($this->expectedUpdateRedirect($updateTargetId, $updateReturnPage)),
        );
        $measurements['update redirect filter cleanup'] = $this->averageResponseTime(
            'update redirect filter cleanup',
            fn() => $this->post("/update/{$updateTargetId}", [
                'work_name' => sprintf('PERF_WORK_%d', $updateTargetNumber),
                'work_name_english' => sprintf('PERF_WORK_EN_%d', $updateTargetNumber),
                'progress' => $this->progressForProductNumber($updateTargetNumber),
                'score' => ($updateTargetNumber % 10) + 1,
                'series' => 'PERF_SERIES_' . ($updateTargetNumber % 20),
                'genre_custom' => $updateTagList,
                'return_query' => [
                    'search' => 'PERF_HIDDEN_REDIRECT_TOKEN',
                    'page' => (string) $wrongReturnPage,
                ],
                'return_fragment' => $updateTargetId,
            ])->assertRedirect($this->expectedUpdateRedirect($updateTargetId, $updateReturnPage)),
        );
        $measurements['delete redirect page clamp'] = $this->averageResponseTime(
            'delete redirect page clamp',
            fn() => $this->post('/destroy/' . $this->deleteTargetId(), [
                'return_query' => [
                    'progress' => 'Listening',
                    'page' => (string) ($this->lastListeningPageAfterDelete() + 4),
                ],
            ])->assertRedirect($this->expectedProgressRedirect($this->lastListeningPageAfterDelete())),
            prepare: fn() => $this->seedDeleteTarget(),
        );

        foreach ($measurements as $label => $averageMs) {
            $this->reportPerformanceWarning($label, $averageMs);
        }

        $this->flushPerformanceOutput();

        foreach ($measurements as $averageMs) {
            $this->assertGreaterThan(0, $averageMs);
        }
    }

    private function seedPerformanceData(): void
    {
        $this->validatePerformanceSeedConfig();

        $now = now();

        collect(range(1, self::TAG_COUNT))
            ->chunk(100)
            ->each(function ($chunk) use ($now): void {
                DB::table('genres')->insert(
                    $chunk
                        ->map(fn(int $number): array => [
                            'group_id' => null,
                            'title' => $this->tagTitle($number),
                            'title_key' => Genre::titleKey($this->tagTitle($number)),
                            'description' => null,
                            'order' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])
                        ->all()
                );
            });

        collect(range(1, self::WORK_COUNT))
            ->chunk(100)
            ->each(function ($chunk) use ($now): void {
                DB::table('products')->insert(
                    $chunk
                        ->map(fn(int $number): array => [
                            'id' => $this->productId($number),
                            'rj_number' => $number,
                            'maker_id' => sprintf('RG%09d', $number),
                            'work_name' => sprintf('PERF_WORK_%d', $number),
                            'work_name_english' => sprintf('PERF_WORK_EN_%d', $number),
                            'age_category' => $number % 2 === 0 ? 'ALL_AGES' : 'R18',
                            'circle' => 'PERF_CIRCLE',
                            'work_image' => sprintf('storage/Works/%s/cover.jpg', $this->productId($number)),
                            'description' => null,
                            'description_english' => null,
                            'notes' => null,
                            'sample_images' => json_encode([]),
                            'score' => ($number % 10) + 1,
                            'series' => 'PERF_SERIES_' . ($number % 20),
                            'progress' => $this->progressForProductNumber($number),
                            'start_date' => null,
                            'start_date_sort' => null,
                            'end_date' => null,
                            'end_date_sort' => null,
                            'num_re_listen_times' => null,
                            're_listen_value' => null,
                            'priority' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])
                        ->all()
                );
            });

        $genreIds = DB::table('genres')
            ->orderBy('title')
            ->pluck('id')
            ->values();

        if (self::PIVOT_COUNT === 0) {
            return;
        }

        collect($this->pivotPairs())
            ->chunk(500)
            ->each(function ($chunk) use ($genreIds, $now): void {
                DB::table('genre_product')->insert(
                    $chunk
                        ->map(fn(array $pair): array => [
                            'product_id' => $this->productId($pair['product_number']),
                            'genre_id' => $genreIds[$pair['tag_index']],
                            'source' => Genre::PIVOT_SOURCE_CUSTOM,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])
                        ->all()
                );
            });
    }

    private function validatePerformanceSeedConfig(): void
    {
        if (self::WORK_COUNT < 1 || self::TAG_COUNT < 1 || self::PIVOT_COUNT < 0) {
            throw new \RuntimeException('Performance smoke counts must use at least one work, one tag, and zero or more pivots.');
        }

        if (self::WORK_COUNT * self::TAG_COUNT < self::PIVOT_COUNT) {
            throw new \RuntimeException('Performance smoke pivot count cannot exceed unique work/tag pairs.');
        }
    }

    private function updateTargetProductNumber(): int
    {
        $page = $this->updateReturnPage();
        $firstProductNumberOnPage = self::WORK_COUNT - (($page - 1) * Option::DEFAULT_INDEX_PER_PAGE);

        return max(1, $firstProductNumberOnPage - min(
            intdiv(Option::DEFAULT_INDEX_PER_PAGE, 2),
            $firstProductNumberOnPage - 1,
        ));
    }

    private function updateReturnPage(): int
    {
        return min(3, max(1, (int) ceil(self::WORK_COUNT / Option::DEFAULT_INDEX_PER_PAGE)));
    }

    private function expectedUpdateRedirect(string $productId, int $page): string
    {
        return $page > 1
            ? "/?page={$page}#{$productId}"
            : "/#{$productId}";
    }

    private function expectedProgressRedirect(int $page): string
    {
        return $page > 1
            ? "/?progress=Listening&page={$page}"
            : '/?progress=Listening';
    }

    private function deleteTargetId(): string
    {
        return 'RJ999999999';
    }

    private function seedDeleteTarget(): void
    {
        DB::table('products')->updateOrInsert(
            ['id' => $this->deleteTargetId()],
            [
                'rj_number' => 999999999,
                'maker_id' => 'RG999999999',
                'work_name' => 'PERF_DELETE_TARGET',
                'work_name_english' => 'PERF_DELETE_TARGET_EN',
                'age_category' => 'ALL_AGES',
                'circle' => 'PERF_CIRCLE',
                'work_image' => 'storage/Works/RJ999999999/cover.jpg',
                'description' => null,
                'description_english' => null,
                'notes' => null,
                'sample_images' => json_encode([]),
                'score' => 1,
                'series' => 'PERF_DELETE_SERIES',
                'progress' => 'Listening',
                'start_date' => null,
                'start_date_sort' => null,
                'end_date' => null,
                'end_date_sort' => null,
                'num_re_listen_times' => null,
                're_listen_value' => null,
                'priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    private function lastListeningPageAfterDelete(): int
    {
        $listeningCount = DB::table('products')
            ->where('progress', 'Listening')
            ->where('id', '<>', $this->deleteTargetId())
            ->count();

        return max(1, (int) ceil($listeningCount / Option::DEFAULT_INDEX_PER_PAGE));
    }

    /**
     * @return list<int>
     */
    private function tagIndexesForProductNumber(int $productNumber): array
    {
        return collect($this->pivotPairs())
            ->where('product_number', $productNumber)
            ->pluck('tag_index')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function tagTitlesForProductNumber(int $productNumber): array
    {
        return collect($this->tagIndexesForProductNumber($productNumber))
            ->map(fn(int $tagIndex): string => $this->tagTitle($tagIndex + 1))
            ->all();
    }

    private function hotTagTitle(): string
    {
        return $this->tagTitle(self::HOT_TAG_NUMBER);
    }

    /**
     * @return list<array{product_number: int, tag_index: int}>
     */
    private function pivotPairs(): array
    {
        $pairs = [];
        $seen = [];

        if (self::PIVOT_COUNT === 0) {
            return [];
        }

        for ($productNumber = 1; $productNumber <= self::WORK_COUNT && count($pairs) < self::PIVOT_COUNT; $productNumber++) {
            $this->addPivotPair($pairs, $seen, $productNumber, self::HOT_TAG_NUMBER - 1);
        }

        for ($round = 0; count($pairs) < self::PIVOT_COUNT; $round++) {
            for ($productNumber = 1; $productNumber <= self::WORK_COUNT && count($pairs) < self::PIVOT_COUNT; $productNumber++) {
                $tagIndex = self::TAG_COUNT === 1
                    ? 0
                    : (($productNumber - 1 + $round) % (self::TAG_COUNT - 1)) + 1;

                $this->addPivotPair($pairs, $seen, $productNumber, $tagIndex);
            }
        }

        return $pairs;
    }

    private function addPivotPair(array &$pairs, array &$seen, int $productNumber, int $tagIndex): void
    {
        $key = "{$productNumber}:{$tagIndex}";

        if (isset($seen[$key])) {
            return;
        }

        $seen[$key] = true;
        $pairs[] = [
            'product_number' => $productNumber,
            'tag_index' => $tagIndex,
        ];
    }

    private function productId(int $number): string
    {
        return sprintf('RJ%09d', $number);
    }

    private function tagTitle(int $number): string
    {
        return sprintf('PERF_TAG_%03d', $number);
    }

    private function progressForProductNumber(int $productNumber): string
    {
        return match ($productNumber % 3) {
            0 => 'Completed',
            1 => 'Plan to Listen',
            default => 'Listening',
        };
    }

    private function averageResponseTime(
        string $label,
        callable $request,
        int $iterations = self::PERFORMANCE_ITERATIONS,
        ?callable $prepare = null,
    ): float {
        $prepare?->__invoke();
        $request();

        $totalMs = 0.0;

        for ($index = 0; $index < $iterations; $index++) {
            $prepare?->__invoke();
            $startedAt = microtime(true);
            $request();
            $totalMs += (microtime(true) - $startedAt) * 1000;
        }

        $averageMs = $totalMs / $iterations;

        $this->performanceOutput[] = sprintf(
            'Performance smoke: %s averaged %.1fms over %d runs.',
            $label,
            $averageMs,
            $iterations,
        );

        return $averageMs;
    }

    private function reportPerformanceWarning(string $label, float $averageMs): void
    {
        if ($averageMs > self::STRONG_WARNING_THRESHOLD_MS) {
            $this->addPerformanceWarning(
                sprintf(
                    'PERFORMANCE WARNING: %s averaged %.1fms, which is above %dms.',
                    $label,
                    $averageMs,
                    self::STRONG_WARNING_THRESHOLD_MS,
                )
            );

            return;
        }

        if ($averageMs > self::WARNING_THRESHOLD_MS) {
            $this->addPerformanceWarning(
                sprintf(
                    'Performance warning: %s averaged %.1fms, which is above %dms.',
                    $label,
                    $averageMs,
                    self::WARNING_THRESHOLD_MS,
                )
            );
        }
    }

    private function addPerformanceWarning(string $message): void
    {
        $this->performanceOutput[] = $message;
        \PHPUnit\Event\Facade::emitter()->testRunnerTriggeredPhpunitWarning($message);
    }

    private function flushPerformanceOutput(): void
    {
        fwrite(STDERR, "\n" . implode("\n", $this->performanceOutput) . "\n");
        $this->performanceOutput = [];
    }
}
