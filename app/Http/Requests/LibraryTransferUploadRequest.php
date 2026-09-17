<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LibraryTransferUploadRequest extends FormRequest
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
            'archive' => ['required', 'file', 'extensions:zip'],
        ];
    }
}
