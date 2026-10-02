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
            'product_code_list.required' => __('Enter text containing at least one product code.'),
            'product_codes.max' => __('You can import up to 500 product codes at once.'),
            'product_codes.*.max' => __('Product code #:position must not exceed :max characters.'),
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
                    __('Could not find a product code (RJ, BJ or VJ followed by numbers) in your input.'),
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
