<?php

namespace Tests\Unit\Support\DLSite;

use App\Support\DLSite\DLSitePythonRunner;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class DLSitePythonRunnerTest extends TestCase
{
    public function test_it_runs_the_scraper_with_the_project_python_venv_and_storage_path(): void
    {
        Process::fake([
            '*' => Process::result(),
        ])->preventStrayProcesses();

        $result = app(DLSitePythonRunner::class)->runScraper('RJ123456');

        $this->assertInstanceOf(ProcessResult::class, $result);

        Process::assertRan(function (PendingProcess $process): bool {
            return $process->command === [
                $this->expectedPythonExecutable(),
                base_path('python/DLSiteScraper.py'),
                storage_path(),
                'RJ123456',
            ] && $process->timeout === null;
        });
    }

    public function test_it_runs_the_tag_fetcher_with_the_project_python_venv(): void
    {
        Process::fake([
            '*' => Process::result(),
        ])->preventStrayProcesses();

        app(DLSitePythonRunner::class)->runTagFetcher('RJ654321');

        Process::assertRan(function (PendingProcess $process): bool {
            return $process->command === [
                $this->expectedPythonExecutable(),
                base_path('python/DLSiteTagFetcher.py'),
                'RJ654321',
            ] && $process->timeout === null;
        });
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
