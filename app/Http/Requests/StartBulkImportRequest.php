<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

class StartBulkImportRequest extends BaseProductRequest
{
    /** @var list<string> */
    private array $normalizedRjCodes = [];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rj_list' => ['required', 'string'],
            'rj_codes' => ['required', 'array', 'max:500'],
            'rj_codes.*' => ['string', 'max:191', 'regex:/^RJ\\d+$/'],
            'work_name' => ['nullable', 'string'],
            ...$this->commonRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'rj_list.required' => __('Enter text containing at least one RJ code.'),
            'rj_codes.max' => __('You can import up to 500 RJ codes at once.'),
            'rj_codes.*.max' => __('RJ code #:position must not exceed :max characters.'),
        ];
    }

    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if ($this->normalizedRjCodes !== [] || $validator->errors()->has('rj_list')) {
                    return;
                }

                $validator->errors()->add(
                    'rj_list',
                    __('Could not find an RJ code (format: RJ + numbers) in your input.'),
                );
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizedRjCodes = $this->extractRjCodes($this->input('rj_list'));

        $this->merge(['rj_codes' => $this->normalizedRjCodes]);

        parent::prepareForValidation();
    }

    /** @return list<string> */
    public function rjCodes(): array
    {
        return $this->normalizedRjCodes;
    }
}
