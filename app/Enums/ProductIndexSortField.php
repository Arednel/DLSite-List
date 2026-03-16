<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;

enum ProductIndexSortField: string
{
    use ProvidesOptions;

    case Score = 'score';
    case Priority = 'priority';
    case TotalTimesReListened = 'num_re_listen_times';
    case ReListenValue = 're_listen_value';
    case StartDate = 'start_date';
    case FinishDate = 'end_date';

    public function label(): string
    {
        return match ($this) {
            self::Score => 'Score',
            self::Priority => 'Priority',
            self::TotalTimesReListened => 'Total Times Re-listened',
            self::ReListenValue => 'Re-listen Value',
            self::StartDate => 'Start Date',
            self::FinishDate => 'Finish Date',
        };
    }
}
