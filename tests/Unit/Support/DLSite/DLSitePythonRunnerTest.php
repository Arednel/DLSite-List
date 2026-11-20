<?php

namespace Tests\Unit\Support\DLSite;

use App\Support\DLSite\DLSitePythonRunner;
use App\Support\Refetch\RefetchService;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class DLSitePythonRunnerTest extends TestCase
{
    public function test_it_runs_the_scraper_with_php_supplied_destinations(): void
    {
        config(['logging.retention_days' => 45]);

        Process::fake([
            '*' => Process::result(),
        ])->preventStrayProcesses();

        $jsonPath = storage_path('framework/testing/work.json');
        $imagePath = storage_path('framework/testing/images');

        $localeConfig = $this->defaultLocaleConfig();
        $result = app(DLSitePythonRunner::class)->fetchWork(
            'RJ123456',
            $jsonPath,
            $localeConfig,
            $imagePath,
        );

        $this->assertInstanceOf(ProcessResult::class, $result);

        Process::assertRan(function (PendingProcess $process) use ($jsonPath, $imagePath): bool {
            return $process->command === [
                $this->expectedPythonExecutable(),
                base_path('python/DLSiteScraper.py'),
                '--work-id',
                'RJ123456',
                '--json-output',
                $jsonPath,
                '--log-directory',
                storage_path('logs'),
                '--locale-config',
                json_encode($this->defaultLocaleConfig(), JSON_THROW_ON_ERROR),
                '--image-output',
                $imagePath,
            ] && $process->environment === [
                'LOG_RETENTION_DAYS' => '45',
            ] && $process->timeout === null;
        });
    }

    public function test_it_omits_image_output_when_images_are_not_requested(): void
    {
        config(['logging.retention_days' => 0]);

        Process::fake([
            '*' => Process::result(),
        ])->preventStrayProcesses();

        $jsonPath = storage_path('framework/testing/work.json');

        app(DLSitePythonRunner::class)->fetchWork('RJ654321', $jsonPath, $this->defaultLocaleConfig());

        Process::assertRan(function (PendingProcess $process) use ($jsonPath): bool {
            return $process->command === [
                $this->expectedPythonExecutable(),
                base_path('python/DLSiteScraper.py'),
                '--work-id',
                'RJ654321',
                '--json-output',
                $jsonPath,
                '--log-directory',
                storage_path('logs'),
                '--locale-config',
                json_encode($this->defaultLocaleConfig(), JSON_THROW_ON_ERROR),
            ] && $process->environment === [
                'LOG_RETENTION_DAYS' => '90',
            ] && $process->timeout === null;
        });
    }

    public function test_it_passes_explicit_locale_config_through_unchanged(): void
    {
        Process::fake(['*' => Process::result()])->preventStrayProcesses();

        $jsonPath = storage_path('framework/testing/work.json');
        $localeConfig = [
            'canonical' => 'future',
            'locales' => ['future' => 'future_API'],
        ];
        app(DLSitePythonRunner::class)->fetchWork('RJ123456', $jsonPath, $localeConfig);

        Process::assertRan(fn(PendingProcess $process): bool => $process->command === [
            $this->expectedPythonExecutable(),
            base_path('python/DLSiteScraper.py'),
            '--work-id',
            'RJ123456',
            '--json-output',
            $jsonPath,
            '--log-directory',
            storage_path('logs'),
            '--locale-config',
            json_encode($localeConfig, JSON_THROW_ON_ERROR),
        ]);
    }

    public function test_it_uses_an_explicit_timeout_when_requested(): void
    {
        Process::fake([
            '*' => Process::result(),
        ])->preventStrayProcesses();

        app(DLSitePythonRunner::class)->fetchWork(
            'RJ123456',
            storage_path('framework/testing/work.json'),
            $this->defaultLocaleConfig(),
            null,
            RefetchService::FETCH_PROCESS_TIMEOUT_SECONDS,
        );

        Process::assertRan(
            fn(PendingProcess $process): bool => $process->timeout === RefetchService::FETCH_PROCESS_TIMEOUT_SECONDS,
        );
    }

    /** @return array{canonical: string, locales: array<string, ?string>} */
    private function defaultLocaleConfig(): array
    {
        return [
            'canonical' => 'ja',
            'locales' => [
                'ja' => null,
                'en' => 'en_US',
            ],
        ];
    }

    private function expectedPythonExecutable(): string
    {
        return base_path(
            PHP_OS_FAMILY === 'Windows'
                ? 'python/venv/Scripts/python.exe'
                : 'python/venv/bin/python'
        );
    }
}
