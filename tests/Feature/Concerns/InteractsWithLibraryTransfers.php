<?php

namespace Tests\Feature\Concerns;

use App\Enums\LibraryImportDecision;
use App\Enums\LibraryImportItemStatus;
use App\Enums\LibraryTransferAction;
use App\Enums\LibraryTransferRunStatus;
use App\Jobs\LibraryTransferJob;
use App\Models\LibraryTransferPart;
use App\Models\LibraryTransferRun;
use App\Support\Transfers\ImportReview;
use App\Support\Transfers\LibraryTransferService;
use App\Support\Transfers\TransferArchive;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

trait InteractsWithLibraryTransfers
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Bus::fake();
        config(['queue.default' => 'database']);
    }

    private function service(): LibraryTransferService
    {
        return app(LibraryTransferService::class);
    }

    private function archive(): TransferArchive
    {
        return app(TransferArchive::class);
    }

    private function planExport(LibraryTransferRun $run): LibraryTransferRun
    {
        do {
            $this->archive()->plan($run->fresh());
        } while ($run->fresh()->status === LibraryTransferRunStatus::Planning);

        return $run->fresh();
    }

    private function review(): ImportReview
    {
        return app(ImportReview::class);
    }

    private function executeJob(LibraryTransferJob $job): void
    {
        app(Pipeline::class)
            ->send($job)
            ->through($job->middleware())
            ->then(function (LibraryTransferJob $job): void {
                app()->call([$job, 'handle']);
            });
    }

    private function exported(array $scopes = ['works'], array $ids = []): LibraryTransferRun
    {
        $run = $this->service()->export($scopes, $ids === [] ? 'all' : 'selected', $ids);
        $run = $this->planExport($run);
        foreach ($run->parts()->get() as $part) {
            $this->archive()->build($part);
        }

        return $run->fresh();
    }

    private function imported(LibraryTransferRun $export): LibraryTransferRun
    {
        $run = $this->service()->startImport();
        foreach ($export->parts()->orderByDesc('id')->get() as $part) {
            $uploaded = $this->service()->upload($run->fresh(), new UploadedFile(Storage::disk('local')->path($part->path), $part->filename, 'application/zip', null, true));
            $this->service()->inspect($run->fresh(), $uploaded->id);
        }
        while ($run->fresh()->status === LibraryTransferRunStatus::Analyzing) {
            $this->service()->analyze($run->fresh());
        }

        return $run->fresh();
    }

    private function apply(LibraryTransferRun $run, LibraryImportDecision|string $decision = LibraryImportDecision::Overwrite): void
    {
        $run->items()->where('status', LibraryImportItemStatus::Pending)->update(['decision' => $decision]);
        $this->service()->action($run->fresh(), LibraryTransferAction::Apply);
        while ($run->fresh()->status === LibraryTransferRunStatus::Applying) {
            $this->service()->apply($run->fresh());
        }
    }

    private function rewriteData(LibraryTransferPart $part, Closure $change): void
    {
        $this->rewriteArchiveEntry($part, function (array $data) use ($change): array {
            $data['records'] = $change($data['records']);

            return $data;
        });
    }

    private function rewriteWorkData(LibraryTransferPart $part, Closure $change): void
    {
        $this->rewriteArchiveEntry($part, $change, 'works');
    }

    private function rewriteArchiveEntry(LibraryTransferPart $part, Closure $change, ?string $section = null): void
    {
        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($part->path));
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $entry = $section === null
            ? $manifest['entries'][0]
            : collect($manifest['entries'])->firstWhere('section', $section);
        $path = $entry['path'];
        $data = $change(json_decode($zip->getFromName($path), true));
        $bytes = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        foreach ($manifest['entries'] as &$manifestEntry) {
            if ($manifestEntry['path'] === $path) {
                $manifestEntry['bytes'] = strlen($bytes);
                $manifestEntry['sha256'] = hash('sha256', $bytes);
            }
        }
        unset($manifestEntry);
        $zip->addFromString($path, $bytes);
        $zip->setCompressionName($path, ZipArchive::CM_STORE);
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        $zip->close();
    }

    private function saveImage(string $path, int $width = 10): string
    {
        $image = UploadedFile::fake()->image(basename($path), $width, 10);
        $bytes = file_get_contents($image->getRealPath());
        Storage::disk('public')->put($path, $bytes);

        return hash('sha256', $bytes);
    }
}
