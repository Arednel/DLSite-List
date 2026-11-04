<?php

return [
    'custom' => [
        'indexFields.*.width' => [
            'required' => 'A custom column width is required.',
            'regex' => 'The column width format is invalid. Use px, rem, em, %, or vw.',
        ],
        'indexFields.*.width_mode' => [
            'required' => 'A column width mode is required.',
            'in' => 'The selected column width mode is invalid.',
        ],
    ],
];
