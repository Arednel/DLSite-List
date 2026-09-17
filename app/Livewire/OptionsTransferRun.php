<?php

namespace App\Livewire;

use App\Enums\LibraryImportDecision;
use App\Enums\LibraryImportItemStatus;
use App\Enums\LibraryTransferAction;
use App\Enums\LibraryTransferDirection;
use App\Enums\LibraryTransferPartStatus;
use App\Enums\LibraryTransferRunStatus;
use App\Models\LibraryImportItem;
use App\Models\LibraryTransferRun;
use App\Models\Option;
use App\Support\Transfers\ImportReview;
use App\Support\Transfers\ImportValue;
use App\Support\Transfers\LibraryTransferService;
use App\Support\Transfers\TransferArchive;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;
use Throwable;

class OptionsTransferRun extends Component
{
    use WithPagination;

    private const REVIEW_PAGE_SIZE = 100;

    #[Locked]
    public int $runId;

    #[Locked]
    public ?string $confirmation = null;

    #[Locked]
    public ?string $confirmationSection = null;

    #[Locked]
    public ?string $confirmationCategory = null;

    /** @var array{section: string, category: string}|null */
    #[Locked]
    public ?array $pendingTabAdvance = null;

    public string $section = 'works';

    public string $category = 'new_works';

    #[Locked]
    public string $newWorkCategory = 'titles';

    #[Locked]
    public string $displayedStatus;

    #[Locked]
    public string $initialLocale;

    public function mount(LibraryTransferRun $run): void
    {
        $this->runId = $run->id;
        $this->displayedStatus = $run->status->value;
        $this->initialLocale = Option::uiLanguage()->value;
        $first = $run->items()->orderBy('id')->first(['section', 'category']);
        if ($first) {
            $this->section = $first->section;
            $this->category = $first->category;
        }
    }

    #[Computed]
    public function run(): LibraryTransferRun
    {
        return LibraryTransferRun::findOrFail($this->runId);
    }

    public function tab(string $section, string $category): void
    {
        abort_unless(in_array($category, ImportReview::CATEGORIES[$section] ?? [], true), 422);
        $this->section = $section;
        $this->category = $category;
        $this->resetPage();
        unset($this->reviewData);
    }

    /** @return array<string, array{label: string, section: string, categories: list<string>}> */
    private function mainTabs(): array
    {
        return [
            'new_works' => [
                'label' => __('New works'),
                'section' => 'works',
                'categories' => ['new_works'],
            ],
            'works' => [
                'label' => __('Works'),
                'section' => 'works',
                'categories' => array_values(array_diff(ImportReview::CATEGORIES['works'], ['new_works'])),
            ],
            'tag-library' => [
                'label' => __('Tag Library'),
                'section' => 'tag-library',
                'categories' => ImportReview::CATEGORIES['tag-library'],
            ],
            'options' => [
                'label' => __('Options'),
                'section' => 'options',
                'categories' => ImportReview::CATEGORIES['options'],
            ],
        ];
    }

    private function activeMainTabKey(): string
    {
        return $this->section === 'works' && $this->category === 'new_works'
            ? 'new_works'
            : $this->section;
    }

    public function mainTab(string $mainTab): void
    {
        if ($mainTab === 'new_works') {
            $this->tab('works', 'new_works');

            return;
        }

        $tab = $this->mainTabs()[$mainTab] ?? null;
        abort_unless($tab !== null, 422);

        $available = LibraryImportItem::query()->where('library_transfer_run_id', $this->runId)
            ->where('section', $tab['section'])
            ->whereIn('category', $tab['categories'])
            ->distinct()->pluck('category')->flip();
        foreach ($tab['categories'] as $category) {
            if ($available->has($category)) {
                $this->tab($tab['section'], $category);

                return;
            }
        }
    }

    public function pollProgress(): void
    {
        unset($this->run, $this->progressPolling, $this->progressData, $this->progressPercent);
        $run = $this->run;
        if ($run->direction === LibraryTransferDirection::Import) {
            $this->dispatch('transfer-status', runId: $run->id, status: $run->status->value, accepting: $run->acceptsNewParts());
        }
        if ($this->displayedStatus !== $run->status->value) {
            $this->displayedStatus = $run->status->value;
            $this->js('$wire.$refresh()');
        }
    }

    #[Computed]
    public function progressPolling(): bool
    {
        $run = $this->run;

        return $run->busy() || ($run->acceptsNewParts() && $run->parts()->awaitingInspection()->exists());
    }

    #[Computed]
    public function progressData(): array
    {
        $run = $this->run;
        if ($run->direction === LibraryTransferDirection::Export && in_array('works', $run->settings['scopes'] ?? [], true)) {
            $workEntries = $run->entries()->where('section', 'works')->whereNotNull('logical_key');
            $planned = (clone $workEntries)->distinct()->count('logical_key');
            $planning = in_array($run->status, [LibraryTransferRunStatus::Queued, LibraryTransferRunStatus::Planning], true);
            $total = $planning ? count($run->settings['product_ids'] ?? []) : $planned;
            $processed = 0;

            if ($run->status === LibraryTransferRunStatus::Ready) {
                $processed = $planned;
            } elseif ($run->status !== LibraryTransferRunStatus::Queued && $run->status !== LibraryTransferRunStatus::Planning) {
                $incomplete = $run->entries()
                    ->whereNotNull('logical_key')
                    ->where(fn($query) => $query->whereNull('library_transfer_part_id')
                        ->orWhereRelation('part', 'status', '<>', LibraryTransferPartStatus::Ready))
                    ->distinct()
                    ->pluck('logical_key');
                $processed = (clone $workEntries)
                    ->whereRelation('part', 'status', LibraryTransferPartStatus::Ready)
                    ->whereNotIn('logical_key', $incomplete)
                    ->distinct()
                    ->count('logical_key');
            }

            return [
                'processed' => $processed,
                'total' => $total,
                'description' => trans_choice(
                    ':processed of :total work exported|:processed of :total works exported',
                    $total,
                    compact('processed', 'total'),
                ),
            ];
        }

        $description = $run->stage ?? 'Waiting for queue worker';
        if ($run->direction === LibraryTransferDirection::Import && $run->acceptsNewParts()) {
            $pendingPart = $run->parts()->awaitingInspection()->orderBy('id')->first(['kind', 'number']);
            $description = $pendingPart
                ? __('Validating :kind part :number', ['kind' => $pendingPart->kind, 'number' => $pendingPart->number])
                : __('Waiting for ZIP files');
        }

        return [
            'processed' => $run->processed,
            'total' => $run->total,
            'description' => __($description),
        ];
    }

    #[Computed]
    public function progressPercent(): int
    {
        $progress = $this->progressData;

        return $progress['total'] > 0
            ? min(100, max(0, (int) round(($progress['processed'] / $progress['total']) * 100)))
            : 0;
    }

    public function newWorkTab(string $category): void
    {
        abort_unless(in_array($category, array_slice(ImportReview::CATEGORIES['works'], 1), true), 422);
        $this->newWorkCategory = $category;
        $this->resetPage();
        unset($this->reviewData);
    }

    public function decide(int $id, string $decision, ImportReview $review): void
    {
        $choice = $decision === 'inherit' ? null : LibraryImportDecision::tryFrom($decision);
        abort_unless($decision === 'inherit' || $choice !== null, 422);

        $this->updateChoices(fn() => $review->setItemDecision($this->runId, $id, $choice));
    }

    public function decideMany(ImportReview $review, string $decision, bool $all = false): void
    {
        $choice = LibraryImportDecision::tryFrom($decision);
        abort_unless($choice !== null, 422);
        abort_unless(in_array($this->category, ImportReview::CATEGORIES[$this->section] ?? [], true), 422);

        $this->updateChoices(fn() => $review->setDefaultDecision(
            $this->runId,
            $choice,
            $all ? null : $this->section,
            $all ? null : $this->category,
        ));
    }

    private function updateChoices(Closure $callback): void
    {
        try {
            $callback();
            unset($this->run, $this->reviewData, $this->confirmationMessage);
        } catch (LockTimeoutException | RuntimeException $exception) {
            $this->addError('transfer', $exception->getMessage() ?: __('A transfer operation is in progress. Retry shortly.'));
        }
    }

    public function ask(string $action, bool $all = false): void
    {
        $choice = LibraryTransferAction::tryFrom($action);
        abort_unless($choice !== null, 422);
        $this->confirmation = $choice->value;
        $this->confirmationSection = $choice === LibraryTransferAction::Apply && ! $all ? $this->section : null;
        $this->confirmationCategory = $choice === LibraryTransferAction::Apply && ! $all ? $this->category : null;
        unset($this->confirmationMessage);
    }

    public function cancelConfirmation(): void
    {
        $this->confirmation = null;
        $this->confirmationSection = null;
        $this->confirmationCategory = null;
        unset($this->confirmationMessage);
    }

    public function confirm(LibraryTransferService $service): void
    {
        if ($this->confirmation === null) {
            return;
        }
        $action = LibraryTransferAction::from($this->confirmation);
        $section = $this->confirmationSection;
        $category = $this->confirmationCategory;
        $this->confirmation = null;
        try {
            $service->action($this->run, $action, $section, $category);
            $this->pendingTabAdvance = $action === LibraryTransferAction::Apply && $section !== null && $category !== null
                ? compact('section', 'category')
                : null;
            $this->confirmationSection = null;
            $this->confirmationCategory = null;
            unset(
                $this->run,
                $this->progressPolling,
                $this->progressPercent,
                $this->partData,
                $this->reviewData,
                $this->confirmationMessage,
            );
            if ($action === LibraryTransferAction::Cancel) {
                $this->dispatch('transfer-cancelled', runId: $this->runId);
            }
            if ($action === LibraryTransferAction::Cleanup) {
                $this->redirectRoute('options.index', ['tab' => 'transfers']);
            }
        } catch (Throwable $exception) {
            $this->addError('transfer', $exception->getMessage() ?: __('A transfer operation is in progress. Retry shortly.'));
        }
    }

    public function resetConfirmDelaySeconds(): int
    {
        return 0;
    }

    #[Computed]
    public function reviewData(): ?array
    {
        $run = $this->run;
        if (! $run->reviewVisible()) {
            return null;
        }
        if ($this->pendingTabAdvance !== null) {
            $this->advanceAfterAppliedTab(
                $run,
                $this->pendingTabAdvance['section'],
                $this->pendingTabAdvance['category'],
            );
            $this->pendingTabAdvance = null;
        }

        $categories = ImportReview::CATEGORIES;
        $counts = $run->items()->selectRaw('section, category, count(*) as total')->groupBy('section', 'category')->get()->keyBy(fn($row) => $row->section . '.' . $row->category);
        if ($counts->isNotEmpty() && ! isset($counts[$this->section . '.' . $this->category])) {
            $mainTabs = $this->mainTabs();
            $mainTabOrder = array_values(array_unique([$this->activeMainTabKey(), ...array_keys($mainTabs)]));
            foreach ($mainTabOrder as $mainTab) {
                foreach ($mainTabs[$mainTab]['categories'] ?? [] as $category) {
                    $section = $mainTabs[$mainTab]['section'] ?? null;
                    if ($section !== null && isset($counts[$section . '.' . $category])) {
                        $this->section = $section;
                        $this->category = $category;
                        break 2;
                    }
                }
            }
            $this->resetPage();
        }
        $itemQuery = $run->items()->forSectionCategory($this->section, $this->category);
        if ($this->category === 'new_works') {
            $paths = ImportValue::newWorkJsonPaths($this->newWorkCategory);
            $itemQuery->where(function ($query) use ($paths): void {
                foreach ($paths as $path) {
                    $query->orWhereJsonContainsKey($path);
                }
                if ($this->newWorkCategory === 'titles') {
                    $query->orWhereNotNull('error');
                }
            });
        }
        $items = $itemQuery->orderByDesc('collection')->orderBy('id')
            ->paginate(self::REVIEW_PAGE_SIZE, ['id', 'library_transfer_run_id', 'section', 'category', 'entity_key', 'status', 'decision', 'decision_override', 'metadata', 'error', 'baseline_preview', 'incoming_preview']);
        $reviewItems = $items->getCollection();
        $labels = collect(array_keys($categories))
            ->merge(collect($categories)->flatten())
            ->merge($reviewItems->flatMap(fn(LibraryImportItem $item): array => [
                $item->entity_key,
                $item->metadata['field'] ?? null,
            ]))
            ->filter(fn($key): bool => is_string($key) && $key !== '')
            ->unique()
            ->mapWithKeys(fn(string $key): array => [$key => ImportValue::label($key)])
            ->all();
        $newWorkChanges = $reviewItems
            ->mapWithKeys(fn(LibraryImportItem $item): array => [
                $item->id => ImportValue::newWorkChanges($item, $this->newWorkCategory),
            ])
            ->all();
        $previews = $reviewItems
            ->mapWithKeys(fn(LibraryImportItem $item): array => [
                $item->id => [
                    'Current' => $item->baseline_preview,
                    'Imported' => $item->incoming_preview,
                ],
            ])
            ->all();

        $newWorkCounts = $this->category === 'new_works'
            ? $this->newWorkCategoryCounts($run, array_slice($categories['works'], 1))
            : [];
        $decisionOptions = ['ignore' => 'Ignore', 'overwrite' => 'Overwrite', 'merge' => 'Merge'];
        $decisionHelp = [
            'ignore' => __('Ignore keeps current library data and skips the imported changes.'),
            'overwrite' => __('Overwrite replaces values included in the archive. For Options, absent keys reset to defaults. For group lists, groups absent from the archive are deleted while their tags and work attachments remain.'),
            'merge' => __('Merge fills empty work fields and adds imported tags, contributors, and images without removing existing ones. For Options, keys absent from the archive keep their current values.'),
        ];
        $mainTabs = $this->mainTabs();
        $availableMainTabs = collect($mainTabs)
            ->filter(fn(array $tab): bool => collect($tab['categories'])
                ->contains(fn(string $category): bool => isset($counts[$tab['section'] . '.' . $category])))
            ->keys()
            ->all();

        return compact('counts', 'items', 'labels', 'newWorkChanges', 'newWorkCounts', 'previews', 'decisionOptions', 'decisionHelp') + [
            'section' => $this->section,
            'category' => $this->category,
            'tabDecisionOptions' => $this->category === 'new_works'
                ? ['ignore' => 'Ignore', 'merge' => 'Add']
                : $decisionOptions,
            'availableMainTabs' => $availableMainTabs,
        ];
    }

    private function advanceAfterAppliedTab(LibraryTransferRun $run, string $section, string $category): void
    {
        $tabs = [];
        foreach ($this->mainTabs() as $mainTab) {
            foreach ($mainTab['categories'] as $tabCategory) {
                $tabs[] = ['section' => $mainTab['section'], 'category' => $tabCategory];
            }
        }

        $current = array_search(['section' => $section, 'category' => $category], $tabs, true);
        if ($current === false) {
            return;
        }

        $unresolved = $run->items()
            ->whereIn('status', [
                LibraryImportItemStatus::Pending,
                LibraryImportItemStatus::Conflict,
                LibraryImportItemStatus::Failed,
            ])
            ->select(['section', 'category'])
            ->distinct()
            ->get()
            ->mapWithKeys(fn(LibraryImportItem $item): array => [$item->section . '.' . $item->category => true]);
        $nextTabs = [...array_slice($tabs, $current + 1), ...array_slice($tabs, 0, $current)];
        foreach ($nextTabs as $tab) {
            if ($unresolved->has($tab['section'] . '.' . $tab['category'])) {
                $this->section = $tab['section'];
                $this->category = $tab['category'];
                $this->resetPage();
                $this->js("document.getElementById('transfer-review-panel')?.scrollIntoView({ block: 'start' })");

                return;
            }
        }
    }

    /** @param list<string> $categories */
    private function newWorkCategoryCounts(LibraryTransferRun $run, array $categories): array
    {
        $expressions = [];
        $bindings = [];
        foreach ($categories as $category) {
            $conditions = [];
            foreach (ImportValue::newWorkJsonPaths($category) as $path) {
                $segments = array_slice(explode('->', $path), 1);
                $conditions[] = "JSON_CONTAINS_PATH(incoming, 'one', ?) = 1";
                $bindings[] = '$."' . implode('"."', $segments) . '"';
            }
            if ($category === 'titles') {
                $conditions[] = 'error IS NOT NULL';
            }
            $expressions[] = 'SUM(CASE WHEN (' . implode(' OR ', $conditions) . ") THEN 1 ELSE 0 END) AS {$category}";
        }

        $totals = $run->items()
            ->where('section', 'works')
            ->where('category', 'new_works')
            ->selectRaw(implode(', ', $expressions), $bindings)
            ->first();

        return collect($categories)
            ->mapWithKeys(fn(string $category): array => [$category => (int) $totals?->getAttribute($category)])
            ->all();
    }

    #[Computed]
    public function partData(): array
    {
        $run = $this->run;
        $polling = $this->progressPolling;
        $showParts = $run->acceptsNewParts()
            || (! $polling && in_array($run->status, [LibraryTransferRunStatus::AwaitingConfirmation, LibraryTransferRunStatus::Ready, LibraryTransferRunStatus::Failed], true));
        $partCounts = ($showParts || $run->status === LibraryTransferRunStatus::AwaitingConfirmation)
            ? $run->parts()->selectRaw('kind, count(*) as total, sum(bytes) as bytes')->groupBy('kind')->get()->keyBy('kind')
            : collect();
        $expected = [];
        foreach (['data', 'images'] as $kind) {
            $expected[$kind] = $run->settings['parts'][$kind] ?? (int) ($partCounts[$kind]->total ?? 0);
        }
        $totalParts = $showParts ? array_sum($expected) : 0;
        $slots = Collection::times($totalParts, function (int $position) use ($expected, $run): array {
            $index = $position - 1;
            $kind = $index < $expected['data'] ? 'data' : 'images';
            $number = $kind === 'data' ? $position : $position - $expected['data'];

            return [
                'kind' => $kind,
                'number' => $number,
                'filename' => isset($run->settings['manifest']['exported_at'])
                    ? TransferArchive::filename($run->settings['manifest']['exported_at'], $kind, $number, $expected[$kind])
                    : null,
            ];
        });
        $parts = $slots->isEmpty() ? collect() : $run->parts()
            ->get(['id', 'kind', 'number', 'filename', 'status', 'bytes', 'error', 'candidate'])
            ->keyBy(fn($part) => $part->kind . '.' . $part->number);
        $partSlots = $slots->map(fn(array $slot): array => [
            ...$slot,
            'part' => $parts->get($slot['kind'] . '.' . $slot['number']),
        ]);
        $downloadParts = $run->direction === LibraryTransferDirection::Export && $run->status === LibraryTransferRunStatus::Ready
            ? $run->parts()->where('status', LibraryTransferPartStatus::Ready)->orderBy('kind')->orderBy('number')->get(['id', 'library_transfer_run_id', 'filename'])
            : collect();
        $needsParts = $run->acceptsNewParts()
            && (! isset($run->settings['manifest']) || $run->parts()->where('status', LibraryTransferPartStatus::Invalid)->whereNull('candidate')->exists()
                || $run->parts()->count() < array_sum($expected));

        $service = app(LibraryTransferService::class);

        return compact('partCounts', 'expected', 'downloadParts', 'partSlots', 'needsParts') + [
            'dataReady' => $run->status === LibraryTransferRunStatus::WaitingForParts && $service->dataReady($run),
            'uploadMaxBytes' => $needsParts ? $service->uploadLimitBytes() : null,
        ];
    }

    #[Computed]
    public function confirmationMessage(): string
    {
        $run = $this->run;

        return match ($this->confirmation) {
            'cleanup' => __('Remove this transfer history, archives and staged files? Saved library data is retained.'),
            'without_images' => __('Continue with data only? All images will be excluded from this import, including uploaded image parts.'),
            'continue_export' => __('Build :count individual ZIP files?', ['count' => $run->total]),
            'apply' => $this->confirmationSection === null
                ? __('Apply choices for every unresolved tab?')
                : __('Apply and resolve this tab?'),
            'retry' => __('Retry the interrupted background operation from its saved checkpoint?'),
            'refresh' => __('Refresh failed/conflicting changes against current local data and reset their choices to Ignore?'),
            default => $run->direction->isImport()
                ? __('Cancel this Import? Already applied changes are retained.')
                : __('Cancel this Export? Already applied changes are retained.'),
        };
    }

    public function render()
    {
        if ($this->initialLocale !== Option::uiLanguage()->value) {
            $this->redirectRoute('options.transfers.show', ['run' => $this->runId]);
        }

        $run = $this->run;
        $partData = $this->partData;
        $reviewData = $this->reviewData;

        return view('livewire.options-transfer-run', [
            'run' => $run,
            'partCounts' => $partData['partCounts'],
            'expected' => $partData['expected'],
            'downloadParts' => $partData['downloadParts'],
            'partSlots' => $partData['partSlots'],
            'needsParts' => $partData['needsParts'],
            'dataReady' => $partData['dataReady'],
            'uploadMaxBytes' => $partData['uploadMaxBytes'],
            'counts' => $reviewData['counts'] ?? null,
            'items' => $reviewData['items'] ?? null,
            'labels' => $reviewData['labels'] ?? null,
            'newWorkChanges' => $reviewData['newWorkChanges'] ?? null,
            'newWorkCounts' => $reviewData['newWorkCounts'] ?? [],
            'previews' => $reviewData['previews'] ?? null,
            'section' => $reviewData['section'] ?? $this->section,
            'category' => $reviewData['category'] ?? $this->category,
            'decisionOptions' => $reviewData['decisionOptions'] ?? null,
            'decisionHelp' => $reviewData['decisionHelp'] ?? [],
            'tabDecisionOptions' => $reviewData['tabDecisionOptions'] ?? null,
            'mainTabs' => $this->mainTabs(),
            'activeMainTab' => $this->activeMainTabKey(),
            'availableMainTabs' => $reviewData['availableMainTabs'] ?? [],
            'categories' => ImportReview::CATEGORIES,
            'newWorkCategories' => array_slice(ImportReview::CATEGORIES['works'], 1),
        ]);
    }
}
