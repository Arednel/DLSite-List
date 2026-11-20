<?php

namespace App\Models;

use App\Enums\RefetchCategory;
use App\Support\ProductImageMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefetchWorkResult extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_FETCHED = 'fetched';

    public const STATUS_FAILED = 'failed';

    public const ACTION_INHERIT = 'inherit';

    public const ACTION_IGNORE = 'ignore';

    public const ACTION_OVERWRITE = 'overwrite';

    public const ACTION_DETAILED = 'detailed';

    protected $fillable = [
        'refetch_run_id',
        'product_id',
        'status',
        'changes',
        'decisions',
        'warnings',
        'error',
    ];

    protected $casts = [
        'changes' => 'array',
        'decisions' => 'array',
        'warnings' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(RefetchRun::class, 'refetch_run_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isFetched(): bool
    {
        return $this->status === self::STATUS_FETCHED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => __('Pending'),
            self::STATUS_FETCHED => __('Fetched'),
            self::STATUS_FAILED => __('Failed'),
            default => (string) $this->status,
        };
    }

    public function changesFor(RefetchCategory|string $category): array
    {
        $value = $category instanceof RefetchCategory ? $category->value : $category;

        return $this->getAttribute('changes')[$value] ?? [];
    }

    public function hasChangesFor(RefetchCategory|string $category): bool
    {
        return $this->changesFor($category) !== [];
    }

    /** @return list<string> */
    public function changedTagLocales(): array
    {
        $changedLocales = data_get(
            $this->changesFor(RefetchCategory::Tags),
            'tags.details.changed_locales',
            [],
        );

        return is_array($changedLocales)
            ? collect($changedLocales)
            ->filter(fn(mixed $locale): bool => is_string($locale))
            ->values()
            ->all()
            : [];
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    public function resolvedTagActions(array $overrides, string $globalAction): array
    {
        $overrideActions = [self::ACTION_IGNORE, self::ACTION_OVERWRITE, self::ACTION_DETAILED];
        $globalAction = in_array($globalAction, [self::ACTION_IGNORE, self::ACTION_OVERWRITE], true)
            ? $globalAction
            : self::ACTION_IGNORE;

        return collect($this->changedTagLocales())
            ->mapWithKeys(function (string $locale) use ($overrideActions, $globalAction, $overrides): array {
                $action = $overrides[$locale] ?? self::ACTION_INHERIT;

                return [
                    $locale => in_array($action, $overrideActions, true)
                        ? $action
                        : $globalAction,
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, string>  $resolvedActions
     * @return list<string>
     */
    public function promotableCustomTags(array $resolvedActions): array
    {
        $detailedLocales = collect($resolvedActions)
            ->filter(fn(string $action): bool => $action === self::ACTION_DETAILED)
            ->keys()
            ->all();
        $customToFetched = data_get(
            $this->changesFor(RefetchCategory::Tags),
            'tags.details.custom_to_fetched',
            [],
        );

        return collect(is_array($customToFetched) ? $customToFetched : [])
            ->only($detailedLocales)
            ->flatten()
            ->filter(fn(mixed $tag): bool => is_string($tag))
            ->unique(fn(string $tag): string => Genre::titleKey($tag))
            ->values()
            ->all();
    }

    public function hasAppliedChanges(): bool
    {
        foreach ($this->decisions ?? [] as $categoryDecisions) {
            foreach ($categoryDecisions as $decision) {
                if (($decision['changed'] ?? null) === true) {
                    return true;
                }
            }
        }

        return false;
    }

    public function displayError(): ?string
    {
        return ProductImageMessage::display($this->error);
    }
}
