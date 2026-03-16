<?php

namespace App\Http\Requests;

use App\Support\ProductIndexFilters;
use Illuminate\Foundation\Http\FormRequest;

class ProductIndexRequest extends FormRequest
{
    private ?ProductIndexFilters $filters = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string'],
            'title' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'genre' => ['nullable', 'string'],
            'series' => ['nullable', 'string'],
            'tags' => ['nullable', 'string'],
            'tag_match' => ['nullable', 'string'],
            'age_category' => ['nullable', 'string'],
            'progress' => ['nullable', 'string'],
            'score' => ['nullable', 'string'],
            'priority' => ['nullable', 'string'],
            'num_re_listen_times' => ['nullable', 'string'],
            're_listen_value' => ['nullable', 'string'],
            'sort_first_field' => ['nullable', 'string'],
            'sort_first_direction' => ['nullable', 'string'],
            'sort_second_field' => ['nullable', 'string'],
            'sort_second_direction' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->filters = ProductIndexFilters::fromQuery($this->query());

        $this->merge($this->filters->toInput());
    }

    public function filters(): ProductIndexFilters
    {
        return $this->filters ??= ProductIndexFilters::fromQuery($this->query());
    }
}
