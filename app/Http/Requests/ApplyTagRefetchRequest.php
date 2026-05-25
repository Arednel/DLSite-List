<?php

namespace App\Http\Requests;

use App\Models\TagRefetchRun;
use App\Models\TagRefetchWorkResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ApplyTagRefetchRequest extends FormRequest
{
    private const ACTION_INHERIT = 'inherit';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $staleActions = [
            TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
            TagRefetchWorkResult::STALE_ACTION_REMOVE,
        ];
        $workActions = [
            self::ACTION_INHERIT,
            ...$staleActions,
        ];
        $addedActions = [
            TagRefetchWorkResult::ADDED_ACTION_ADD,
            TagRefetchWorkResult::ADDED_ACTION_IGNORE,
        ];
        $workAddedActions = [
            self::ACTION_INHERIT,
            ...$addedActions,
        ];
        $customToFetchedActions = [
            TagRefetchWorkResult::CUSTOM_TO_FETCHED_ACTION_PROMOTE,
            TagRefetchWorkResult::CUSTOM_TO_FETCHED_ACTION_KEEP_CUSTOM,
        ];
        $workCustomToFetchedActions = [
            self::ACTION_INHERIT,
            ...$customToFetchedActions,
        ];

        return [
            'global_japanese_action' => ['required', Rule::in($staleActions)],
            'global_english_action' => ['required', Rule::in($staleActions)],
            'global_added_japanese_action' => ['nullable', Rule::in($addedActions)],
            'global_added_english_action' => ['nullable', Rule::in($addedActions)],
            'global_custom_to_fetched_action' => ['nullable', Rule::in($customToFetchedActions)],
            'work_actions' => ['nullable', 'array'],
            'work_actions.*.japanese' => ['nullable', Rule::in($workActions)],
            'work_actions.*.english' => ['nullable', Rule::in($workActions)],
            'work_actions.*.added_japanese' => ['nullable', Rule::in($workAddedActions)],
            'work_actions.*.added_english' => ['nullable', Rule::in($workAddedActions)],
            'work_actions.*.custom_to_fetched' => ['nullable', Rule::in($workCustomToFetchedActions)],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $run = $this->refetchRun();

                if (! $run?->canBeApplied()) {
                    $validator->errors()->add(
                        'run',
                        $run?->applyUnavailableMessage() ?? 'This refetch run is not ready to apply.'
                    );
                }
            },
        ];
    }

    public function globalJapaneseAction(): string
    {
        return $this->validated('global_japanese_action');
    }

    public function globalEnglishAction(): string
    {
        return $this->validated('global_english_action');
    }

    public function globalAddedJapaneseAction(): string
    {
        return $this->validated(
            'global_added_japanese_action',
            TagRefetchWorkResult::ADDED_ACTION_ADD
        );
    }

    public function globalAddedEnglishAction(): string
    {
        return $this->validated(
            'global_added_english_action',
            TagRefetchWorkResult::ADDED_ACTION_ADD
        );
    }

    public function globalCustomToFetchedAction(): string
    {
        return $this->validated(
            'global_custom_to_fetched_action',
            TagRefetchWorkResult::CUSTOM_TO_FETCHED_ACTION_PROMOTE
        );
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function workActions(): array
    {
        return $this->validated('work_actions', []);
    }

    protected function getRedirectUrl(): string
    {
        $run = $this->refetchRun();

        return $run === null
            ? parent::getRedirectUrl()
            : route('options.refetch-tags.show', $run);
    }

    private function refetchRun(): ?TagRefetchRun
    {
        $run = $this->route('run');

        return $run instanceof TagRefetchRun ? $run : null;
    }
}
