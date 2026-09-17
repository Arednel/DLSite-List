<?php

namespace App\Support\Transfers;

use App\Enums\ProductAgeCategory;
use App\Enums\ProductContributorRole;
use App\Enums\ProductPriority;
use App\Enums\ProductProgress;
use App\Enums\ProductReListenValue;
use App\Enums\ProductScore;
use App\Rules\MaxBytes;
use App\Rules\ValidPartialDate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class LibraryWorkValidator
{
    public function validate(array $data): array
    {
        $this->validateAllowedKeys($data);

        if (isset($data['rj_code']) && is_string($data['rj_code'])) {
            $data['rj_code'] = strtoupper(trim($data['rj_code']));
        }

        Validator::make($data, $this->rules(), $this->messages())->validate();

        return $data;
    }

    private function validateAllowedKeys(array $data): void
    {
        $allowed = ['rj_code', 'created_at', 'updated_at', ...array_keys(LibraryData::FIELDS), 'contributors', 'tags', 'cover', 'sample_images'];

        Validator::make(['work' => $data], [
            'work' => ['array:' . implode(',', $allowed)],
        ], [
            'work.array' => 'Unknown work fields in schema v1.',
        ])->validate();
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rules(): array
    {
        return [
            'rj_code' => ['required', 'string', 'max:191', 'regex:/\ARJ\d+\z/'],
            'created_at' => ['sometimes', 'nullable', 'date', 'regex:' . LibraryData::UTC_DATE_PATTERN],
            'updated_at' => ['sometimes', 'nullable', 'date', 'regex:' . LibraryData::UTC_DATE_PATTERN],
            'titles' => ['required', 'array:' . implode(',', LibraryData::FIELDS['titles'])],
            'titles.work_name' => ['required', 'string', 'max:1000'],
            'titles.work_name_english' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'descriptions' => ['sometimes', 'array:' . implode(',', LibraryData::FIELDS['descriptions'])],
            'descriptions.description' => ['sometimes', 'nullable', 'string', 'max:65535', new MaxBytes(65535)],
            'descriptions.description_english' => ['sometimes', 'nullable', 'string', 'max:65535', new MaxBytes(65535)],
            'details' => ['sometimes', 'array:' . implode(',', LibraryData::FIELDS['details'])],
            'details.maker_id' => ['sometimes', 'nullable', 'string', 'max:200'],
            'details.series' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'details.age_category' => ['sometimes', 'nullable', Rule::enum(ProductAgeCategory::class)],
            'details.notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'listening' => ['sometimes', 'array:' . implode(',', LibraryData::FIELDS['listening'])],
            'listening.progress' => ['sometimes', 'nullable', Rule::enum(ProductProgress::class)],
            'listening.score' => ['sometimes', 'nullable', Rule::enum(ProductScore::class)],
            'listening.priority' => ['sometimes', 'nullable', Rule::enum(ProductPriority::class)],
            'listening.num_re_listen_times' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:2147483647'],
            'listening.re_listen_value' => ['sometimes', 'nullable', Rule::enum(ProductReListenValue::class)],
            'listening.start_date' => ['sometimes', 'nullable', 'array:year,month,day', new ValidPartialDate],
            'listening.start_date.year' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:9999'],
            'listening.start_date.month' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:12'],
            'listening.start_date.day' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:31'],
            'listening.end_date' => ['sometimes', 'nullable', 'array:year,month,day', new ValidPartialDate],
            'listening.end_date.year' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:9999'],
            'listening.end_date.month' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:12'],
            'listening.end_date.day' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:31'],
            'tags' => ['sometimes', 'array:custom,jp,en'],
            'tags.*' => ['sometimes', 'array', 'max:10000'],
            'tags.*.*' => ['required', 'string', 'max:255'],
            'contributors' => ['sometimes', 'array:' . implode(',', array_column(ProductContributorRole::cases(), 'value'))],
            'contributors.*' => ['sometimes', 'array', 'max:10000'],
            'contributors.*.*' => ['required', 'string', 'max:255'],
            'contributors.circle.*' => ['required', 'string', 'max:200'],
            'cover' => ['sometimes', 'array:complete,files', 'required_array_keys:complete,files'],
            'cover.complete' => ['required_with:cover', 'boolean'],
            'cover.files' => ['sometimes', 'array', 'max:1'],
            'cover.files.*' => ['required', 'array'],
            'cover.files.*.path' => ['required', 'string'],
            'cover.files.*.sha256' => ['required', 'string'],
            'cover.files.*.bytes' => ['required', 'integer:strict', 'min:0'],
            'cover.files.*.media_type' => ['required', 'string'],
            'sample_images' => ['sometimes', 'array:complete,files', 'required_array_keys:complete,files'],
            'sample_images.complete' => ['required_with:sample_images', 'boolean'],
            'sample_images.files' => ['sometimes', 'array', 'max:10000'],
            'sample_images.files.*' => ['required', 'array'],
            'sample_images.files.*.path' => ['required', 'string'],
            'sample_images.files.*.sha256' => ['required', 'string'],
            'sample_images.files.*.bytes' => ['required', 'integer:strict', 'min:0'],
            'sample_images.files.*.media_type' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'contributors.circle.*.max' => 'Circle exceeds the legacy field limit of 200 characters.',
            'cover.files.max' => 'A work can have only one cover.',
        ];
    }
}
