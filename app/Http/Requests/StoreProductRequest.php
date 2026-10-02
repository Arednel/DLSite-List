<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreProductRequest extends BaseProductRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'id' => ['bail', 'required', 'max:191', 'regex:/^(?:RJ|BJ|VJ)\\d+$/', Rule::unique('products', 'id')],
            'work_name' => ['nullable', 'string'],
        ], $this->commonRules());
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id.required' => __('Enter a product code or a link containing one.'),
            'id.regex' => __('Could not find a product code (RJ, BJ or VJ followed by numbers) in your input.'),
            'id.unique' => __('Work with this product code is already in your library'),
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->normalizeProductCodeInput();
        parent::prepareForValidation();
    }
}
