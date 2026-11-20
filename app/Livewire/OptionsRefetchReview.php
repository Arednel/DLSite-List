<?php

namespace App\Livewire;

use App\Enums\DlsiteLanguage;
use App\Enums\ProductFormat;
use App\Enums\RefetchCategory;
use App\Models\Genre;
use App\Models\Option;
use App\Models\RefetchRun;
use App\Models\RefetchWorkResult;
use App\Support\DLSite\DLSiteRating;
use App\Support\ProductImageMessage;
use App\Support\Refetch\RefetchService;
use App\Support\LocalizedMetadataField;
use App\Support\TagColor;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

class OptionsRefetchReview extends Component
{
    use WithPagination;

    private const REVIEW_PAGE_SIZE = 100;

    #[Locked]
    public int $runId;

    #[Locked]
    public bool $confirmingApplyAll = false;

    #[Locked]
    public ?string $confirmingApplyCategory = null;

    #[Locked]
    public bool $confirmingRejectOrFinish = false;

    #[Locked]
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
     * @var array<int|string, array<string, mixed>>
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
            $this->resetPage();
            $this->js("document.getElementById('refetch-tab-{$this->runId}-{$category}')?.scrollIntoView({ block: 'nearest', inline: 'nearest' })");
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

        $this->cancelConfirmation();
        $this->confirmingApplyAll = true;
    }

    public function askApplyTab(string $value): void
    {
        $category = RefetchCategory::tryFrom($value);

        if ($category === null) {
            $this->addError('category', __('errors.refetch.review.invalid_category'));

            return;
        }

        if (! $this->run()->canBeApplied()) {
            return;
        }

        $this->cancelConfirmation();
        $this->confirmingApplyCategory = $category->value;
    }

    public function askRejectOrFinish(): void
    {
        if (! $this->run()->canBeApplied()) {
            return;
        }

        $this->cancelConfirmation();
        $this->confirmingRejectOrFinish = true;
    }

    public function cancelConfirmation(): void
    {
        $this->confirmingApplyAll = false;
        $this->confirmingApplyCategory = null;
        $this->confirmingRejectOrFinish = false;
    }

    public function applyTab(RefetchService $service): void
    {
        $category = RefetchCategory::tryFrom((string) $this->confirmingApplyCategory);

        if ($category === null) {
            return;
        }

        $this->cancelConfirmation();
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
            $this->addError('run', ProductImageMessage::display($exception->getMessage()));

            return;
        }
    }

    public function applyAll(RefetchService $service): void
    {
        if (! $this->confirmingApplyAll) {
            return;
        }

        $this->cancelConfirmation();
        $validated = $this->validate();

        try {
            $service->applyAll(
                $this->run(),
                $validated['globalActions'],
                $validated['actions'],
                $validated['tagActions'] ?? [],
            );
        } catch (RuntimeException $exception) {
            $this->addError('run', ProductImageMessage::display($exception->getMessage()));

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
        if (! $this->confirmingRejectOrFinish) {
            return;
        }

        $this->cancelConfirmation();

        try {
            $service->rejectOrFinish($this->run());
        } catch (RuntimeException $exception) {
            $this->addError('run', ProductImageMessage::display($exception->getMessage()));

            return;
        }

        $this->redirectRoute('options.refetch.show', ['run' => $this->runId]);
    }

    public function render(): View
    {
        $run = $this->run();
        $categoryReviews = $this->categoryReviews($run);
        $activeCategory = RefetchCategory::tryFrom($this->activeCategory)
            ?? RefetchCategory::cases()[0];
        $tagColors = $activeCategory === RefetchCategory::Tags
            ? $this->refetchTagColors($run)
            : [];

        return view('livewire.options-refetch-review', [
            'run' => $run,
            'canApply' => $run->canBeApplied(),
            'categoryReviews' => $categoryReviews,
            'activeReview' => $this->activeCategoryReview($run, $categoryReviews, $tagColors),
            'globalActionOptions' => $this->globalActionOptions(),
            'changeActionOptions' => $this->changeActionOptions(),
            'tagChangeActionOptions' => $this->tagChangeActionOptions(),
            'tagAddedActionOptions' => $this->tagAddedActionOptions(),
            'tagStaleActionOptions' => $this->tagStaleActionOptions(),
            'tagCustomActionOptions' => $this->tagCustomActionOptions(),
            'finishAction' => $this->finishAction($run),
        ])->withErrors($this->displayErrors($run));
    }

    /**
     * @return array<string, list<string>>
     */
    private function displayErrors(RefetchRun $run): array
    {
        $errors = $this->getErrorBag()->toArray();

        foreach ($run->results as $result) {
            if ($result->isFailed()) {
                $message = $result->displayError();

                if ($message !== null && $message !== '') {
                    $errors['review'][] = "{$result->product_id}: {$message}";
                }
            }

            foreach ($result->warnings ?? [] as $warning) {
                $key = (string) ($warning['key'] ?? '');
                $replace = is_array($warning['replace'] ?? null) ? $warning['replace'] : [];
                $message = $key !== '' ? __('errors.' . $key, $replace) : '';

                if (is_string($message) && $message !== '') {
                    $errors['review'][] = "{$result->product_id}: {$message}";
                }
            }
        }

        return $errors;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        $rules = [
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
            'tagActions.*.locales' => ['array'],
            'tagActions.*.locales.*.added' => [Rule::in([
                RefetchService::TAG_ADDED_ADD,
                RefetchService::TAG_ADDED_IGNORE,
            ])],
            'tagActions.*.locales.*.stale' => [Rule::in([
                RefetchService::TAG_STALE_MOVE_TO_CUSTOM,
                RefetchService::TAG_STALE_REMOVE,
            ])],
            'tagActions.*.custom_to_fetched' => [Rule::in([
                RefetchService::TAG_CUSTOM_PROMOTE,
                RefetchService::TAG_CUSTOM_KEEP,
            ])],
        ];

        return $rules;
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
                    if ($category === RefetchCategory::Tags) {
                        $saved = data_get($result->decisions, "{$category->value}.{$field}", []);

                        foreach ($result->changedTagLocales() as $locale) {
                            $this->actions[$category->value][$result->getKey()][$locale] = data_get(
                                $saved,
                                "locales.{$locale}.action",
                                RefetchService::ACTION_INHERIT,
                            );
                            $this->tagActions[$result->getKey()]['locales'][$locale] = [
                                'added' => data_get(
                                    $saved,
                                    "tag_actions.locales.{$locale}.added",
                                    RefetchService::TAG_ADDED_ADD,
                                ),
                                'stale' => data_get(
                                    $saved,
                                    "tag_actions.locales.{$locale}.stale",
                                    RefetchService::TAG_STALE_MOVE_TO_CUSTOM,
                                ),
                            ];
                        }

                        $this->tagActions[$result->getKey()]['custom_to_fetched'] = data_get(
                            $saved,
                            'tag_actions.custom_to_fetched',
                            RefetchService::TAG_CUSTOM_PROMOTE,
                        );

                        continue;
                    }

                    $this->actions[$category->value][$result->getKey()][$field] = data_get(
                        $result->decisions,
                        "{$category->value}.{$field}.action",
                        RefetchService::ACTION_INHERIT,
                    );
                }
            }
        }
    }

    /**
     * @return list<array{
     *     value: string,
     *     label: string,
     *     count: int,
     *     resolved: bool,
     *     has_changes: bool,
     *     is_image: bool,
     *     is_tags: bool,
     * }>
     */
    private function categoryReviews(RefetchRun $run): array
    {
        return collect(RefetchCategory::cases())
            ->map(function (RefetchCategory $category) use ($run): array {
                $count = $run->results->sum(
                    fn(RefetchWorkResult $result): int => count($result->changesFor($category))
                );

                return [
                    'value' => $category->value,
                    'label' => $category->label(),
                    'count' => $count,
                    'resolved' => $run->tabResolved($category),
                    'has_changes' => $count > 0,
                    'is_image' => $category->isImage(),
                    'is_tags' => $category === RefetchCategory::Tags,
                ];
            })
            ->all();
    }

    /**
     * @param  list<array{
     *     value: string,
     *     label: string,
     *     count: int,
     *     resolved: bool,
     *     has_changes: bool,
     *     is_image: bool,
     *     is_tags: bool
     * }>  $categoryReviews
     * @param  array<string, array<string, mixed>>  $tagColors
     * @return array{
     *     value: string,
     *     label: string,
     *     count: int,
     *     resolved: bool,
     *     has_changes: bool,
     *     is_image: bool,
     *     is_tags: bool,
     *     cards: LengthAwarePaginator
     * }
     */
    private function activeCategoryReview(RefetchRun $run, array $categoryReviews, array $tagColors): array
    {
        $category = RefetchCategory::tryFrom($this->activeCategory)
            ?? RefetchCategory::cases()[0];
        $review = collect($categoryReviews)->first(
            fn(array $review): bool => $review['value'] === $category->value
        );
        $colors = $category === RefetchCategory::Tags ? $tagColors : [];
        $cards = $run->results
            ->flatMap(fn(RefetchWorkResult $result): array => $this->resultReviewCards($result, $category, $colors))
            ->values();
        $page = $this->getPage();
        $pageCards = $cards
            ->forPage($page, self::REVIEW_PAGE_SIZE)
            ->values();
        $paginator = new LengthAwarePaginator(
            $pageCards,
            $cards->count(),
            self::REVIEW_PAGE_SIZE,
            $page,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ],
        );

        return [
            ...$review,
            'cards' => $paginator,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $colors
     * @return list<array<string, mixed>>
     */
    private function resultReviewCards(
        RefetchWorkResult $result,
        RefetchCategory $category,
        array $colors,
    ): array {
        $changes = $result->changesFor($category);

        if ($changes === []) {
            return [];
        }

        if (in_array($category, [RefetchCategory::Titles, RefetchCategory::Descriptions], true)) {
            return [$this->localizedTextReviewCard($result, $category, $changes)];
        }

        if ($category === RefetchCategory::Tags) {
            $change = $changes['tags'] ?? null;

            return is_array($change)
                ? [$this->tagReviewCard($result, $change, $colors)]
                : [];
        }

        return collect($changes)
            ->map(fn(array $change, string $field): array => [
                'kind' => 'default',
                'result_id' => $result->getKey(),
                'product_id' => $result->product_id,
                'work_name' => $result->product?->work_name,
                'field' => $field,
                'label' => (string) $change['label'],
                'current' => $this->prepareCategoryReviewValue($change['old'], $category, $colors),
                'refetched' => $this->prepareCategoryReviewValue($change['new'], $category, $colors),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $changes
     * @return array<string, mixed>
     */
    private function localizedTextReviewCard(
        RefetchWorkResult $result,
        RefetchCategory $category,
        array $changes,
    ): array {
        $attribute = $category === RefetchCategory::Titles ? 'title' : 'description';
        $japanese = null;
        $localized = [];

        foreach ($changes as $field => $change) {
            $parsed = LocalizedMetadataField::parse($field);
            if ($parsed === null || $parsed['attribute'] !== $attribute) {
                continue;
            }
            $language = $parsed['language'];

            $item = [
                'field' => $field,
                'language' => $language->value,
                'language_label' => $language->label(),
                'label' => $attribute === 'title' ? $language->titleLabel() : $language->descriptionLabel(),
                'current' => $change['old'],
                'refetched' => $change['new'],
            ];

            if ($language === DlsiteLanguage::Japanese) {
                $japanese = $item;
            } else {
                $localized[$language->value] = $item;
            }
        }

        return [
            'kind' => 'localized_text',
            'result_id' => $result->getKey(),
            'product_id' => $result->product_id,
            'work_name' => $result->product?->work_name,
            'japanese' => $japanese,
            'localized' => DlsiteLanguage::orderedRows($localized),
            'localized_label' => $category === RefetchCategory::Titles
                ? __('Localized titles')
                : __('Localized descriptions'),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $colors
     * @return array<string, mixed>
     */
    private function tagReviewCard(RefetchWorkResult $result, array $change, array $colors): array
    {
        $oldFetched = is_array(data_get($change, 'old.fetched')) ? data_get($change, 'old.fetched') : [];
        $newFetched = is_array(data_get($change, 'new.fetched')) ? data_get($change, 'new.fetched') : [];
        $details = is_array($change['details'] ?? null) ? $change['details'] : [];
        $resultActions = data_get(
            $this->actions,
            RefetchCategory::Tags->value . ".{$result->getKey()}",
            [],
        );
        $localeActions = $result->resolvedTagActions(
            is_array($resultActions) ? $resultActions : [],
            $this->globalActions[RefetchCategory::Tags->value] ?? RefetchService::ACTION_IGNORE,
        );
        $locales = [];

        foreach ($result->changedTagLocales() as $locale) {
            $language = DlsiteLanguage::tryFrom($locale);
            if ($language === null) {
                continue;
            }

            $locales[$locale] = [
                'locale' => $locale,
                'language_label' => $language->label(),
                'current' => $this->prepareReviewValue($oldFetched[$locale] ?? [], false, $colors),
                'refetched' => $this->prepareReviewValue($newFetched[$locale] ?? [], false, $colors),
                'added' => $this->prepareReviewValue($details['added'][$locale] ?? [], false, $colors),
                'stale' => $this->prepareReviewValue($details['stale'][$locale] ?? [], false, $colors),
                'show_detailed_actions' => ($localeActions[$locale] ?? RefetchService::ACTION_IGNORE)
                    === RefetchService::ACTION_DETAILED,
            ];
        }

        $japanese = $locales[DlsiteLanguage::Japanese->value] ?? null;
        unset($locales[DlsiteLanguage::Japanese->value]);
        $customToFetched = $result->promotableCustomTags($localeActions);

        return [
            'kind' => 'tags',
            'result_id' => $result->getKey(),
            'product_id' => $result->product_id,
            'work_name' => $result->product?->work_name,
            'japanese' => $japanese,
            'localized' => DlsiteLanguage::orderedRows($locales),
            'localized_label' => __('Localized tags'),
            'custom_to_fetched' => $this->prepareReviewValue($customToFetched, false, $colors),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $colors
     */
    private function prepareCategoryReviewValue(mixed $value, RefetchCategory $category, array $colors): mixed
    {
        if ($category === RefetchCategory::Rating && is_array($value)) {
            $count = isset($value['dlsite_rating_count']) ? (int) $value['dlsite_rating_count'] : null;

            return [
                'Up to 5' => DLSiteRating::displayValue($value['dlsite_rating_5'] ?? null),
                'Up to 10' => DLSiteRating::displayValue($value['dlsite_rating_10'] ?? null),
                'Vote count' => DLSiteRating::displayVoteCount($count),
            ];
        }

        if ($category === RefetchCategory::ProductFormat && is_array($value)) {
            $value = ProductFormat::labelsFor($value);
        }

        return $this->prepareReviewValue($value, $category->isImage(), $colors);
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
     * @return list<array{value: string, label: string}>
     */
    private function tagAddedActionOptions(): array
    {
        return [
            ['value' => RefetchService::TAG_ADDED_ADD, 'label' => 'Add as fetched'],
            ['value' => RefetchService::TAG_ADDED_IGNORE, 'label' => 'Ignore'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function tagStaleActionOptions(): array
    {
        return [
            ['value' => RefetchService::TAG_STALE_MOVE_TO_CUSTOM, 'label' => 'Move to custom tags'],
            ['value' => RefetchService::TAG_STALE_REMOVE, 'label' => 'Remove'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function tagCustomActionOptions(): array
    {
        return [
            ['value' => RefetchService::TAG_CUSTOM_PROMOTE, 'label' => 'Promote to fetched'],
            ['value' => RefetchService::TAG_CUSTOM_KEEP, 'label' => 'Keep custom'],
        ];
    }

    /**
     * @return array{label: string, confirmation: string, loading: string}
     */
    private function finishAction(RefetchRun $run): array
    {
        return $run->hasAppliedDecisions()
            ? [
                'label' => 'Ignore Remaining and Finish',
                'confirmation' => 'Ignore every unresolved tab and finish this run?',
                'loading' => 'Finishing refetch review...',
            ]
            : [
                'label' => 'Reject Run',
                'confirmation' => 'Reject this refetch run?',
                'loading' => 'Rejecting refetch run...',
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
                ->flatMap(function (array $change): array {
                    return [
                        ...collect(data_get($change, 'old.fetched', []))->flatten()->all(),
                        ...data_get($change, 'old.custom', []),
                        ...collect(data_get($change, 'new.fetched', []))->flatten()->all(),
                    ];
                }))
            ->map(fn(mixed $title): string => trim((string) $title))
            ->filter(fn(string $title): bool => $title !== '')
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
