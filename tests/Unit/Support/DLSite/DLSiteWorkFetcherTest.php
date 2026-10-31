<?php

namespace Tests\Unit\Support\DLSite;

use App\Support\DLSite\DLSiteWorkFetcher;
use App\Support\DLSite\DLSiteWorkUnavailableException;
use Illuminate\Process\FakeProcessResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
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
                'DLsite work fetch returned an invalid manifest.',
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

    public function test_exit_code_two_is_classified_as_an_unavailable_work_after_retries(): void
    {
        Storage::fake('local');
        $jsonPath = Storage::disk('local')->path('Works/RJ123456.json');
        Process::fake([
            '*' => Process::result(errorOutput: 'GeoBlocked DLsite work', exitCode: 2),
        ])->preventStrayProcesses();

        $this->expectException(DLSiteWorkUnavailableException::class);
        $this->expectExceptionMessage('GeoBlocked DLsite work');

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
        $this->expectExceptionMessage('DLsite work fetch did not create JSON.');

        app(DLSiteWorkFetcher::class)->fetch('RJ123456', $jsonPath);
    }

    public function test_original_lookup_follows_image_retries_and_preserves_both_responses(): void
    {
        [$jsonPath, $translated] = $this->translatedWork();
        $original = $this->originalWork();
        $original['japanese']['translation_info'] = ['original_workno' => 'RJ999999'];
        $calls = [];
        Process::fake(function ($process) use (&$calls, $original) {
            $command = $process->command;
            $id = $command[array_search('--work-id', $command) + 1];
            $calls[] = $id;
            if ($id === 'RJ01689827') {
                return $this->manifest(count($calls) < 3 ? ['cover.jpg'] : []);
            }
            $this->assertNotContains('--image-output', $command);
            $this->assertSame(590, $process->timeout);
            file_put_contents($command[array_search('--json-output', $command) + 1], json_encode($original));

            return $this->manifest([]);
        })->preventStrayProcesses();

        $result = app(DLSiteWorkFetcher::class)->fetch('RJ01689827', $jsonPath, 'images', 590);
        $this->assertSame(['RJ01689827', 'RJ01689827', 'RJ01689827', 'RJ01647180'], $calls);
        $this->assertSame('RaRo', $result->workData->circle);
        $this->assertSame('RG49556', $result->workData->makerId);
        $this->assertSame($translated, json_decode(file_get_contents($jsonPath), true));
        $this->assertSame($original, Storage::disk('local')->json('Works/RJ01689827 - RJ01647180.json'));
        $this->assertSame(
            ['Works/RJ01689827 - RJ01647180.json', 'Works/RJ01689827.json'],
            Storage::disk('local')->files('Works'),
        );
    }

    public function test_language_edition_identifies_current_work_as_original_and_takes_precedence(): void
    {
        [$jsonPath, $raw] = $this->translatedWork();
        $raw['japanese']['language_editions'][0]['workno'] = 'RJ01689827';
        $raw['japanese']['translation_info'] = ['original_workno' => 'RJ01647180'];
        file_put_contents($jsonPath, json_encode($raw));
        Process::fake(['*' => $this->manifest([])])->preventStrayProcesses();

        $result = app(DLSiteWorkFetcher::class)->fetch('RJ01689827', $jsonPath);

        $this->assertSame('Provider', $result->workData->circle);
        Process::assertRanTimes(fn(): bool => true, 1);
    }

    public function test_translation_info_is_used_when_language_editions_are_missing(): void
    {
        [$jsonPath, $raw] = $this->translatedWork();
        unset($raw['japanese']['language_editions']);
        $raw['japanese']['translation_info'] = ['original_workno' => 'RJ01647180'];
        file_put_contents($jsonPath, json_encode($raw));
        Process::fake(function ($process) {
            $command = $process->command;
            if ($command[array_search('--work-id', $command) + 1] === 'RJ01647180') {
                file_put_contents($command[array_search('--json-output', $command) + 1], json_encode($this->originalWork()));
            }

            return $this->manifest([]);
        })->preventStrayProcesses();

        $result = app(DLSiteWorkFetcher::class)->fetch('RJ01689827', $jsonPath);

        $this->assertSame('RaRo', $result->workData->circle);
        Process::assertRanTimes(fn(): bool => true, 2);
    }

    public static function missingOriginalReferences(): array
    {
        return [
            'missing translation metadata' => [null, false],
            'unknown original status' => [['is_original' => null], false],
            'original work' => [['is_original' => true], false],
            'translated work' => [['is_original' => false], true],
        ];
    }

    #[DataProvider('missingOriginalReferences')]
    public function test_missing_original_reference_logs_only_for_explicit_translations(?array $translationInfo, bool $shouldLog): void
    {
        [$jsonPath, $raw] = $this->translatedWork();
        unset($raw['japanese']['language_editions'], $raw['japanese']['translation_info']);
        if ($translationInfo !== null) {
            $raw['japanese']['translation_info'] = $translationInfo;
        }
        file_put_contents($jsonPath, json_encode($raw));
        Log::spy();
        Process::fake(['*' => $this->manifest([])])->preventStrayProcesses();

        $result = app(DLSiteWorkFetcher::class)->fetch('RJ01689827', $jsonPath);

        $this->assertSame('Provider', $result->workData->circle);
        Process::assertRanTimes(fn(): bool => true, 1);
        if ($shouldLog) {
            Log::shouldHaveReceived('info')->once()->with('No original DLsite work reference found.', [
                'work_id' => 'RJ01689827',
            ]);
        } else {
            Log::shouldNotHaveReceived('info');
        }
    }

    public function test_invalid_language_edition_original_id_falls_back_to_translation_info(): void
    {
        [$jsonPath, $raw] = $this->translatedWork();
        $raw['japanese']['language_editions'][0]['workno'] = '../bad';
        $raw['japanese']['translation_info'] = ['original_workno' => 'RJ01647180'];
        file_put_contents($jsonPath, json_encode($raw));
        Process::fake(function ($process) {
            $command = $process->command;
            if ($command[array_search('--work-id', $command) + 1] === 'RJ01647180') {
                file_put_contents($command[array_search('--json-output', $command) + 1], json_encode($this->originalWork()));
            }

            return $this->manifest([]);
        })->preventStrayProcesses();

        $result = app(DLSiteWorkFetcher::class)->fetch('RJ01689827', $jsonPath);

        $this->assertSame('RaRo', $result->workData->circle);
        $this->assertSame('RG49556', $result->workData->makerId);
        Process::assertRanTimes(fn(): bool => true, 2);
    }

    public function test_failed_original_lookup_keeps_translation_and_never_reuses_saved_original(): void
    {
        [$jsonPath] = $this->translatedWork();
        Storage::disk('local')->put('Works/RJ01689827 - RJ01647180.json', json_encode($this->originalWork()));
        Log::spy();
        Process::fake(['*' => Process::sequence([
            $this->manifest([]),
            Process::result(errorOutput: 'Unavailable original', exitCode: 2),
        ])])->preventStrayProcesses();

        $result = app(DLSiteWorkFetcher::class)->fetch('RJ01689827', $jsonPath);
        $this->assertSame('Provider', $result->workData->circle);
        $this->assertSame('RG60289', $result->workData->makerId);
        Process::assertRanTimes(fn(): bool => true, 2);
        Log::shouldHaveReceived('warning')->once();
    }

    public static function invalidOriginalOutputs(): array
    {
        return [
            'missing output' => [null],
            'wrong product id' => [json_encode(['japanese' => ['product_id' => 'RJ999999']])],
            'missing usable maker' => [json_encode([
                'japanese' => ['product_id' => 'RJ01647180'],
                'english' => ['product_id' => 'RJ01647180'],
            ])],
        ];
    }

    #[DataProvider('invalidOriginalOutputs')]
    public function test_invalid_or_missing_original_output_does_not_resolve_circle(?string $output): void
    {
        [$jsonPath] = $this->translatedWork();
        $savedOriginal = $this->originalWork();
        Storage::disk('local')->put('Works/RJ01689827 - RJ01647180.json', json_encode($savedOriginal));
        Log::spy();
        Process::fake(function ($process) use ($output) {
            $command = $process->command;
            if ($output !== null && $command[array_search('--work-id', $command) + 1] === 'RJ01647180') {
                file_put_contents($command[array_search('--json-output', $command) + 1], $output);
            }

            return $this->manifest([]);
        })->preventStrayProcesses();

        $this->assertSame('Provider', app(DLSiteWorkFetcher::class)->fetch('RJ01689827', $jsonPath)->workData->circle);
        $this->assertSame($savedOriginal, Storage::disk('local')->json('Works/RJ01689827 - RJ01647180.json'));
        $this->assertSame(
            ['Works/RJ01689827 - RJ01647180.json', 'Works/RJ01689827.json'],
            Storage::disk('local')->files('Works'),
        );
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_programming_errors_in_original_lookup_are_not_swallowed(): void
    {
        [$jsonPath] = $this->translatedWork();
        Process::fake(function ($process) {
            if (in_array('RJ01647180', $process->command, true)) {
                throw new \TypeError('Programming bug.');
            }

            return $this->manifest([]);
        })->preventStrayProcesses();

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Programming bug.');

        app(DLSiteWorkFetcher::class)->fetch('RJ01689827', $jsonPath);
    }

    public function test_original_process_exception_preserves_translation(): void
    {
        [$jsonPath] = $this->translatedWork();
        Log::spy();
        Process::fake(function ($process) {
            if (in_array('RJ01647180', $process->command, true)) {
                throw new RuntimeException('Process timed out.');
            }

            return $this->manifest([]);
        })->preventStrayProcesses();
        $this->assertSame('Provider', app(DLSiteWorkFetcher::class)->fetch('RJ01689827', $jsonPath)->workData->circle);
        Log::shouldHaveReceived('warning')->once();
    }

    private function translatedWork(): array
    {
        Storage::fake('local');
        $raw = ['japanese' => [
            'product_id' => 'RJ01689827',
            'maker_id' => 'RG60289',
            'circle' => 'Provider',
            'language_editions' => [
                ['workno' => 'RJ01647180', 'display_order' => 1],
                ['workno' => 'RJ01689827', 'display_order' => 2],
            ],
            'translation_info' => ['original_workno' => null],
        ], 'english' => []];
        Storage::disk('local')->put('Works/RJ01689827.json', json_encode($raw));

        return [Storage::disk('local')->path('Works/RJ01689827.json'), $raw];
    }

    private function originalWork(): array
    {
        $locale = ['product_id' => 'RJ01647180', 'maker_id' => 'RG49556', 'maker_name' => 'RaRo'];

        return ['japanese' => $locale, 'english' => $locale];
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
