<?php

namespace Tests\Unit\Support\DLSite;

use App\Support\DLSite\DLSiteWorkFetcher;
use Illuminate\Process\FakeProcessResult;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DLSiteWorkFetcherTest extends TestCase
{
    public function test_it_retries_failed_images_five_times_and_returns_the_latest_partial_result(): void
    {
        Storage::fake('local');
        $jsonPath = Storage::disk('local')->path('Refetch/1/Works/RJ123456.json');
        Storage::disk('local')->put('Refetch/1/Works/RJ123456.json', json_encode([
            'japanese' => [
                'product_id' => 'RJ123456',
                'work_name' => 'Fetched Title',
            ],
            'english' => [],
        ], JSON_THROW_ON_ERROR));

        Process::fake([
            '*' => Process::sequence([
                $this->manifest(['cover.jpg']),
                $this->manifest(['sample_1.jpg']),
                $this->manifest(['sample_1.jpg']),
                $this->manifest(['sample_1.jpg']),
                $this->manifest(['sample_1.jpg']),
            ]),
        ])->preventStrayProcesses();

        $result = app(DLSiteWorkFetcher::class)->fetch('RJ123456', $jsonPath, 'unused-images');

        $this->assertSame('Fetched Title', $result->workData->workName);
        $this->assertSame(['sample_1.jpg'], $result->failedImages);
        Process::assertRanTimes(fn(): bool => true, DLSiteWorkFetcher::MAX_ATTEMPTS);
    }

    public function test_it_stops_after_the_first_complete_attempt(): void
    {
        Storage::fake('local');
        $jsonPath = Storage::disk('local')->path('Works/RJ123456.json');
        Storage::disk('local')->put('Works/RJ123456.json', json_encode([
            'japanese' => ['product_id' => 'RJ123456'],
            'english' => [],
        ], JSON_THROW_ON_ERROR));

        Process::fake([
            '*' => $this->manifest([]),
        ])->preventStrayProcesses();

        app(DLSiteWorkFetcher::class)->fetch('RJ123456', $jsonPath);

        Process::assertRanTimes(fn(): bool => true, 1);
    }

    public function test_it_trusts_the_manifest_without_reconciling_image_files(): void
    {
        Storage::fake('local');
        $jsonPath = Storage::disk('local')->path('Works/RJ123456.json');
        Storage::disk('local')->put('Works/RJ123456.json', json_encode([
            'japanese' => [
                'product_id' => 'RJ123456',
                'work_image' => 'cover-url',
            ],
            'english' => [],
        ], JSON_THROW_ON_ERROR));
        Process::fake([
            '*' => $this->manifest([]),
        ])->preventStrayProcesses();

        $result = app(DLSiteWorkFetcher::class)->fetch(
            'RJ123456',
            $jsonPath,
            Storage::disk('local')->path('Works/RJ123456'),
        );

        $this->assertSame([], $result->failedImages);
        Process::assertRanTimes(fn(): bool => true, 1);
    }

    public function test_it_rejects_an_invalid_manifest_without_using_existing_json_or_retrying(): void
    {
        Storage::fake('local');
        $jsonPath = Storage::disk('local')->path('Works/RJ123456.json');
        Storage::disk('local')->put('Works/RJ123456.json', json_encode([
            'japanese' => ['product_id' => 'RJ123456'],
            'english' => [],
        ], JSON_THROW_ON_ERROR));
        Process::fake(['*' => Process::result()])->preventStrayProcesses();

        try {
            app(DLSiteWorkFetcher::class)->fetch('RJ123456', $jsonPath);
            $this->fail('Expected an invalid manifest exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'DLSite work fetch returned an invalid manifest.',
                $exception->getMessage(),
            );
        }

        Process::assertRanTimes(fn(): bool => true, 1);
    }

    public function test_it_retries_failed_processes_without_falling_back_to_existing_json(): void
    {
        Storage::fake('local');
        $jsonPath = Storage::disk('local')->path('Works/RJ123456.json');
        Storage::disk('local')->put('Works/RJ123456.json', json_encode([
            'japanese' => ['product_id' => 'RJ123456'],
            'english' => [],
        ], JSON_THROW_ON_ERROR));
        Process::fake([
            '*' => Process::result(errorOutput: 'Fetch failed.', exitCode: 1),
        ])->preventStrayProcesses();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Fetch failed.');

        try {
            app(DLSiteWorkFetcher::class)->fetch('RJ123456', $jsonPath);
        } finally {
            Process::assertRanTimes(fn(): bool => true, DLSiteWorkFetcher::MAX_ATTEMPTS);
        }
    }

    public function test_it_rejects_a_valid_manifest_when_json_was_not_created(): void
    {
        Storage::fake('local');
        $jsonPath = Storage::disk('local')->path('Works/RJ123456.json');
        Process::fake(['*' => $this->manifest([])])->preventStrayProcesses();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DLSite work fetch did not create JSON.');

        app(DLSiteWorkFetcher::class)->fetch('RJ123456', $jsonPath);
    }

    /**
     * @param  list<string>  $failedImages
     */
    private function manifest(array $failedImages): FakeProcessResult
    {
        return Process::result(output: json_encode([
            'product_id' => 'RJ123456',
            'downloaded_images' => [],
            'failed_images' => $failedImages,
        ], JSON_THROW_ON_ERROR));
    }
}
