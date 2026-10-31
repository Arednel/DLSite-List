<?php

namespace App\Support\DLSite;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Exception;
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
        ?int $processTimeoutSeconds = null,
    ): DLSiteFetchResult {
        $lastResult = null;
        $lastFetch = null;
        $lastRaw = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $lastResult = $this->runner->fetchWork(
                $workId,
                $jsonPath,
                $imageDirectory,
                $processTimeoutSeconds,
            );

            if ($lastResult->failed()) {
                continue;
            }

            $failedImages = $this->failedImages($lastResult);

            if (! File::isFile($jsonPath)) {
                throw new RuntimeException('DLsite work fetch did not create JSON.');
            }

            $raw = File::json($jsonPath);

            if (! is_array($raw)) {
                throw new RuntimeException('DLsite work fetch returned invalid JSON.');
            }

            $lastFetch = new DLSiteFetchResult(
                workData: DLSiteWorkData::fromArray($raw, $workId),
                failedImages: $failedImages,
            );
            $lastRaw = $raw;

            if (! $lastFetch->hasImageFailures()) {
                break;
            }
        }

        if ($lastFetch !== null) {
            // Resolve once, after the translation's image retry cycle finishes.
            $original = $this->fetchOriginal($workId, $lastRaw, $jsonPath, $processTimeoutSeconds);

            if ($original === null) {
                return $lastFetch;
            }

            return new DLSiteFetchResult(
                workData: DLSiteWorkData::fromArray($lastRaw, $workId, $original),
                failedImages: $lastFetch->failedImages,
            );
        }

        $message = $this->failureMessage($lastResult);

        if ($lastResult?->exitCode() === 2) {
            throw new DLSiteWorkUnavailableException($message);
        }

        throw new RuntimeException($message);
    }

    private function fetchOriginal(string $workId, array $raw, string $jsonPath, ?int $timeout): ?array
    {
        // Prefer the Japanese language-edition group; fall back to the older translation reference.
        $originalId = DLSiteWorkData::originalWorkId($raw);

        if ($originalId === null) {
            if (data_get($raw, 'japanese.translation_info.is_original') === false) {
                Log::info('No original DLsite work reference found.', [
                    'work_id' => $workId,
                ]);
            }

            return null;
        }

        if ($originalId === $workId) {
            return null;
        }

        $outputPath = null;

        try {
            if (Validator::make(
                ['original_id' => $originalId],
                ['original_id' => ['required', 'string', 'max:191', 'regex:/\A(?:RJ|BJ|VJ)\d+\z/']],
            )->fails()) {
                throw new RuntimeException('Invalid original work ID.');
            }

            // A translation-specific filename cannot overwrite an independently saved original work.
            $destination = dirname($jsonPath) . DIRECTORY_SEPARATOR . "{$workId} - {$originalId}.json";
            // Fresh output prevents a missing response from reusing an older saved JSON.
            $outputPath = $destination . '.' . Str::uuid() . '.json';
            $result = $this->runner->fetchWork($originalId, $outputPath, null, $timeout);
            if ($result->failed()) {
                throw new RuntimeException($this->failureMessage($result));
            }

            $this->failedImages($result);
            if (! File::isFile($outputPath)) {
                throw new RuntimeException('Original fetch did not create JSON.');
            }
            $original = File::json($outputPath);
            if (! is_array($original)) {
                throw new RuntimeException('Original fetch returned invalid JSON.');
            }
            foreach (['japanese', 'english'] as $language) {
                if (data_get($original, "{$language}.product_id") !== $originalId) {
                    throw new RuntimeException('Original fetch returned a different product ID.');
                }
            }
            if (DLSiteWorkData::originalMaker($original) === null) {
                throw new RuntimeException('Original work has no usable Circle maker.');
            }
            if (! File::move($outputPath, $destination)) {
                throw new RuntimeException('Could not save original work JSON.');
            }

            return $original;
        } catch (Exception $exception) {
            // A failed original lookup must not discard a successfully fetched translation.
            Log::warning('Could not resolve the original DLsite Circle.', [
                'work_id' => $workId,
                'original_work_id' => $originalId,
                'reason' => $exception->getMessage(),
            ]);

            return null;
        } finally {
            if ($outputPath !== null && File::isFile($outputPath)) {
                File::delete($outputPath);
            }
        }
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
            throw new RuntimeException('DLsite work fetch returned an invalid manifest.');
        }

        return array_values($manifest['failed_images']);
    }

    private function failureMessage(?ProcessResult $result): string
    {
        $message = trim((string) $result?->errorOutput());

        return $message === '' ? 'DLsite fetch failed.' : $message;
    }
}
