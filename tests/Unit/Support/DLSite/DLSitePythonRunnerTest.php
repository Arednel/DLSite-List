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

        $result = app(DLSitePythonRunner::class)->fetchWork(
            'RJ123456',
            $jsonPath,
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

        app(DLSitePythonRunner::class)->fetchWork('RJ654321', $jsonPath);

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
            ] && $process->environment === [
                'LOG_RETENTION_DAYS' => '90',
            ] && $process->timeout === null;
        });
    }

    public function test_it_uses_an_explicit_timeout_when_requested(): void
    {
        Process::fake([
            '*' => Process::result(),
        ])->preventStrayProcesses();

        app(DLSitePythonRunner::class)->fetchWork(
            'RJ123456',
            storage_path('framework/testing/work.json'),
            null,
            RefetchService::FETCH_PROCESS_TIMEOUT_SECONDS,
        );

        Process::assertRan(
            fn(PendingProcess $process): bool => $process->timeout === RefetchService::FETCH_PROCESS_TIMEOUT_SECONDS,
        );
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
