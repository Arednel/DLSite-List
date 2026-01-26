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
            'id.required' => 'Please enter an RJ code or a DLSite link that contains it.',
            'id.regex' => 'Could not find an RJ code (format: RJ + numbers) in your input.',
            'id.unique' => 'This RJ work is already in the database',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $id = $this->input('id');
        if (is_string($id) && preg_match('/RJ\\d+/i', $id, $matches)) {
            $this->merge([
                'id' => strtoupper($matches[0]),
            ]);
        }

        parent::prepareForValidation();
    }
}
