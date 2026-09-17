<?php

namespace App\Support\Transfers;

use App\Models\Genre;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class LibraryTagValidator
{
    public function validate(array $record): void
    {
        Validator::make($record, [
            'type' => ['required', Rule::in(['tag', 'group'])],
            'key' => ['required', 'string'],
            'value' => ['required', 'array'],
            'value.title' => ['required', 'string', 'max:255'],
            'value.description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'value.hidden_on_index' => ['sometimes', 'boolean'],
            'value.color' => ['sometimes', 'nullable', 'regex:/\A#[0-9a-fA-F]{6}\z/'],
            'value.text_color' => ['sometimes', 'nullable', 'regex:/\A#[0-9a-fA-F]{6}\z/'],
            'value.parents' => ['sometimes', 'array'],
            'value.parents.*' => ['required', 'string', 'max:255'],
            'value.members' => ['sometimes', 'array'],
            'value.members.*' => ['required', 'string', 'max:255'],
        ])->validate();

        if ($record['key'] !== Genre::titleKey($record['value']['title'])) {
            throw new InvalidArgumentException('Tag identity does not match its title.');
        }
    }
}
