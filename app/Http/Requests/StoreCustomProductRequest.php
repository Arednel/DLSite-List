<?php

namespace App\Http\Requests;

use App\Enums\ProductAgeCategory;
use App\Enums\ProductField;
use App\Models\Option;
use App\Support\ProductFieldLayout;
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
        $visibleFields = ProductFieldLayout::visibleFields(Option::customQuickAddFieldLayout());
        $coverVisible = in_array(ProductField::Image->value, $visibleFields, true);
        $samplesVisible = in_array(ProductField::SampleImages->value, $visibleFields, true);

        return array_merge($this->commonRules(), [
            'id' => ['bail', 'required', 'max:191', 'regex:/^(?:RJ|BJ|VJ)\d+$/', Rule::unique('products', 'id')],
            'work_name' => ['required', 'string'],
            'age_category' => ['required', Rule::enum(ProductAgeCategory::class)],
            'work_image' => [Rule::excludeIf(! $coverVisible), 'nullable', File::image()->max('20mb')],
            'sample_images' => [Rule::excludeIf(! $samplesVisible), 'nullable', 'array'],
            'sample_images.*' => [File::image()->max('20mb')],
            'sample_image_order' => [Rule::excludeIf(! $samplesVisible), 'nullable', 'array'],
            'sample_image_order.*' => ['string', 'max:512'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id.required' => __('errors.Enter a product code or a link containing one.'),
            'id.regex' => __('errors.Could not find a product code (RJ, BJ or VJ followed by numbers) in your input.'),
            'id.unique' => __('errors.Work with this product code is already in your library'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeProductCodeInput();
        parent::prepareForValidation();
    }
}
