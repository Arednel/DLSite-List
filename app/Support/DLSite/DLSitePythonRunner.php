<?php

namespace App\Support\DLSite;

use App\Logging\WeeklyRotatingFileHandler;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;

class DLSitePythonRunner
{
    public function runScraper(string $workId): ProcessResult
    {
        return $this->runScript('DLSiteScraper.py', [
            storage_path(),
            $workId,
        ]);
    }

    public function runTagFetcher(string $workId): ProcessResult
    {
        return $this->runScript('DLSiteTagFetcher.py', [
            $workId,
        ]);
    }

    /**
     * @param  list<string>  $arguments
     */
    private function runScript(string $script, array $arguments): ProcessResult
    {
        return Process::forever()
            ->env([
                'LOG_RETENTION_DAYS' => (string) WeeklyRotatingFileHandler::normalizeRetentionDays(
                    config('logging.retention_days'),
                ),
            ])
            ->run([
                $this->pythonExecutable(),
                base_path("python/{$script}"),
                ...$arguments,
            ]);
    }

    private function pythonExecutable(): string
    {
        return base_path(
            PHP_OS_FAMILY === 'Windows'
                ? 'python/venv/Scripts/python.exe'
                : 'python/venv/bin/python'
        );
    }
}
