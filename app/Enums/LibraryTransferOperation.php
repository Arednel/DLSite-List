<?php

namespace App\Enums;

enum LibraryTransferOperation: string
{
    case Plan = 'plan';
    case Build = 'build';
    case Inspect = 'inspect';
    case Analyze = 'analyze';
    case Apply = 'apply';

    /** @return list<LibraryTransferRunStatus> */
    public function expectedStatuses(): array
    {
        return match ($this) {
            self::Plan => [LibraryTransferRunStatus::Queued, LibraryTransferRunStatus::Planning],
            self::Build => [LibraryTransferRunStatus::Building],
            self::Inspect => [LibraryTransferRunStatus::Uploading, LibraryTransferRunStatus::WaitingForParts],
            self::Analyze => [LibraryTransferRunStatus::Analyzing],
            self::Apply => [LibraryTransferRunStatus::Applying],
        };
    }
}
