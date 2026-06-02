<?php

namespace App\Support\DLSite;

use App\Enums\ProductContributorRole;

final readonly class DLSiteWorkData
{
    public function __construct(
        public string $productId,
        public ?string $makerId,
        public ?string $workName,
        public ?string $englishWorkName,
        public ?string $ageCategory,
        public ?string $circle,
        public ?string $description,
        public ?string $englishDescription,
        public ?string $titleName,
        public array $japaneseGenres,
        public array $englishGenres,
        public array $sampleImages,
        public array $contributorsByRole,
    ) {}

    public static function fromArray(array $workData, ?string $fallbackProductId = null): self
    {
        $japanese = is_array($workData['japanese'] ?? null) ? $workData['japanese'] : [];
        $english = is_array($workData['english'] ?? null) ? $workData['english'] : [];
        $productId = self::text($japanese['product_id'] ?? $english['product_id'] ?? $fallbackProductId);

        if ($productId === null) {
            throw new \InvalidArgumentException('DLSite work data is missing a product id.');
        }

        $workName = self::text($japanese['work_name'] ?? null);
        $englishWorkName = self::text($english['work_name'] ?? null);
        $description = self::text($japanese['description'] ?? null);
        $englishDescription = self::text($english['description'] ?? null);

        return new self(
            productId: $productId,
            makerId: self::text($japanese['maker_id'] ?? $english['maker_id'] ?? null),
            workName: $workName,
            englishWorkName: $englishWorkName === $workName ? null : $englishWorkName,
            ageCategory: self::text(data_get($japanese, 'age_category._name_')),
            circle: self::text($japanese['circle'] ?? $english['circle'] ?? null),
            description: $description,
            englishDescription: $englishDescription === $description ? null : $englishDescription,
            titleName: self::text($japanese['title_name'] ?? $english['title_name'] ?? null),
            japaneseGenres: self::list($japanese['genre'] ?? []),
            englishGenres: self::list($english['genre'] ?? []),
            sampleImages: self::list($japanese['sample_images'] ?? []),
            contributorsByRole: self::contributorsByRole($japanese, $english),
        );
    }

    public function autoSeries(): ?string
    {
        return $this->titleName;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function contributorsByRole(array $japanese, array $english): array
    {
        $roles = [];

        foreach (ProductContributorRole::cases() as $role) {
            if ($role === ProductContributorRole::Circle) {
                $circle = self::text($japanese['circle'] ?? $english['circle'] ?? null);
                $roles[$role->value] = $circle === null ? [] : [$circle];

                continue;
            }

            $roles[$role->value] = self::list($japanese[$role->dlsiteKey()] ?? $english[$role->dlsiteKey()] ?? []);
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
