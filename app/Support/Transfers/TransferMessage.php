<?php

namespace App\Support\Transfers;

use App\Support\Localization\ErrorMessage;
use App\Support\ProductImageMessage;

final class TransferMessage
{
    private const PREFIX_TEMPLATES = [
        'Duplicate entry across archive parts: :value',
        'Duplicate domain identity: :value',
        'Missing selected data section: :value',
        'Domain record exceeds the configured JSON safety limit: :value',
        'Domain record cannot fit in one supported fragment: :value',
        'Entry size mismatch: :value',
        'Entry checksum mismatch: :value',
        'Invalid image content: :value',
        'Unsafe, duplicate, encrypted or undeclared ZIP entry: :value',
        'Export source changed: :value',
        'Export source changed during finalization: :value',
        'Export source changed during build: :value',
        'Unable to add ZIP entry: :value',
        'Unable to remove temporary transfer file: :value',
        'Unable to remove temporary transfer directory: :value',
        'Unknown or non-portable option: :value',
    ];

    public static function stage(?string $message): ?string
    {
        if ($message === null || $message === '') {
            return $message;
        }

        if (str_starts_with($message, 'Preparing ')) {
            $parts = explode(' / ', substr($message, strlen('Preparing ')), 2);

            if (count($parts) === 2) {
                return __('Preparing :section / :entity', [
                    'section' => self::label($parts[0]),
                    'entity' => $parts[1],
                ]);
            }
        }

        if (preg_match('/^Preparing (.+) and its images for export$/', $message, $matches)) {
            return __('Preparing :code and its images for export', ['code' => $matches[1]]);
        }

        if (preg_match('/^Preparing (.+) for export$/', $message, $matches)) {
            return __('Preparing :code for export', ['code' => $matches[1]]);
        }

        if (str_starts_with($message, 'Applying ')) {
            $parts = explode(' / ', substr($message, strlen('Applying ')), 3);

            if (count($parts) === 3) {
                return __('Applying :section / :category / :entity', [
                    'section' => self::label($parts[0]),
                    'category' => self::label($parts[1]),
                    'entity' => $parts[2],
                ]);
            }
        }

        if (preg_match('/^(Validating|Building|Built) (data|images) part (\d+)$/', $message, $matches)) {
            return __($matches[1] . ' ' . $matches[2] . ' part :number', ['number' => $matches[3]]);
        }

        return __($message);
    }

    public static function diagnostic(?string $message): ?string
    {
        if ($message === null || $message === '') {
            return $message;
        }

        if (preg_match('/^(.+): work was removed before export planning\.$/', $message, $matches)) {
            return ErrorMessage::format(':product: work was removed before export planning.', ['product' => $matches[1]]);
        }

        if (preg_match('/^(.+): cover image is incomplete\.$/', $message, $matches)) {
            return ErrorMessage::format(':product: cover image is incomplete.', ['product' => $matches[1]]);
        }

        if (preg_match('/^(.+): sample image (\d+) is incomplete\.$/', $message, $matches)) {
            return ErrorMessage::format(':product: sample image :number is incomplete.', [
                'product' => $matches[1],
                'number' => $matches[2],
            ]);
        }

        foreach (self::PREFIX_TEMPLATES as $template) {
            $prefix = substr($template, 0, -strlen(':value'));

            if (str_starts_with($message, $prefix)) {
                return ErrorMessage::format($template, ['value' => substr($message, strlen($prefix))]);
            }
        }

        if (preg_match('/^Entry cannot fit in one part: (.+)\. Start a new export with a larger or Unlimited size\.$/', $message, $matches)) {
            return ErrorMessage::format('Entry cannot fit in one part: :value. Start a new export with a larger or Unlimited size.', ['value' => $matches[1]]);
        }

        if (preg_match('/^A (.+) record exceeds the supported JSON record size\.$/', $message, $matches)) {
            return ErrorMessage::format('A :section record exceeds the supported JSON record size.', ['section' => self::label($matches[1])]);
        }

        return ProductImageMessage::display($message);
    }

    private static function label(string $value): string
    {
        return match ($value) {
            'work' => __('Works'),
            default => ImportValue::label($value),
        };
    }
}
