<?php

namespace App\Http\Requests;

use App\Enums\ProductAgeCategory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreCustomProductRequest extends BaseProductRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'id' => ['bail', 'required', 'regex:/^RJ\d+$/', Rule::unique('products', 'id')],
            'work_name' => ['required', 'string'],
            'age_category' => ['required', Rule::enum(ProductAgeCategory::class)],
            'work_image' => ['required', File::image()->max('20mb')],
            'sample_images' => ['nullable', 'array'],
            'sample_images.*' => [File::image()->max('20mb')],
        ], $this->commonRules());
    }

    /**
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

    protected function prepareForValidation(): void
    {
        $this->normalizeRjIdInput();
        parent::prepareForValidation();
    }
}
