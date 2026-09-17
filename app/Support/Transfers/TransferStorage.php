<?php

namespace App\Support\Transfers;

use App\Models\LibraryTransferRun;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class TransferStorage
{
    public function stageUpload(LibraryTransferRun $run, UploadedFile $file): string
    {
        $directory = $run->directory() . '/uploads';
        $name = (string) Str::uuid() . '.zip';
        $disk = Storage::disk('local');
        $disk->makeDirectory($directory);

        if (is_uploaded_file($file->getPathname())) {
            $file->move($disk->path($directory), $name);
            $path = $directory . '/' . $name;
        } else {
            $path = $file->storeAs($directory, $name, 'local');
        }

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Unable to save uploaded archive.');
        }

        return $path;
    }

    public function stageDirectory(LibraryTransferRun $run, string $uploadPath): string
    {
        $this->assertRunPath($run, $uploadPath);

        return $run->directory() . '/staged/' . pathinfo($uploadPath, PATHINFO_FILENAME);
    }

    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $disk = Storage::disk('local');
        try {
            if ($disk->exists($path) && ! $disk->delete($path)) {
                throw new RuntimeException("Unable to remove temporary transfer file: {$path}");
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function deleteStage(LibraryTransferRun $run, ?string $uploadPath): void
    {
        if ($uploadPath === null || $uploadPath === '') {
            return;
        }

        $stage = $this->stageDirectory($run, $uploadPath);
        $disk = Storage::disk('local');
        try {
            if ($disk->exists($stage) && ! $disk->deleteDirectory($stage)) {
                throw new RuntimeException("Unable to remove temporary transfer directory: {$stage}");
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function deleteRun(LibraryTransferRun $run): void
    {
        $disk = Storage::disk('local');
        if ($disk->exists($run->directory()) && ! $disk->deleteDirectory($run->directory())) {
            throw new RuntimeException('Unable to clean up staged transfer files.');
        }
    }

    public function sweep(LibraryTransferRun $run): void
    {
        // Uploads stage their file before committing its reference. Skip optional cleanup while that window is open, and hold the lock through the reference scan.
        Cache::lock('transfer-upload-' . $run->id, config('transfers.lock_seconds'))
            ->get(fn() => $this->sweepUnreferenced($run));
    }

    private function sweepUnreferenced(LibraryTransferRun $run): void
    {
        $disk = Storage::disk('local');
        $referenced = [];
        foreach ($run->parts()->get(['path', 'upload_path', 'candidate']) as $part) {
            if ($part->path) {
                $referenced[$part->path] = true;
            }
            if ($part->upload_path) {
                $referenced[$part->upload_path] = true;
            }
            if (is_string($part->candidate['path'] ?? null)) {
                $referenced[$part->candidate['path']] = true;
            }
        }
        foreach ($run->entries()->where('source_disk', 'local')->pluck('source_path') as $path) {
            $referenced[$path] = true;
        }

        foreach (['uploads', 'staged', 'build'] as $directory) {
            $root = $run->directory() . '/' . $directory;
            if (! $disk->exists($root)) {
                continue;
            }
            foreach ($disk->allFiles($root) as $path) {
                if (isset($referenced[$path])) {
                    continue;
                }
                $this->delete($path);
            }
        }
    }

    public function publish(LibraryTransferRun $run, string $temporary, string $final): void
    {
        $this->assertRunPath($run, $temporary);
        $this->assertRunPath($run, $final);
        $source = Storage::disk('local')->path($temporary);
        $destination = Storage::disk('local')->path($final);
        if (! @rename($source, $destination)) {
            throw new RuntimeException('Unable to publish ZIP.');
        }
    }

    private function assertRunPath(LibraryTransferRun $run, string $path): void
    {
        $prefix = $run->directory() . '/';
        if (! str_starts_with(str_replace('\\', '/', $path), $prefix) || str_contains($path, '..')) {
            throw new RuntimeException('Unsafe transfer storage path.');
        }
    }
}
