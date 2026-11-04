<?php

namespace App\Support\DLSite;

use App\Enums\RatingScale;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

final readonly class DLSiteRating
{
    public const MAX_COUNT = 4294967295;

    public const RATING_KEYS = [
        'dlsite_rating_5',
        'dlsite_rating_10',
    ];

    public const ATTRIBUTE_KEYS = [
        ...self::RATING_KEYS,
        'dlsite_rating_count',
    ];

    public function __construct(
        public ?string $rating5,
        public ?string $rating10,
        public ?int $count,
    ) {}

    public static function fromDistribution(mixed $detail): self
    {
        if (! is_array($detail)) {
            return new self(null, null, null);
        }

        $counts = Arr::only($detail, range(1, 5));

        if ($counts === []) {
            return new self(null, null, null);
        }

        $counts = array_map(fn($count) => $count ?? 0, $counts);

        if (Validator::make(
            ['counts' => $counts],
            ['counts.*' => ['required', 'integer', 'min:0', 'max:' . self::MAX_COUNT]],
        )->fails()) {
            return new self(null, null, null);
        }

        $count = 0;
        $weighted = 0;

        foreach ($counts as $rating => $amount) {
            $amount = (int) $amount;
            $count += $amount;
            $weighted += (int) $rating * $amount;
        }

        if ($count > self::MAX_COUNT) {
            return new self(null, null, null);
        }

        if ($count === 0) {
            return new self(null, null, 0);
        }

        return new self(
            number_format($weighted / $count, 2, '.', ''),
            number_format($weighted * 2 / $count, 2, '.', ''),
            $count,
        );
    }

    public static function fromManual(mixed $rating, mixed $count, RatingScale $scale): self
    {
        $input = [
            'rating' => $rating === '' ? null : $rating,
            'count' => $count === '' ? null : $count,
        ];
        $validated = Validator::make($input, [
            'rating' => self::manualRatingRules($scale),
            'count' => self::manualCountRules(),
        ])->validate();

        $normalizedCount = isset($validated['count'])
            ? (int) $validated['count']
            : null;

        if (! isset($validated['rating'])) {
            return new self(null, null, $normalizedCount);
        }

        $rating = (float) $validated['rating'];
        $rating5 = $scale === RatingScale::Five ? $rating : $rating / 2;

        return new self(
            number_format($rating5, 2, '.', ''),
            number_format($rating5 * 2, 2, '.', ''),
            $normalizedCount,
        );
    }

    /** @return list<string> */
    public static function manualRatingRules(RatingScale $scale): array
    {
        return ['nullable', 'numeric', 'min:0', 'max:' . $scale->value, 'decimal:0,2'];
    }

    /** @return list<string> */
    public static function manualCountRules(): array
    {
        return ['nullable', 'integer', 'min:0', 'max:' . self::MAX_COUNT];
    }

    public static function displayValue(?string $rating): ?string
    {
        if ($rating === null) {
            return null;
        }

        if (! str_contains($rating, '.')) {
            return $rating . '.0';
        }

        [$whole, $fraction] = explode('.', $rating, 2);
        $fraction = rtrim($fraction, '0');

        return $whole . '.' . ($fraction === '' ? '0' : $fraction);
    }

    public static function displayVoteCount(?int $count): ?string
    {
        return $count === null
            ? null
            : trans_choice('(:count vote)|(:count votes)', $count, ['count' => number_format($count)]);
    }

    /** @return array{dlsite_rating_5: ?string, dlsite_rating_10: ?string, dlsite_rating_count: ?int} */
    public function attributes(): array
    {
        return [
            'dlsite_rating_5' => $this->rating5,
            'dlsite_rating_10' => $this->rating10,
            'dlsite_rating_count' => $this->count,
        ];
    }
}
