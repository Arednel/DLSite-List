<?php

namespace App\Support\DLSite;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class DLSiteWorkFetcher
{
    public const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly DLSitePythonRunner $runner,
    ) {}

    public function fetch(
        string $workId,
        string $jsonPath,
        ?string $imageDirectory = null,
    ): DLSiteFetchResult {
        $lastResult = null;
        $lastFetch = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $lastResult = $this->runner->fetchWork($workId, $jsonPath, $imageDirectory);

            if ($lastResult->failed()) {
                continue;
            }

            $failedImages = $this->failedImages($lastResult);

            if (! File::isFile($jsonPath)) {
                throw new RuntimeException('DLSite work fetch did not create JSON.');
            }

            $raw = File::json($jsonPath);

            if (! is_array($raw)) {
                throw new RuntimeException('DLSite work fetch returned invalid JSON.');
            }

            $lastFetch = new DLSiteFetchResult(
                workData: DLSiteWorkData::fromArray($raw, $workId),
                failedImages: $failedImages,
            );

            if (! $lastFetch->hasImageFailures()) {
                return $lastFetch;
            }
        }

        return $lastFetch ?? throw new RuntimeException($this->failureMessage($lastResult));
    }

    /**
     * @return list<string>
     */
    private function failedImages(ProcessResult $result): array
    {
        $manifest = json_decode(trim($result->output()), true);

        if (
            ! is_array($manifest)
            || ! isset($manifest['failed_images'])
            || ! is_array($manifest['failed_images'])
        ) {
            throw new RuntimeException('DLSite work fetch returned an invalid manifest.');
        }

        return array_values($manifest['failed_images']);
    }

    private function failureMessage(?ProcessResult $result): string
    {
        $message = trim((string) $result?->errorOutput());

        return $message === '' ? 'DLSite work fetch failed.' : $message;
    }
}
