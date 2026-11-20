<?php

namespace App\Support\DLSite;

use App\Enums\DlsiteLanguage;
use App\Enums\ProductContributorRole;
use App\Enums\ProductFormat;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final readonly class DLSiteWorkData
{
    /**
     * @param array<string, array{title: ?string, description: ?string, genres: list<string>}> $localizedMetadata
     */
    public function __construct(
        public string $productId,
        public ?string $siteId,
        public ?string $makerId,
        public ?string $workName,
        public ?string $ageCategory,
        public array $productFormat,
        public ?string $announceDate,
        public ?string $circle,
        public ?string $publisher,
        public ?string $brand,
        public ?string $description,
        public ?string $titleName,
        public ?string $workImage,
        public array $sampleImages,
        public array $contributorsByRole,
        public DLSiteRating $rating,
        public array $localizedMetadata,
    ) {}

    public static function fromArray(array $workData, ?string $fallbackProductId = null, ?array $originalWorkData = null): self
    {
        $workData = self::normalizePayload($workData);
        $japanese = $workData['locales'][DlsiteLanguage::Japanese->value] ?? [];
        $productId = self::text($japanese['product_id'] ?? null) ?? self::text($fallbackProductId);

        if ($productId === null) {
            throw new \InvalidArgumentException('dlsite.work.missing_product_id');
        }

        $workName = self::text($japanese['work_name'] ?? null);
        $description = self::text($japanese['description'] ?? null);
        $circle = self::text($japanese['circle'] ?? null);
        $makerId = self::text($japanese['maker_id'] ?? null);
        $localizedMetadata = self::localizedMetadata($workData);

        // The original maker takes precedence even when the translation has an explicit Circle.
        if ($original = self::originalMaker($originalWorkData ?? [])) {
            $circle = $original['maker_name'];
            $makerId = $original['maker_id'];
        }

        return new self(
            productId: $productId,
            siteId: self::text($japanese['site_id'] ?? null),
            makerId: $makerId,
            workName: $workName,
            ageCategory: self::text(data_get($japanese, 'age_category._name_')),
            productFormat: ProductFormat::normalizeStoredValues(self::list($japanese['product_format'] ?? [])),
            announceDate: self::text($japanese['announce_date'] ?? null),
            circle: $circle,
            publisher: self::text($japanese['publisher'] ?? null),
            brand: self::text($japanese['brand'] ?? null),
            description: $description,
            titleName: self::text($japanese['title_name'] ?? null),
            workImage: self::text($japanese['work_image'] ?? null),
            sampleImages: self::list($japanese['sample_images'] ?? []),
            contributorsByRole: self::contributorsByRole($japanese, $circle),
            rating: DLSiteRating::fromDistribution($japanese['rate_count_detail'] ?? null),
            localizedMetadata: $localizedMetadata,
        );
    }

    public function titleForLocale(DlsiteLanguage|string $locale): ?string
    {
        $locale = $locale instanceof DlsiteLanguage ? $locale->value : $locale;

        return $this->localizedMetadata[$locale]['title'] ?? null;
    }

    public function descriptionForLocale(DlsiteLanguage|string $locale): ?string
    {
        $locale = $locale instanceof DlsiteLanguage ? $locale->value : $locale;

        return $this->localizedMetadata[$locale]['description'] ?? null;
    }

    /** @return list<string> */
    public function genresForLocale(DlsiteLanguage|string $locale): array
    {
        $locale = $locale instanceof DlsiteLanguage ? $locale->value : $locale;

        return $this->localizedMetadata[$locale]['genres'] ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    public function genresByLocale(): array
    {
        return Arr::map(
            $this->localizedMetadata,
            fn(array $metadata): array => $metadata['genres'],
        );
    }

    public static function originalWorkId(array $workData): ?string
    {
        $workData = self::normalizePayload($workData);
        $japanese = $workData['locales'][DlsiteLanguage::Japanese->value] ?? [];
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
        $workData = self::normalizePayload($workData);
        $japanese = $workData['locales'][DlsiteLanguage::Japanese->value] ?? [];
        $id = self::text($japanese['maker_id'] ?? null);
        $name = self::text($japanese['maker_name'] ?? null);

        if ($id === null || ! preg_match('/^RG[0-9]+$/D', $id) || $name === null) {
            return null;
        }

        return ['maker_id' => $id, 'maker_name' => $name];
    }

    /**
     * Normalize both the current locale-keyed scraper payload and historical japanese/english payloads.
     * Safe to reuse before archive field-presence checks and DTO construction.
     */
    public static function normalizePayload(array $workData): array
    {
        $hasLocaleContainer = is_array($workData['locales'] ?? null);
        $locales = collect($hasLocaleContainer ? $workData['locales'] : [])
            ->mapWithKeys(function (mixed $locale, string|int $localeKey): array {
                $key = (string) $localeKey;
                $normalized = self::normalizeLegacyLocaleKey($key);

                return [$normalized => $locale];
            })
            ->all();

        foreach (['ja' => 'japanese', 'en' => 'english'] as $locale => $section) {
            if (
                is_array($workData[$section] ?? null)
                && ! is_array($locales[$locale] ?? null)
                && (! $hasLocaleContainer || $workData[$section] !== [])
            ) {
                $locales[$locale] = $workData[$section];
            }
        }

        foreach ($locales as $localeKey => $locale) {
            if (! is_array($locale)) {
                unset($locales[$localeKey]);
                continue;
            }

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

            $locales[$localeKey] = $locale;
        }

        $japanese = $locales[DlsiteLanguage::Japanese->value] ?? [];

        if ($japanese !== []) {
            // Canonical metadata is Japanese-only.
            $makerRole = match (substr(self::text($japanese['maker_id'] ?? null) ?? '', 0, 2)) {
                'RG' => 'circle',
                'BG' => 'publisher',
                'VG' => 'brand',
                default => null,
            };

            // Keep an explicitly supplied older field, even when its value is empty.
            if ($makerRole !== null && array_key_exists('maker_name', $japanese) && ! array_key_exists($makerRole, $japanese)) {
                $japanese[$makerRole] = self::text($japanese['maker_name']);
            }

            // Use creater for a missing or null Author; preserve an explicitly empty Author list.
            if (
                ! isset($japanese['author'])
                && self::list($japanese['creater'] ?? []) !== []
            ) {
                $japanese['author'] = $japanese['creater'];
            }

            // Format supplied dates without adding a date field to partial archive data.
            if (array_key_exists('announce_date', $japanese)) {
                $japanese['announce_date'] = self::announcementDate($japanese['announce_date']);
            }

            $locales[DlsiteLanguage::Japanese->value] = $japanese;
        }

        $workData['locales'] = $locales;
        unset($workData['japanese'], $workData['english']);

        return $workData;
    }

    private static function announcementDate(mixed $value): ?string
    {
        $value = self::text($value);
        if ($value === null) {
            return null;
        }

        return Str::replaceEnd('+09:00', '', $value);
    }

    public function autoSeries(): ?string
    {
        return $this->titleName;
    }

    /**
     * @return array<string, array{title: ?string, description: ?string, genres: list<string>}>
     */
    private static function localizedMetadata(array $workData): array
    {
        $metadata = [];
        foreach ($workData['locales'] as $locale => $payload) {
            $language = is_string($locale) ? DlsiteLanguage::tryFrom($locale) : null;
            if ($language === null) {
                continue;
            }

            $title = self::text($payload['work_name'] ?? null);
            $description = self::text($payload['description'] ?? null);

            $metadata[$language->value] = [
                'title' => $title,
                'description' => $description,
                'genres' => self::list($payload['genre'] ?? []),
            ];
        }

        return $metadata;
    }

    /** Historical locale-keyed payload compatibility only. */
    private static function normalizeLegacyLocaleKey(string $locale): string
    {
        return $locale === 'jp'
            ? DlsiteLanguage::Japanese->value
            : $locale;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function contributorsByRole(array $japanese, ?string $circle): array
    {
        $roles = [];

        foreach (ProductContributorRole::cases() as $role) {
            if ($role === ProductContributorRole::Circle) {
                $roles[$role->value] = $circle === null ? [] : [$circle];

                continue;
            }

            $key = $role->dlsiteKey();
            $roles[$role->value] = self::list($japanese[$key] ?? []);
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
            ->filter(fn(?string $item): bool => $item !== null)
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
