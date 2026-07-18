<?php

namespace App\Http\Requests;

use App\Enums\ProductField;
use App\Enums\UiLanguage;
use App\Models\Option;
use App\Support\ProductFieldLayout;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends BaseProductRequest
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
            'work_name' => [
                Rule::requiredIf(fn(): bool => ProductFieldLayout::editable(
                    Option::editFieldLayout(),
                    ProductField::Title,
                )),
                'nullable',
                'string',
            ],
            'genre_fetched' => ['nullable', 'array'],
            'genre_fetched_language' => [
                Rule::requiredIf(fn(): bool => $this->wasSubmitted('genre_fetched')),
                Rule::in([UiLanguage::current()->fetchedTagLanguage()]),
            ],
        ], $this->commonRules());
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'genre_fetched' => $this->normalizeGenreList($this->input('genre_fetched')),
        ]);
    }
}
