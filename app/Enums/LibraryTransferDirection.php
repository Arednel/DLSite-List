<?php

namespace App\Enums;

enum LibraryTransferDirection: string
{
    case Export = 'export';
    case Import = 'import';

    public function isExport(): bool
    {
        return $this === self::Export;
    }

    public function isImport(): bool
    {
        return $this === self::Import;
    }

    /** @return list<LibraryTransferRunStatus> */
    public function supersedableStatuses(): array
    {
        return match ($this) {
            self::Export => [
                LibraryTransferRunStatus::Queued,
                LibraryTransferRunStatus::Planning,
                LibraryTransferRunStatus::AwaitingConfirmation,
                LibraryTransferRunStatus::Building,
                LibraryTransferRunStatus::Failed,
            ],
            self::Import => [
                LibraryTransferRunStatus::Uploading,
                LibraryTransferRunStatus::WaitingForParts,
                LibraryTransferRunStatus::Analyzing,
                LibraryTransferRunStatus::Review,
                LibraryTransferRunStatus::Failed,
            ],
        };
    }
}
