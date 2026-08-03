<?php

namespace App\Livewire;

use App\Enums\RefetchCategory;
use App\Models\Genre;
use App\Models\Option;
use App\Models\RefetchRun;
use App\Models\RefetchWorkResult;
use App\Support\Refetch\RefetchService;
use App\Support\TagColor;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

class OptionsRefetchReview extends Component
{
    #[Locked]
    public int $runId;

    #[Locked]
    public bool $confirmingApplyAll = false;

    #[Locked]
    public ?string $confirmingApplyCategory = null;

    public string $activeCategory;

    /**
     * @var array<string, string>
     */
    public array $globalActions = [];

    /**
     * @var array<string, array<int|string, array<string, string>>>
     */
    public array $actions = [];

    /**
     * @var array<int|string, array<string, string>>
     */
    public array $tagActions = [];

    public function mount(RefetchRun $run): void
    {
        $this->runId = (int) $run->getKey();
        $run->loadMissing(['results.product']);

        $firstCategory = collect(RefetchCategory::cases())->first(
            fn(RefetchCategory $category): bool => ! $run->tabResolved($category)
                && $run->results->contains(
                    fn(RefetchWorkResult $result): bool => $result->hasChangesFor($category)
                )
        );

        $this->activeCategory = ($firstCategory ?? RefetchCategory::cases()[0])->value;
        $this->initializeChoices($run);
    }

    public function showCategory(string $category): void
    {
        if (RefetchCategory::tryFrom($category) !== null) {
            $this->activeCategory = $category;
        }
    }

    public function overwriteAll(): void
    {
        $run = $this->run();

        if (! $run->canBeApplied()) {
            return;
        }

        foreach (RefetchCategory::cases() as $category) {
            if (
                ! $run->tabResolved($category)
                && $run->results->contains(
                    fn(RefetchWorkResult $result): bool => $result->hasChangesFor($category)
                )
            ) {
                $this->globalActions[$category->value] = RefetchService::ACTION_OVERWRITE;
            }
        }
    }

    public function askApplyAll(): void
    {
        if (! $this->run()->canBeApplied()) {
            return;
        }

        $this->confirmingApplyCategory = null;
        $this->confirmingApplyAll = true;
    }

    public function askApplyTab(string $value): void
    {
        $category = RefetchCategory::tryFrom($value);

        if ($category === null) {
            $this->addError('category', __('The selected category is invalid.'));

            return;
        }

        if (! $this->run()->canBeApplied()) {
            return;
        }

        $this->confirmingApplyAll = false;
        $this->confirmingApplyCategory = $category->value;
    }

    public function cancelApplyConfirmation(): void
    {
        $this->confirmingApplyAll = false;
        $this->confirmingApplyCategory = null;
    }

    public function applyTab(RefetchService $service): void
    {
        $category = RefetchCategory::tryFrom((string) $this->confirmingApplyCategory);

        if ($category === null) {
            return;
        }

        $this->cancelApplyConfirmation();
        $validated = $this->validate();

        try {
            $service->applyTab(
                $this->run(),
                $category,
                $validated['globalActions'][$category->value] ?? RefetchService::ACTION_IGNORE,
                $validated['actions'][$category->value] ?? [],
                $validated['tagActions'] ?? [],
            );
        } catch (RuntimeException $exception) {
            $this->addError('run', __($exception->getMessage()));

            return;
        }
    }

    public function applyAll(RefetchService $service): void
    {
        if (! $this->confirmingApplyAll) {
            return;
        }

        $this->cancelApplyConfirmation();
        $validated = $this->validate();

        try {
            $service->applyAll(
                $this->run(),
                $validated['globalActions'],
                $validated['actions'],
                $validated['tagActions'] ?? [],
            );
        } catch (RuntimeException $exception) {
            $this->addError('run', __($exception->getMessage()));

            return;
        }

        $this->redirectRoute('options.refetch.show', ['run' => $this->runId]);
    }

    public function resetConfirmDelaySeconds(): int
    {
        return 0;
    }

    public function rejectOrFinish(RefetchService $service): void
    {
        try {
            $service->rejectOrFinish($this->run());
        } catch (RuntimeException $exception) {
            $this->addError('run', __($exception->getMessage()));

            return;
        }

        $this->redirectRoute('options.refetch.show', ['run' => $this->runId]);
    }

    public function render(): View
    {
        $run = $this->run();
        $tagColors = $this->refetchTagColors($run);

        return view('livewire.options-refetch-review', [
            'run' => $run,
            'canApply' => $run->canBeApplied(),
            'failedResults' => $run->results->filter->isFailed(),
            'categoryReviews' => $this->categoryReviews($run, $tagColors),
            'globalActionOptions' => $this->globalActionOptions(),
            'changeActionOptions' => $this->changeActionOptions(),
            'tagChangeActionOptions' => $this->tagChangeActionOptions(),
            'tagActionFields' => $this->tagActionFields(),
            'finishAction' => $this->finishAction($run),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'globalActions' => ['array'],
            'globalActions.*' => [Rule::in([
                RefetchService::ACTION_IGNORE,
                RefetchService::ACTION_OVERWRITE,
            ])],
            'actions' => ['array'],
            'actions.*' => ['array'],
            'actions.*.*' => ['array'],
            'actions.*.*.*' => [Rule::in([
                RefetchService::ACTION_INHERIT,
                RefetchService::ACTION_IGNORE,
                RefetchService::ACTION_OVERWRITE,
                RefetchService::ACTION_DETAILED,
            ])],
            'tagActions' => ['array'],
            'tagActions.*.added_japanese' => [Rule::in([
                RefetchService::TAG_ADDED_ADD,
                RefetchService::TAG_ADDED_IGNORE,
            ])],
            'tagActions.*.added_english' => [Rule::in([
                RefetchService::TAG_ADDED_ADD,
                RefetchService::TAG_ADDED_IGNORE,
            ])],
            'tagActions.*.stale_japanese' => [Rule::in([
                RefetchService::TAG_STALE_MOVE_TO_CUSTOM,
                RefetchService::TAG_STALE_REMOVE,
            ])],
            'tagActions.*.stale_english' => [Rule::in([
                RefetchService::TAG_STALE_MOVE_TO_CUSTOM,
                RefetchService::TAG_STALE_REMOVE,
            ])],
            'tagActions.*.custom_to_fetched' => [Rule::in([
                RefetchService::TAG_CUSTOM_PROMOTE,
                RefetchService::TAG_CUSTOM_KEEP,
            ])],
        ];
    }

    private function run(): RefetchRun
    {
        return RefetchRun::query()
            ->with(['results.product'])
            ->findOrFail($this->runId);
    }

    private function initializeChoices(RefetchRun $run): void
    {
        foreach (RefetchCategory::cases() as $category) {
            $this->globalActions[$category->value] = RefetchService::ACTION_IGNORE;

            foreach ($run->results as $result) {
                foreach ($result->changesFor($category) as $field => $change) {
                    $this->actions[$category->value][$result->getKey()][$field] = data_get(
                        $result->decisions,
                        "{$category->value}.{$field}.action",
                        RefetchService::ACTION_INHERIT,
                    );

                    if ($category === RefetchCategory::Tags) {
                        $savedTagActions = data_get(
                            $result->decisions,
                            "{$category->value}.{$field}.tag_actions",
                            [],
                        );

                        $this->tagActions[$result->getKey()] = array_replace(
                            $this->defaultTagActions(),
                            is_array($savedTagActions) ? $savedTagActions : [],
                        );
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $tagColors
     * @return list<array{
     *     value: string,
     *     label: string,
     *     count: int,
     *     resolved: bool,
     *     has_changes: bool,
     *     is_image: bool,
     *     is_tags: bool,
     *     results: list<array{
     *         id: int|string,
     *         product_id: string,
     *         work_name: ?string,
     *         changes: list<array{
     *             field: string,
     *             label: string,
     *             old: mixed,
     *             new: mixed,
     *             tag_details: list<array{label: string, tags: mixed}>
     *         }>
     *     }>
     * }>
     */
    private function categoryReviews(RefetchRun $run, array $tagColors): array
    {
        return collect(RefetchCategory::cases())
            ->map(function (RefetchCategory $category) use ($run, $tagColors): array {
                $results = $run->results
                    ->map(function (RefetchWorkResult $result) use ($category, $tagColors): array {
                        $colors = $category === RefetchCategory::Tags ? $tagColors : [];

                        $changes = collect($result->changesFor($category))
                            ->map(fn(array $change, string $field): array => [
                                'field' => $field,
                                'label' => (string) $change['label'],
                                'old' => $this->prepareReviewValue($change['old'], $category->isImage(), $colors),
                                'new' => $this->prepareReviewValue($change['new'], $category->isImage(), $colors),
                                'tag_details' => $category === RefetchCategory::Tags
                                    ? $this->tagDetails($change, $colors)
                                    : [],
                            ])
                            ->values()
                            ->all();

                        return [
                            'id' => $result->getKey(),
                            'product_id' => $result->product_id,
                            'work_name' => $result->product?->work_name,
                            'changes' => $changes,
                        ];
                    })
                    ->filter(fn(array $result): bool => $result['changes'] !== [])
                    ->values()
                    ->all();

                return [
                    'value' => $category->value,
                    'label' => $category->label(),
                    'count' => collect($results)->sum(
                        fn(array $result): int => count($result['changes'])
                    ),
                    'resolved' => $run->tabResolved($category),
                    'has_changes' => $results !== [],
                    'is_image' => $category->isImage(),
                    'is_tags' => $category === RefetchCategory::Tags,
                    'results' => $results,
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $colors
     * @return list<array{label: string, tags: mixed}>
     */
    private function tagDetails(array $change, array $colors): array
    {
        return [
            [
                'label' => 'New JP',
                'tags' => $this->prepareReviewValue(data_get($change, 'details.added_japanese', []), false, $colors),
            ],
            [
                'label' => 'New EN',
                'tags' => $this->prepareReviewValue(data_get($change, 'details.added_english', []), false, $colors),
            ],
            [
                'label' => 'Stale JP',
                'tags' => $this->prepareReviewValue(data_get($change, 'details.stale_japanese', []), false, $colors),
            ],
            [
                'label' => 'Stale EN',
                'tags' => $this->prepareReviewValue(data_get($change, 'details.stale_english', []), false, $colors),
            ],
            [
                'label' => 'Custom->Fetched JP',
                'tags' => $this->prepareReviewValue(
                    data_get($change, 'details.custom_to_fetched_japanese', []),
                    false,
                    $colors,
                ),
            ],
            [
                'label' => 'Custom->Fetched EN',
                'tags' => $this->prepareReviewValue(
                    data_get($change, 'details.custom_to_fetched_english', []),
                    false,
                    $colors,
                ),
            ],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $colors
     */
    private function prepareReviewValue(mixed $value, bool $image, array $colors): mixed
    {
        if ($image || ! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            return collect($value)
                ->map(fn(mixed $items): mixed => $this->prepareReviewValue($items, false, $colors))
                ->all();
        }

        return collect($value)
            ->map(fn(mixed $item): array => [
                'value' => $item,
                'colors' => $colors[(string) $item] ?? [],
            ])
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function globalActionOptions(): array
    {
        return [
            ['value' => RefetchService::ACTION_IGNORE, 'label' => 'Ignore'],
            ['value' => RefetchService::ACTION_OVERWRITE, 'label' => 'Overwrite'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function changeActionOptions(): array
    {
        return [
            ['value' => RefetchService::ACTION_INHERIT, 'label' => 'Use global choice'],
            ['value' => RefetchService::ACTION_OVERWRITE, 'label' => 'Overwrite'],
            ['value' => RefetchService::ACTION_IGNORE, 'label' => 'Ignore'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function tagChangeActionOptions(): array
    {
        return [
            ['value' => RefetchService::ACTION_INHERIT, 'label' => 'Use global choice'],
            ['value' => RefetchService::ACTION_OVERWRITE, 'label' => 'Overwrite'],
            ['value' => RefetchService::ACTION_DETAILED, 'label' => 'Detailed choices'],
            ['value' => RefetchService::ACTION_IGNORE, 'label' => 'Ignore'],
        ];
    }

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     options: list<array{value: string, label: string}>
     * }>
     */
    private function tagActionFields(): array
    {
        return [
            [
                'key' => 'added_japanese',
                'label' => 'New JP',
                'options' => [
                    ['value' => RefetchService::TAG_ADDED_ADD, 'label' => 'Add as fetched'],
                    ['value' => RefetchService::TAG_ADDED_IGNORE, 'label' => 'Ignore'],
                ],
            ],
            [
                'key' => 'added_english',
                'label' => 'New EN',
                'options' => [
                    ['value' => RefetchService::TAG_ADDED_ADD, 'label' => 'Add as fetched'],
                    ['value' => RefetchService::TAG_ADDED_IGNORE, 'label' => 'Ignore'],
                ],
            ],
            [
                'key' => 'stale_japanese',
                'label' => 'Stale JP',
                'options' => [
                    ['value' => RefetchService::TAG_STALE_MOVE_TO_CUSTOM, 'label' => 'Move to custom tags'],
                    ['value' => RefetchService::TAG_STALE_REMOVE, 'label' => 'Remove'],
                ],
            ],
            [
                'key' => 'stale_english',
                'label' => 'Stale EN',
                'options' => [
                    ['value' => RefetchService::TAG_STALE_MOVE_TO_CUSTOM, 'label' => 'Move to custom tags'],
                    ['value' => RefetchService::TAG_STALE_REMOVE, 'label' => 'Remove'],
                ],
            ],
            [
                'key' => 'custom_to_fetched',
                'label' => 'Custom->Fetched',
                'options' => [
                    ['value' => RefetchService::TAG_CUSTOM_PROMOTE, 'label' => 'Promote to fetched'],
                    ['value' => RefetchService::TAG_CUSTOM_KEEP, 'label' => 'Keep custom'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function defaultTagActions(): array
    {
        return [
            'added_japanese' => RefetchService::TAG_ADDED_ADD,
            'added_english' => RefetchService::TAG_ADDED_ADD,
            'stale_japanese' => RefetchService::TAG_STALE_MOVE_TO_CUSTOM,
            'stale_english' => RefetchService::TAG_STALE_MOVE_TO_CUSTOM,
            'custom_to_fetched' => RefetchService::TAG_CUSTOM_PROMOTE,
        ];
    }

    /**
     * @return array{label: string, confirmation: string}
     */
    private function finishAction(RefetchRun $run): array
    {
        return $run->hasAppliedDecisions()
            ? [
                'label' => 'Ignore Remaining and Finish',
                'confirmation' => 'Ignore every unresolved tab and finish this run?',
            ]
            : [
                'label' => 'Reject Run',
                'confirmation' => 'Reject this refetch run?',
            ];
    }

    /**
     * @return array<string, array{color: ?string, text_color: ?string, color_style: string, has_background_color: bool, has_font_color: bool}>
     */
    private function refetchTagColors(RefetchRun $run): array
    {
        if (! Option::tagColorSurfaceEnabled(Option::TAG_COLOR_SURFACE_REFETCH)) {
            return [];
        }

        $titles = $run->results
            ->flatMap(fn(RefetchWorkResult $result) => collect($result->changesFor(RefetchCategory::Tags))
                ->flatMap(fn(array $change): array => [
                    ...data_get($change, 'old.japanese', []),
                    ...data_get($change, 'old.english', []),
                    ...data_get($change, 'old.custom', []),
                    ...data_get($change, 'new.japanese', []),
                    ...data_get($change, 'new.english', []),
                ]))
            ->map(fn(mixed $title): string => trim((string) $title))
            ->filter()
            ->unique()
            ->values();
        $pairs = TagColor::effectiveColorPairsForTitleKeys(
            $titles->map(fn(string $title): string => Genre::titleKey($title))
        );

        return $titles
            ->mapWithKeys(function (string $title) use ($pairs): array {
                $colors = $pairs->get(Genre::titleKey($title), TagColor::pair(null, null));

                return [$title => TagColor::viewData($colors['color'], $colors['text_color'])];
            })
            ->all();
    }
}
