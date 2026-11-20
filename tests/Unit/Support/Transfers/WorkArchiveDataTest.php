<?php

namespace Tests\Unit\Support\Transfers;

use App\Support\Transfers\WorkArchiveData;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkArchiveDataTest extends TestCase
{
    #[DataProvider('malformedV4LocalizedValues')]
    public function test_v4_raw_localized_values_are_validated_before_normalization(array $english): void
    {
        $this->expectException(ValidationException::class);

        app(WorkArchiveData::class)->import($this->v4Document($english), 'RJ123456', 4);
    }

    public static function malformedV4LocalizedValues(): array
    {
        return [
            'title array' => [['work_name' => ['bad']]],
            'description object-shaped array' => [['description' => ['bad']]],
            'genre scalar' => [['genre' => 'Tag']],
            'genre associative map' => [['genre' => ['name' => 'Tag']]],
            'genre non-string entry' => [['genre' => [null]]],
        ];
    }

    public function test_v4_partial_values_keep_omitted_clear_and_empty_tag_bucket_distinct(): void
    {
        $record = app(WorkArchiveData::class)->import($this->v4Document([
            'work_name' => null,
            'description' => null,
            'genre' => [],
        ]), 'RJ123456', 4);

        $this->assertArrayHasKey('en', $record['titles']);
        $this->assertNull($record['titles']['en']);
        $this->assertArrayHasKey('en', $record['descriptions']);
        $this->assertNull($record['descriptions']['en']);
        $this->assertSame([], $record['tags']['en']);

        $omitted = app(WorkArchiveData::class)->import($this->v4Document([]), 'RJ123456', 4);
        $this->assertArrayNotHasKey('en', $omitted['titles']);
        $this->assertArrayNotHasKey('descriptions', $omitted);
        $this->assertArrayNotHasKey('en', $omitted['tags']);
    }

    private function v4Document(array $english): array
    {
        return [
            'locales' => [
                'ja' => [
                    'product_id' => 'RJ123456',
                    'work_name' => 'Japanese Title',
                    'genre' => ['Japanese Tag'],
                ],
                'en' => [
                    'product_id' => 'RJ123456',
                    ...$english,
                ],
            ],
        ];
    }
}
