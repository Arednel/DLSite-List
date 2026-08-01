<?php

namespace App\Support\DLSite;

use App\Logging\WeeklyRotatingFileHandler;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;

class DLSitePythonRunner
{
    public function fetchWork(
        string $workId,
        string $jsonPath,
        ?string $imageDirectory = null,
    ): ProcessResult {
        $arguments = [
            '--work-id',
            $workId,
            '--json-output',
            $jsonPath,
            '--log-directory',
            storage_path('logs'),
        ];

        if ($imageDirectory !== null) {
            $arguments[] = '--image-output';
            $arguments[] = $imageDirectory;
        }

        return $this->runScript('DLSiteScraper.py', $arguments);
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
