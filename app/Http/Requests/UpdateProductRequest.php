<?php

namespace App\Http\Requests;

use App\Enums\ProductField;
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
        $editFieldLayout = Option::editFieldLayout();
        $validateFetchedLanguage = ProductFieldLayout::fetchedTagsEditable($editFieldLayout)
            && $this->wasSubmitted('genre_fetched');
        $fetchedLanguageRules = [
            Rule::requiredIf($validateFetchedLanguage),
        ];

        if ($validateFetchedLanguage) {
            $fetchedLanguageRules[] = Rule::in([Option::effectiveFetchedTagLanguage()->value]);
        }

        return array_merge([
            'work_name' => [
                Rule::requiredIf(fn(): bool => ProductFieldLayout::editable(
                    $editFieldLayout,
                    ProductField::Title,
                )),
                'nullable',
                'string',
            ],
            'genre_fetched' => ['nullable', 'array'],
            'genre_fetched_language' => $fetchedLanguageRules,
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
