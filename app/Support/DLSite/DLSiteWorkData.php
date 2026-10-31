<?php

namespace App\Support\DLSite;

use App\Enums\ProductContributorRole;
use App\Enums\ProductFormat;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final readonly class DLSiteWorkData
{
    public function __construct(
        public string $productId,
        public ?string $siteId,
        public ?string $makerId,
        public ?string $workName,
        public ?string $englishWorkName,
        public ?string $ageCategory,
        public array $productFormat,
        public ?string $announceDate,
        public ?string $circle,
        public ?string $publisher,
        public ?string $brand,
        public ?string $description,
        public ?string $englishDescription,
        public ?string $titleName,
        public ?string $workImage,
        public array $japaneseGenres,
        public array $englishGenres,
        public array $sampleImages,
        public array $contributorsByRole,
        /** Null when the rating could not be read, so stored values are kept. */
        public ?DLSiteRating $rating = null,
    ) {}

    public static function fromArray(array $workData, ?string $fallbackProductId = null, ?array $originalWorkData = null): self
    {
        $workData = self::normalizePayload($workData);
        $japanese = is_array($workData['japanese'] ?? null) ? $workData['japanese'] : [];
        $english = is_array($workData['english'] ?? null) ? $workData['english'] : [];
        $productId = self::text($japanese['product_id'] ?? $english['product_id'] ?? $fallbackProductId);

        if ($productId === null) {
            throw new \InvalidArgumentException('DLsite work data is missing a product id.');
        }

        $workName = self::text($japanese['work_name'] ?? null);
        $englishWorkName = self::text($english['work_name'] ?? null);
        $description = self::text($japanese['description'] ?? null);
        $englishDescription = self::text($english['description'] ?? null);
        $circle = self::text($japanese['circle'] ?? null) ?? self::text($english['circle'] ?? null);
        $makerId = self::text($japanese['maker_id'] ?? $english['maker_id'] ?? null);

        // The original maker takes precedence even when the translation has an explicit Circle.
        if ($original = self::originalMaker($originalWorkData ?? [])) {
            $circle = $original['maker_name'];
            $makerId = $original['maker_id'];
        }

        return new self(
            productId: $productId,
            siteId: self::text($japanese['site_id'] ?? null) ?? self::text($english['site_id'] ?? null),
            makerId: $makerId,
            workName: $workName,
            englishWorkName: $englishWorkName === $workName ? null : $englishWorkName,
            ageCategory: self::text(data_get($japanese, 'age_category._name_')),
            productFormat: self::productFormat($japanese, $english),
            announceDate: self::text($japanese['announce_date'] ?? $english['announce_date'] ?? null),
            circle: $circle,
            publisher: self::text($japanese['publisher'] ?? null) ?? self::text($english['publisher'] ?? null),
            brand: self::text($japanese['brand'] ?? null) ?? self::text($english['brand'] ?? null),
            description: $description,
            englishDescription: $englishDescription === $description ? null : $englishDescription,
            titleName: self::text($japanese['title_name'] ?? $english['title_name'] ?? null),
            workImage: self::text($japanese['work_image'] ?? $english['work_image'] ?? null),
            japaneseGenres: self::list($japanese['genre'] ?? []),
            englishGenres: self::list($english['genre'] ?? []),
            sampleImages: self::list($japanese['sample_images'] ?? []),
            contributorsByRole: self::contributorsByRole($japanese, $english, $circle),
            rating: DLSiteRating::fromLocales($japanese, $english),
        );
    }

    public static function originalWorkId(array $workData): ?string
    {
        $japanese = is_array($workData['japanese'] ?? null) ? $workData['japanese'] : [];

        $languageEditions = is_array($japanese['language_editions'] ?? null) ? $japanese['language_editions'] : [];

        foreach ($languageEditions as $edition) {
            if (! is_array($edition) || ($edition['display_order'] ?? null) !== 1) {
                continue;
            }

            $workno = self::text($edition['workno'] ?? null);
            if (Validator::make(
                ['workno' => $workno],
                ['workno' => ['required', 'string', 'max:191', 'regex:/\A(?:RJ|BJ|VJ)\d+\z/']],
            )->passes()) {
                return $workno;
            }
        }

        return self::text(data_get($japanese, 'translation_info.original_workno'));
    }

    /** @return array{maker_id: string, maker_name: string}|null */
    public static function originalMaker(array $workData): ?array
    {
        // Keep the ID and name from the same locale; only RG makers identify circles.
        foreach (['japanese', 'english'] as $language) {
            $id = self::text(data_get($workData, "{$language}.maker_id"));
            $name = self::text(data_get($workData, "{$language}.maker_name"));
            if ($id !== null && preg_match('/^RG[0-9]+$/D', $id) && $name !== null) {
                return ['maker_id' => $id, 'maker_name' => $name];
            }
        }

        return null;
    }

    /**
     * Add canonical aliases without changing explicitly supplied legacy fields.
     * Safe to reuse before archive field-presence checks and DTO construction.
     */
    public static function normalizePayload(array $workData): array
    {
        // Map each locale independently, leaving missing locale data untouched.
        foreach (['japanese', 'english'] as $language) {
            if (! is_array($workData[$language] ?? null)) {
                continue;
            }

            $locale = $workData[$language];
            // The maker ID prefix determines whether the name is a Circle, Publisher, or Brand.
            $makerRole = match (substr(self::text($locale['maker_id'] ?? null) ?? '', 0, 2)) {
                'RG' => 'circle',
                'BG' => 'publisher',
                'VG' => 'brand',
                default => null,
            };

            // Keep an explicitly supplied older field, even when its value is empty.
            if ($makerRole !== null && array_key_exists('maker_name', $locale) && ! array_key_exists($makerRole, $locale)) {
                $locale[$makerRole] = self::text($locale['maker_name']);
            }

            // Fill a missing description from the first nonempty introduction, in priority order.
            if (! array_key_exists('description', $locale)) {
                foreach (['intro_masked', 'intro_s', 'intro'] as $field) {
                    if (! array_key_exists($field, $locale)) {
                        continue;
                    }
                    $locale['description'] = self::text($locale[$field]);
                    if ($locale['description'] !== null) {
                        break;
                    }
                }
            }

            // Use creater for a missing or null Author; preserve an explicitly empty Author list.
            if (
                ! isset($locale['author'])
                && self::list($locale['creater'] ?? []) !== []
            ) {
                $locale['author'] = $locale['creater'];
            }

            // Format supplied dates without adding a date field to partial archive data.
            if (array_key_exists('announce_date', $locale)) {
                $locale['announce_date'] = self::announcementDate($locale['announce_date']);
            }

            $workData[$language] = $locale;
        }

        return $workData;
    }

    private static function announcementDate(mixed $value): ?string
    {
        // Trim the supplied value and treat blank or unusable values as null.
        $value = self::text($value);
        if ($value === null) {
            return null;
        }

        // Remove Japan's offset suffix for storage, keeping the date and time unchanged.
        return Str::replaceEnd('+09:00', '', $value);
    }

    public function autoSeries(): ?string
    {
        return $this->titleName;
    }

    /**
     * @return list<string>
     */
    private static function productFormat(array $japanese, array $english): array
    {
        $japaneseFormats = self::list($japanese['product_format'] ?? []);

        return ProductFormat::normalizeStoredValues(
            $japaneseFormats !== []
                ? $japaneseFormats
                : self::list($english['product_format'] ?? []),
        );
    }

    /**
     * @return array<string, list<string>>
     */
    private static function contributorsByRole(array $japanese, array $english, ?string $circle): array
    {
        $roles = [];

        foreach (ProductContributorRole::cases() as $role) {
            if ($role === ProductContributorRole::Circle) {
                $roles[$role->value] = $circle === null ? [] : [$circle];

                continue;
            }

            $key = $role->dlsiteKey();
            $roles[$role->value] = self::list($japanese[$key] ?? $english[$key] ?? []);
        }

        return $roles;
    }

    /**
     * @return list<string>
     */
    private static function list(mixed $value): array
    {
        return collect(is_array($value) ? $value : [$value])
            ->map(fn(mixed $item): ?string => self::text($item))
            ->filter()
            ->unique(fn(string $item): string => mb_convert_case($item, MB_CASE_FOLD, 'UTF-8'))
            ->values()
            ->all();
    }

    private static function text(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
