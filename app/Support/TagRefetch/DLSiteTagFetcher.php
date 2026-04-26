<?php

namespace App\Support\TagRefetch;

use RuntimeException;
use Symfony\Component\Process\Process;

class DLSiteTagFetcher
{
    /**
     * @return array{japanese: list<string>, english: list<string>}
     */
    public function fetch(string $workId): array
    {
        $pythonExe = base_path(
            PHP_OS_FAMILY === 'Windows'
                ? 'python/venv/Scripts/python.exe'
                : 'python/venv/bin/python'
        );

        $process = new Process([
            $pythonExe,
            base_path('python/DLSiteTagFetcher.py'),
            $workId,
        ]);
        $process->setTimeout(0);
        $process->run();

        if (! $process->isSuccessful()) {
            $message = trim($process->getErrorOutput()) ?: trim($process->getOutput());
            throw new RuntimeException($message ?: 'DLSite tag fetch failed.');
        }

        $payload = json_decode($process->getOutput(), true);

        if (! is_array($payload)) {
            throw new RuntimeException('DLSite tag fetch returned invalid JSON.');
        }

        return [
            'japanese' => $this->normalizeTags(data_get($payload, 'japanese.genre', [])),
            'english' => $this->normalizeTags(data_get($payload, 'english.genre', [])),
        ];
    }

    /**
     * @return list<string>
     */
    private function normalizeTags(mixed $tags): array
    {
        return collect(is_array($tags) ? $tags : [])
            ->map(fn (mixed $tag): string => trim((string) $tag))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
