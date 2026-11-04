<?php

namespace Tests\Unit\Support\Transfers;

use App\Support\Transfers\LibraryWorkValidator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LibraryWorkValidatorTest extends TestCase
{
    #[DataProvider('ratingPairs')]
    public function test_dlsite_rating_scales_are_validated_as_a_pair(array $details, bool $valid): void
    {
        if (! $valid) {
            $this->expectException(ValidationException::class);
        }

        app(LibraryWorkValidator::class)->validate([
            'product_code' => 'RJ123456',
            'titles' => ['work_name' => 'Test work'],
            'details' => $details,
        ]);

        if ($valid) {
            $this->addToAssertionCount(1);
        }
    }

    public static function ratingPairs(): array
    {
        return [
            'no rating fields' => [[], true],
            'count only' => [['dlsite_rating_count' => 100], true],
            'both ratings null' => [[
                'dlsite_rating_5' => null,
                'dlsite_rating_10' => null,
            ], true],
            'both ratings populated' => [[
                'dlsite_rating_5' => '4.97',
                'dlsite_rating_10' => '9.93',
                'dlsite_rating_count' => 100,
            ], true],
            'five only' => [['dlsite_rating_5' => '4.97'], false],
            'ten only' => [['dlsite_rating_10' => '9.93'], false],
            'five null and ten populated' => [[
                'dlsite_rating_5' => null,
                'dlsite_rating_10' => '9.93',
            ], false],
            'five populated and ten null' => [[
                'dlsite_rating_5' => '4.97',
                'dlsite_rating_10' => null,
            ], false],
        ];
    }
}
