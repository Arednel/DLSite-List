<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;

enum ProductIndexSortField: string
{
    use ProvidesOptions;

    case RJ = 'rj';
    case Score = 'score';
    case Series = 'series';
    case AgeCategory = 'age_category';
    case Progress = 'progress';
    case Priority = 'priority';
    case TotalTimesReListened = 'num_re_listen_times';
    case ReListenValue = 're_listen_value';
    case StartDate = 'start_date';
    case FinishDate = 'end_date';
    case AddedToTheSiteDate = 'created_at';

    public function label(): string
    {
        return match ($this) {
            self::RJ => 'RJ / Title',
            self::Score => 'Score',
            self::Series => 'Series',
            self::AgeCategory => 'Age',
            self::Progress => 'Progress',
            self::Priority => 'Priority',
            self::TotalTimesReListened => 'Total Times Re-listened',
            self::ReListenValue => 'Re-listen Value',
            self::StartDate => 'Start Date',
            self::FinishDate => 'Finish Date',
            self::AddedToTheSiteDate => 'Added to the site Date',
        };
    }

    public function sqlColumn(): string
    {
        return match ($this) {
            self::RJ => 'rj_number',
            self::StartDate => 'start_date_sort',
            self::FinishDate => 'end_date_sort',
            default => $this->value,
        };
    }
}
