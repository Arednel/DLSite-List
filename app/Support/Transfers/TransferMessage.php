<?php

namespace App\Support\Transfers;

use App\Support\Localization\ErrorMessage;
use App\Support\ProductImageMessage;

final class TransferMessage
{
    private const PREFIX_TEMPLATES = [
        'Duplicate entry across archive parts: :value' => 'transfer.archive.duplicate_entry',
        'Duplicate domain identity: :value' => 'transfer.archive.duplicate_domain_identity',
        'Missing selected data section: :value' => 'transfer.archive.missing_data_section',
        'Domain record exceeds the configured JSON safety limit: :value' => 'transfer.archive.domain_record_limit',
        'Domain record cannot fit in one supported fragment: :value' => 'transfer.archive.domain_record_fragment_limit',
        'Entry size mismatch: :value' => 'transfer.archive.entry_size_mismatch',
        'Entry checksum mismatch: :value' => 'transfer.archive.entry_checksum_mismatch',
        'Invalid image content: :value' => 'transfer.archive.invalid_image_content',
        'Unsafe, duplicate, encrypted or undeclared ZIP entry: :value' => 'transfer.archive.unsafe_entry',
        'Export source changed: :value' => 'transfer.export.source_changed',
        'Export source changed during finalization: :value' => 'transfer.export.source_changed_finalization',
        'Export source changed during build: :value' => 'transfer.export.source_changed_build',
        'Unable to add ZIP entry: :value' => 'transfer.archive.add_entry_failed',
        'Unable to remove temporary transfer file: :value' => 'transfer.storage.remove_temp_file_failed',
        'Unable to remove temporary transfer directory: :value' => 'transfer.storage.remove_temp_directory_failed',
        'Unknown or non-portable option: :value' => 'transfer.import.unknown_option',
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
            return __('errors.transfer.export.work_removed', ['product' => $matches[1]]);
        }

        if (preg_match('/^(.+): cover image is incomplete\.$/', $message, $matches)) {
            return __('errors.transfer.export.cover_incomplete', ['product' => $matches[1]]);
        }

        if (preg_match('/^(.+): sample image (\d+) is incomplete\.$/', $message, $matches)) {
            return __('errors.transfer.export.sample_incomplete', [
                'product' => $matches[1],
                'number' => $matches[2],
            ]);
        }

        foreach (self::PREFIX_TEMPLATES as $template => $error) {
            $prefix = substr($template, 0, -strlen(':value'));

            if (str_starts_with($message, $prefix)) {
                return __('errors.' . $error, ['value' => substr($message, strlen($prefix))]);
            }
        }

        if (preg_match('/^Entry cannot fit in one part: (.+)\. Start a new export with a larger or Unlimited size\.$/', $message, $matches)) {
            return __('errors.transfer.export.entry_too_large', ['value' => $matches[1]]);
        }

        if (preg_match('/^A (.+) record exceeds the supported JSON record size\.$/', $message, $matches)) {
            return __('errors.transfer.archive.section_record_size_limit', ['section' => self::label($matches[1])]);
        }

        $customCssRecoveryError = 'transfer.custom_css.recovery.failed_with_detail|';
        if (str_starts_with($message, $customCssRecoveryError)) {
            $detail = trim(substr($message, strlen($customCssRecoveryError)));
            if ($detail === '') {
                return __('errors.transfer.custom_css.recovery.failed');
            }

            return __('errors.transfer.custom_css.recovery.failed_with_detail', [
                'detail' => ErrorMessage::display($detail),
            ]);
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
