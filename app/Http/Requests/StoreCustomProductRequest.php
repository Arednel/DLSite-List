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
        return array_merge($this->commonRules(), [
            'id' => ['bail', 'required', 'max:191', 'regex:/^(?:RJ|BJ|VJ)\d+$/', Rule::unique('products', 'id')],
            'work_name' => ['required', 'string'],
            'age_category' => ['required', Rule::enum(ProductAgeCategory::class)],
            'work_image' => ['required', File::image()->max('20mb')],
            'sample_images' => ['nullable', 'array'],
            'sample_images.*' => [File::image()->max('20mb')],
        ]);
    }

    /**
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

    protected function prepareForValidation(): void
    {
        $this->normalizeProductCodeInput();
        parent::prepareForValidation();
    }
}
