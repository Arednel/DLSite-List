<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

class StartBulkImportRequest extends BaseProductRequest
{
    /** @var list<string> */
    private array $normalizedProductCodes = [];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_code_list' => ['required', 'string'],
            'product_codes' => ['required', 'array', 'max:500'],
            'product_codes.*' => ['string', 'max:191', 'regex:/^(?:RJ|BJ|VJ)\\d+$/'],
            'work_name' => ['nullable', 'string'],
            ...$this->commonRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'product_code_list.required' => __('errors.bulk_import.input.required'),
            'product_codes.max' => __('errors.bulk_import.input.max_codes'),
            'product_codes.*.max' => __('errors.bulk_import.input.code_max'),
        ];
    }

    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if ($this->normalizedProductCodes !== [] || $validator->errors()->has('product_code_list')) {
                    return;
                }

                $validator->errors()->add(
                    'product_code_list',
                    __('errors.product.code.invalid'),
                );
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizedProductCodes = $this->extractProductCodes($this->input('product_code_list'));

        $this->merge(['product_codes' => $this->normalizedProductCodes]);

        parent::prepareForValidation();
    }

    /** @return list<string> */
    public function productCodes(): array
    {
        return $this->normalizedProductCodes;
    }
}
