<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class StartRefetchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'scope' => ['required', 'in:all,selected'],
            'product_ids' => ['nullable', 'array', 'required_if:scope,selected', 'min:1'],
            'product_ids.*' => ['string', 'exists:products,id'],
            'check_images' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_ids.required_if' => __('Select at least one work to refetch.'),
            'product_ids.min' => __('Select at least one work to refetch.'),
        ];
    }

    /**
     * @return list<string>
     */
    public function productIds(): array
    {
        $query = Product::query()->orderByNumericRj();

        if ($this->validated('scope') === 'selected') {
            $query->whereKey($this->validated('product_ids'));
        }

        return $query->pluck('id')->all();
    }
}
