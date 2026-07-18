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
            'id' => ['bail', 'required', 'regex:/^RJ\\d+$/', Rule::unique('products', 'id')],
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
            'id.required' => __('Please enter an RJ code or a DLSite link that contains it.'),
            'id.regex' => __('Could not find an RJ code (format: RJ + numbers) in your input.'),
            'id.unique' => __('This RJ work is already in the database'),
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->normalizeRjIdInput();
        parent::prepareForValidation();
    }
}
