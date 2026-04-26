<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StartTagRefetchRequest extends FormRequest
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
            'product_ids' => ['nullable', 'array', 'required_if:scope,selected'],
            'product_ids.*' => ['string', 'exists:products,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_ids.required_if' => 'Select at least one work to refetch.',
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->productIds() === []) {
                    $validator->errors()->add('product_ids', 'Select at least one work to refetch.');
                }
            },
        ];
    }

    /**
     * @return list<string>
     */
    public function productIds(): array
    {
        if ($this->input('scope') === 'all') {
            return Product::query()
                ->orderBy('id')
                ->pluck('id')
                ->all();
        }

        $selectedIds = collect($this->input('product_ids', []))
            ->unique()
            ->values()
            ->all();

        return Product::query()
            ->whereIn('id', $selectedIds)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }
}
