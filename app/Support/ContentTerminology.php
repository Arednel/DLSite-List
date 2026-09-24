<?php

namespace App\Support;

use App\Enums\ContentFocus;
use App\Enums\ProductProgress;
use App\Enums\ProductScore;

final readonly class ContentTerminology
{
    /**
     * General is the baseline. Focused profiles only override wording that differs.
     *
     * @var array<string, array<string, string>>
     */
    private const TERMS = [
        'all_works' => [
            'general' => 'All Works',
            'listening' => 'All ASMR',
            'games' => 'All Games',
            'video' => 'All Videos',
            'music' => 'All Music',
            'artwork' => 'All Artwork',
        ],
        'progress_active' => [
            'general' => 'In Progress',
            'listening' => 'Listening',
            'reading' => 'Reading',
            'games' => 'Playing',
            'video' => 'Watching',
            'music' => 'Listening',
            'artwork' => 'Viewing',
        ],
        'progress_active_current' => [
            'general' => 'Currently In Progress',
            'listening' => 'Currently Listening',
            'reading' => 'Currently Reading',
            'games' => 'Currently Playing',
            'video' => 'Currently Watching',
            'music' => 'Currently Listening',
            'artwork' => 'Currently Viewing',
        ],
        'progress_planned' => [
            'general' => 'Planned',
            'listening' => 'Plan to Listen',
            'reading' => 'Plan to Read',
            'games' => 'Plan to Play',
            'video' => 'Plan to Watch',
            'music' => 'Plan to Listen',
            'artwork' => 'Plan to View',
        ],
        'repeat_count' => [
            'general' => 'Total Times Repeated',
            'listening' => 'Total Times Re-listened',
            'reading' => 'Total Times Re-read',
            'games' => 'Total Times Replayed',
            'video' => 'Total Times Rewatched',
            'music' => 'Total Times Re-listened',
            'artwork' => 'Total Times Revisited',
        ],
        'repeat_value' => [
            'general' => 'Repeat Value',
            'listening' => 'Re-listen Value',
            'reading' => 'Re-read Value',
            'games' => 'Replay Value',
            'video' => 'Rewatch Value',
            'music' => 'Re-listen Value',
            'artwork' => 'Revisit Value',
        ],
        'repeat_value_select' => [
            'general' => 'Select repeat value',
            'listening' => 'Select re-listen value',
            'reading' => 'Select re-read value',
            'games' => 'Select replay value',
            'video' => 'Select rewatch value',
            'music' => 'Select re-listen value',
            'artwork' => 'Select revisit value',
        ],
        'score_10' => [
            'general' => '(10) Masterpiece',
        ],
        'score_9' => [
            'general' => '(9) Great',
        ],
        'score_8' => [
            'general' => '(8) Very Good',
        ],
        'score_7' => [
            'general' => '(7) Good',
        ],
        'score_6' => [
            'general' => '(6) Fine',
            'listening' => '(6) Nice',
        ],
        'score_5' => [
            'general' => '(5) Average',
        ],
        'score_4' => [
            'general' => '(4) Bad',
            'listening' => '(4) Below Average',
        ],
        'score_3' => [
            'general' => '(3) Very Bad',
            'listening' => '(3) Unremarkable',
        ],
        'score_2' => [
            'general' => '(2) Horrible',
            'listening' => '(2) Subtle',
        ],
        'score_1' => [
            'general' => '(1) Appalling',
            'listening' => '(1) Faint',
        ],
    ];

    public function __construct(
        private ContentFocus $focus,
    ) {}

    public function allWorks(): string
    {
        return $this->term('all_works');
    }

    public function progress(ProductProgress $progress): string
    {
        return match ($progress) {
            ProductProgress::Listening => $this->term('progress_active'),
            ProductProgress::PlanToListen => $this->term('progress_planned'),
            ProductProgress::Completed => __('Completed'),
            ProductProgress::OnHold => __('On Hold'),
            ProductProgress::Dropped => __('Dropped'),
        };
    }

    public function currentProgress(): string
    {
        return $this->term('progress_active_current');
    }

    public function repeatCount(): string
    {
        return $this->term('repeat_count');
    }

    public function repeatValue(): string
    {
        return $this->term('repeat_value');
    }

    public function repeatValueSelect(): string
    {
        return $this->term('repeat_value_select');
    }

    public function score(ProductScore $score): string
    {
        return $this->term('score_' . $score->value);
    }

    /**
     * @return list<array{general: string, selected: string}>
     */
    public function previewRows(): array
    {
        $general = new self(ContentFocus::General);

        return collect(array_keys(self::TERMS))
            ->map(fn(string $term): array => [
                'general' => $general->term($term),
                'selected' => $this->term($term),
            ])
            ->all();
    }

    private function term(string $term): string
    {
        $translationKey = self::TERMS[$term][$this->focus->value]
            ?? self::TERMS[$term][ContentFocus::General->value];

        return __($translationKey);
    }
}
