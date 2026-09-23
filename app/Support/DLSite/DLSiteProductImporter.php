<?php

namespace App\Support\DLSite;

use App\Enums\ProductContributorRole;
use App\Enums\ProductField;
use App\Models\Genre;
use App\Models\Product;
use App\Support\ProductContributorSync;
use App\Support\ProductGenreSync;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class DLSiteProductImporter
{
    public function __construct(
        private readonly DLSiteWorkFetcher $workFetcher,
        private readonly ProductGenreSync $genreSync,
        private readonly ProductContributorSync $contributorSync,
    ) {}

    /** The optional completion callback runs inside the product creation transaction. */
    public function import(
        string $workId,
        DLSiteProductImportInput $input,
        ?Closure $onCreated = null,
    ): DLSiteProductImportResult {
        try {
            $fetchResult = $this->workFetcher->fetch(
                $workId,
                Storage::disk('local')->path("Works/{$workId}.json"),
                Storage::disk('public')->path("Works/{$workId}"),
            );
        } catch (RuntimeException $exception) {
            throw DLSiteProductImportException::fromFetchFailure($exception);
        }

        $workData = $fetchResult->workData;
        $productId = $workData->productId;
        [$workName, $englishWorkName] = $this->titleValues($input, $workData);
        $circle = $this->textOverride(
            $input,
            ProductField::Circle,
            'circle',
            $workData->circle,
        );
        $makerId = $this->textOverride(
            $input,
            ProductField::Circle,
            'maker_id',
            $workData->makerId,
        );
        [$description, $englishDescription] = $this->descriptionValues($input, $workData);
        $contributorsByRole = $this->contributorsByRole($input, $workData, $circle);
        $customGenres = $input->fieldVisible(ProductField::Tags)
            ? (array) $input->value('genre_custom', [])
            : [];

        $product = DB::transaction(function () use (
            $input,
            $workData,
            $productId,
            $workName,
            $englishWorkName,
            $circle,
            $makerId,
            $description,
            $englishDescription,
            $contributorsByRole,
            $customGenres,
            $onCreated,
            $fetchResult,
        ): Product {
            $product = Product::query()->createOrFirst(
                ['id' => $productId],
                [
                    'maker_id' => $makerId,
                    'work_name' => $workName,
                    'work_name_english' => $englishWorkName,
                    'age_category' => $this->textOverride(
                        $input,
                        ProductField::AgeCategory,
                        'age_category',
                        $workData->ageCategory,
                    ),
                    'circle' => $circle,
                    'work_image' => "storage/Works/{$productId}/cover.jpg",
                    'description' => $description,
                    'description_english' => $englishDescription,
                    'notes' => $input->fieldSubmitted(ProductField::Notes, 'notes')
                        ? $input->value('notes')
                        : null,
                    'series' => $this->seriesValue($input, $workData),
                    'sample_images' => Collection::times(
                        count($workData->sampleImages),
                        fn(int $position): string => "storage/Works/{$productId}/sample_{$position}.jpg",
                    )->all(),
                    'score' => $input->fieldSubmitted(ProductField::Score, 'score')
                        ? $input->value('score')
                        : null,
                    'progress' => $input->fieldSubmitted(ProductField::Progress, 'progress')
                        ? $input->value('progress')
                        : null,
                    'start_date' => $input->fieldSubmitted(ProductField::StartDate, 'add.start_date')
                        ? $input->value('start_date')
                        : null,
                    'end_date' => $input->fieldSubmitted(ProductField::FinishDate, 'add.finish_date')
                        ? $input->value('end_date')
                        : null,
                    'num_re_listen_times' => $input->fieldSubmitted(ProductField::TotalTimesReListened, 'add.num_re_listen_times')
                        ? $input->value('num_re_listen_times')
                        : null,
                    're_listen_value' => $input->fieldSubmitted(ProductField::ReListenValue, 'add.re_listen_value')
                        ? $input->value('re_listen_value')
                        : null,
                    'priority' => $input->fieldSubmitted(ProductField::Priority, 'add.priority')
                        ? $input->value('priority')
                        : null,
                ],
            );

            if (! $product->wasRecentlyCreated) {
                throw new DLSiteProductAlreadyExistsException;
            }

            $this->genreSync->sync($product, [
                Genre::LANGUAGE_JAPANESE => Genre::resolveIdsFromTitles($workData->japaneseGenres),
                Genre::LANGUAGE_ENGLISH => Genre::resolveIdsFromTitles($workData->englishGenres),
            ], Genre::resolveIdsFromTitles($customGenres));

            $this->contributorSync->sync($product, $contributorsByRole, $makerId);

            // Bulk Import records completion in the same transaction as product creation.
            $onCreated?->__invoke($fetchResult->imageFailureMessage());

            return $product;
        });

        return new DLSiteProductImportResult(
            product: $product,
            warning: $fetchResult->imageFailureMessage(),
        );
    }

    /** @return array{0: string, 1: ?string} */
    private function titleValues(DLSiteProductImportInput $input, DLSiteWorkData $workData): array
    {
        $workName = $this->textOverride(
            $input,
            ProductField::Title,
            'work_name',
            $workData->workName,
        );
        $englishWorkName = $this->textOverride(
            $input,
            ProductField::Title,
            'work_name_english',
            $workData->englishWorkName,
        );

        return [$workName, $englishWorkName === $workName ? null : $englishWorkName];
    }

    private function textOverride(
        DLSiteProductImportInput $input,
        ProductField $field,
        string $key,
        ?string $default,
    ): ?string {
        if (! $input->fieldSubmitted($field, $key)) {
            return $default;
        }

        $value = $input->value($key);

        return filled($value) ? (string) $value : $default;
    }

    private function seriesValue(DLSiteProductImportInput $input, DLSiteWorkData $workData): ?string
    {
        $series = $input->value('series');

        if ($input->fieldSubmitted(ProductField::Series, 'series') && filled($series)) {
            return (string) $series;
        }

        return $input->autoSeriesFromTitleName ? $workData->autoSeries() : null;
    }

    /** @return array{0: ?string, 1: ?string} */
    private function descriptionValues(DLSiteProductImportInput $input, DLSiteWorkData $workData): array
    {
        $description = $this->textOverride(
            $input,
            ProductField::DescriptionJapanese,
            'description',
            $workData->description,
        );
        $englishDescription = $this->textOverride(
            $input,
            ProductField::DescriptionEnglish,
            'description_english',
            $workData->englishDescription,
        );

        return [$description, $englishDescription === $description ? null : $englishDescription];
    }

    /** @return array<string, list<string>> */
    private function contributorsByRole(
        DLSiteProductImportInput $input,
        DLSiteWorkData $workData,
        ?string $circle,
    ): array {
        $contributors = $workData->contributorsByRole;
        $contributors[ProductContributorRole::Circle->value] = filled($circle) ? [$circle] : [];

        foreach (ProductContributorRole::cases() as $role) {
            if ($role === ProductContributorRole::Circle) {
                continue;
            }

            $values = (array) $input->value($role->value, []);

            if ($input->fieldVisible($role->productField()) && $values !== []) {
                $contributors[$role->value] = array_values($values);
            }
        }

        return $contributors;
    }
}
