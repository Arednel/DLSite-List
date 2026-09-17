<?php

namespace App\Livewire;

use App\Enums\LibraryTransferDirection;
use App\Models\LibraryTransferRun;
use App\Models\Option;
use App\Models\Product;
use App\Support\Transfers\LibraryTransferCleanupService;
use App\Support\Transfers\LibraryTransferService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Json;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

class OptionsTransfers extends Component
{
    public array $scopes = ['works', 'tag-library', 'options'];

    public string $mode = 'all';

    public string $search = '';

    public array $selectedIds = [];

    public string $sizeChoice = '';

    public string $customMib = '';

    public string $sizeNotice = '';

    public string $cleanupNotice = '';

    #[Locked]
    public bool $confirmingCleanup = false;

    public function mount(): void
    {
        $size = (string) Option::exportPartMib();
        $this->sizeChoice = in_array($size, [...array_map('strval', self::presets()), 'unlimited'], true) ? $size : 'custom';
        $this->customMib = $size === 'unlimited' ? (string) config('transfers.default_part_mib') : $size;
    }

    public static function presets(): array
    {
        return config('transfers.part_size_presets_mib');
    }

    public function saveSize(): void
    {
        $validated = $this->validate([
            'sizeChoice' => ['required', Rule::in([...array_map('strval', self::presets()), 'custom', 'unlimited'])],
            'customMib' => [
                Rule::excludeUnless(fn(): bool => $this->sizeChoice === 'custom'),
                'required',
                'integer',
                'min:1',
                'max:' . Option::maxExportPartMib(),
            ],
        ]);
        $value = $validated['sizeChoice'] === 'custom' ? (int) $validated['customMib'] : $validated['sizeChoice'];
        Option::setExportPartMib($value === 'unlimited' ? $value : (int) $value);
        $this->sizeNotice = 'Export part size saved.';
    }

    public function updatedScopes(): void
    {
        $this->scopes = array_values(array_unique(array_intersect(
            $this->scopes,
            ['works', 'images', 'tag-library', 'options'],
        )));

        if (! in_array('works', $this->scopes, true)) {
            $this->scopes = array_values(array_diff($this->scopes, ['images']));
        }
    }

    public function updatedSelectedIds(): void
    {
        $this->selectedIds = collect($this->selectedIds)
            ->map(fn(mixed $productId): string => (string) $productId)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function export(LibraryTransferService $service): void
    {
        try {
            $run = $service->export($this->scopes, $this->mode, $this->selectedIds);
            $this->redirectRoute('options.transfers.show', ['run' => $run]);
        } catch (RuntimeException $exception) {
            $this->addError('transfer', $exception->getMessage());
        }
    }

    #[Json]
    public function startImport(int $fileCount): array
    {
        if ($fileCount < 1) {
            throw ValidationException::withMessages([
                'transfer' => __('Choose at least one ZIP file before starting the import.'),
            ]);
        }

        try {
            $run = app(LibraryTransferService::class)->startImport();

            return [
                'runId' => $run->id,
                'uploadUrl' => route('options.transfers.upload', $run),
                'showUrl' => route('options.transfers.show', $run),
            ];
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['transfer' => $exception->getMessage()]);
        }
    }

    public function askCleanup(LibraryTransferCleanupService $cleanupService): void
    {
        $this->cleanupNotice = '';
        $this->resetErrorBag('cleanup');

        if ($cleanupService->unavailable()) {
            $this->addError('cleanup', __(LibraryTransferCleanupService::UNAVAILABLE_MESSAGE));

            return;
        }

        $this->confirmingCleanup = true;
    }

    public function cancelCleanup(): void
    {
        $this->confirmingCleanup = false;
    }

    public function cleanup(LibraryTransferCleanupService $cleanupService): void
    {
        if (! $this->confirmingCleanup) {
            return;
        }

        try {
            $cleanupService->cleanup();
        } catch (RuntimeException $exception) {
            $this->confirmingCleanup = false;
            $this->addError('cleanup', __($exception->getMessage()));

            if ($exception->getPrevious() !== null) {
                report($exception);
            }

            return;
        }

        $this->confirmingCleanup = false;
        $this->cleanupNotice = 'Transfer history cleaned up.';
    }

    public function resetConfirmDelaySeconds(): int
    {
        return 0;
    }

    public function render(LibraryTransferCleanupService $cleanupService, LibraryTransferService $transferService): View
    {
        $products = collect();

        if (in_array('works', $this->scopes, true) && $this->mode === 'selected') {
            $products = Product::query()
                ->when(trim($this->search) !== '', fn($query) => $query->whereAny(
                    ['id', 'work_name', 'work_name_english'],
                    'like',
                    '%' . trim($this->search) . '%',
                ))
                ->orderByNumericRj()
                ->get(['id', 'work_name', 'work_name_english']);
        }

        return view('livewire.options-transfers', [
            'products' => $products,
            'hasAnyProducts' => $products->isNotEmpty() || Product::query()->exists(),
            'latestImportRunId' => LibraryTransferRun::query()->where('direction', LibraryTransferDirection::Import)->latest('id')->value('id'),
            'latestExportRunId' => LibraryTransferRun::query()->where('direction', LibraryTransferDirection::Export)->latest('id')->value('id'),
            'cleanupUnavailable' => $cleanupService->unavailable(),
            'uploadMaxBytes' => $transferService->uploadLimitBytes(),
        ]);
    }
}
