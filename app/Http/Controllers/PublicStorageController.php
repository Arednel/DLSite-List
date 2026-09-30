<?php

namespace App\Http\Controllers;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\CorruptedPathDetected;
use League\Flysystem\PathTraversalDetected;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicStorageController extends Controller
{
    public function __invoke(string $path): BinaryFileResponse
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        try {
            abort_unless($disk->fileExists($path), 404);

            return response()->file($disk->path($path));
        } catch (PathTraversalDetected | CorruptedPathDetected) {
            abort(404);
        }
    }
}
