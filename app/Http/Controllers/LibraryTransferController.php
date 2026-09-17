<?php

namespace App\Http\Controllers;

use App\Enums\LibraryTransferDirection;
use App\Enums\LibraryTransferPartStatus;
use App\Enums\LibraryTransferRunStatus;
use App\Http\Requests\LibraryTransferUploadRequest;
use App\Models\LibraryImportItem;
use App\Models\LibraryTransferEntry;
use App\Models\LibraryTransferPart;
use App\Models\LibraryTransferRun;
use App\Models\Option;
use App\Support\Transfers\LibraryTransferService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class LibraryTransferController extends Controller
{
    public function show(LibraryTransferRun $run)
    {
        return view('OptionsTransfer', ['run' => $run, 'productFormModalEnabled' => Option::productFormModalEnabled(), 'productFormModalCompletionAction' => Option::productFormModalCompletionAction()]);
    }

    public function upload(LibraryTransferUploadRequest $request, LibraryTransferRun $run, LibraryTransferService $service)
    {
        try {
            $part = $service->upload($run, $request->file('archive'));

            return response()->json(['part' => $part->id, 'status' => $part->status->value]);
        } catch (RuntimeException | LockTimeoutException $exception) {
            return response()->json(['message' => $exception->getMessage() ?: 'A transfer operation is in progress. Retry this file shortly.'], 422);
        }
    }

    public function download(LibraryTransferRun $run, LibraryTransferPart $part, LibraryTransferService $service)
    {
        abort_unless($run->direction === LibraryTransferDirection::Export && $run->status === LibraryTransferRunStatus::Ready && $part->status === LibraryTransferPartStatus::Ready, 404);
        abort_unless($service->exportPartAvailable($run, $part), 409, 'The archive file is missing or corrupt. Return to this transfer and retry it.');

        return response()->download(Storage::disk('local')->path($part->path), $part->filename, ['Content-Type' => 'application/zip', 'Cache-Control' => 'private, no-store']);
    }

    public function image(LibraryTransferRun $run, LibraryTransferEntry $entry)
    {
        abort_unless($run->direction === LibraryTransferDirection::Import && ! ($run->settings['without_images'] ?? false)
            && $entry->kind === 'images'
            && $entry->part?->status === LibraryTransferPartStatus::Valid && Storage::disk($entry->source_disk)->exists($entry->source_path), 404);

        return response()->file(Storage::disk($entry->source_disk)->path($entry->source_path), [
            'Content-Type' => $entry->media_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function change(LibraryTransferRun $run, LibraryImportItem $item)
    {
        abort_unless($run->direction === LibraryTransferDirection::Import, 404);

        return response()->json(
            ['entity' => $item->entity_key, 'category' => $item->category, 'baseline' => $item->baseline, 'incoming' => $item->incoming, 'warning' => $item->error],
            headers: ['Content-Disposition' => 'attachment; filename="change-' . $item->id . '.json"', 'Cache-Control' => 'private, no-store'],
            options: JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
