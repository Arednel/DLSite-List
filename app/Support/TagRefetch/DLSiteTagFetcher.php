<?php

namespace App\Support\TagRefetch;

use App\Support\DLSite\DLSitePythonRunner;
use RuntimeException;

class DLSiteTagFetcher
{
    public function __construct(
        private DLSitePythonRunner $pythonRunner,
    ) {}

    /**
     * @return array{japanese: list<string>, english: list<string>}
     */
    public function fetch(string $workId): array
    {
        $result = $this->pythonRunner->runTagFetcher($workId);

        if ($result->failed()) {
            $message = trim($result->errorOutput()) ?: trim($result->output());
            throw new RuntimeException($message ?: 'DLSite tag fetch failed.');
        }

        $payload = json_decode($result->output(), true);

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
