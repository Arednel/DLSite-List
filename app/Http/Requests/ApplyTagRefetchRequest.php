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

        return [
            'global_japanese_action' => ['required', Rule::in($staleActions)],
            'global_english_action' => ['required', Rule::in($staleActions)],
            'work_actions' => ['nullable', 'array'],
            'work_actions.*.japanese' => ['nullable', Rule::in($workActions)],
            'work_actions.*.english' => ['nullable', Rule::in($workActions)],
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
