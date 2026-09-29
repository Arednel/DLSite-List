<?php

namespace App\Enums;

use App\Support\TagInput;

enum ProductFormat: string
{
    public const CUSTOM_PREFIX = 'custom:';

    public const MAX_CUSTOM_LABEL_LENGTH = 255;

    case Action = 'ACN';
    case Adventure = 'ADV';
    case Quiz = 'QIZ';
    case CgIllustrations = 'ICG';
    case DigitalNovel = 'DNV';
    case Gekiga = 'SCM';
    case ImageMaterials = 'IMT';
    case Manga = 'MNG';
    case Miscellaneous = 'ET3';
    case MiscellaneousGame = 'ETC';
    case Music = 'MUS';
    case MusicAdditional = 'MS2';
    case AudioMaterials = 'AMT';
    case Novel = 'NRE';
    case Publication = 'PBC';
    case Puzzle = 'PZL';
    case RolePlaying = 'RPG';
    case Shooting = 'STG';
    case Simulation = 'SLN';
    case Table = 'TBL';
    case ToolsAccessories = 'TOL';
    case Typing = 'TYP';
    case Video = 'MOV';
    case Animation = 'MV2';
    case VoiceAsmr = 'SOU';
    case VoiceAdditional = 'SND';
    case VoicedComic = 'VCM';
    case Webtoon = 'WBT';

    public function label(?string $locale = null): string
    {
        return __($this->labelKey(), [], $locale);
    }

    private function labelKey(): string
    {
        return match ($this) {
            self::Action => 'Action',
            self::Adventure => 'Adventure',
            self::Quiz => 'Quiz',
            self::CgIllustrations => 'CG + Illustrations',
            self::DigitalNovel => 'Digital Novel',
            self::Gekiga => 'Gekiga',
            self::ImageMaterials => 'Illustration Materials',
            self::Manga => 'Manga',
            self::Miscellaneous => 'Miscellaneous',
            self::MiscellaneousGame => 'Miscellaneous Games',
            self::Music, self::MusicAdditional => 'Music',
            self::AudioMaterials => 'Music Materials',
            self::Novel => 'Novel',
            self::Publication => 'Publication',
            self::Puzzle => 'Puzzle',
            self::RolePlaying => 'Role-playing',
            self::Shooting => 'Shooting',
            self::Simulation => 'Simulation',
            self::Table => 'Table',
            self::ToolsAccessories => 'Tools / Accessories',
            self::Typing => 'Typing',
            self::Video => 'Video',
            self::Animation => 'Animation',
            self::VoiceAsmr => 'Voice / ASMR',
            self::VoiceAdditional => 'Voice',
            self::VoicedComic => 'Voiced Comics',
            self::Webtoon => 'Webtoon',
        };
    }

    private function isAdditional(): bool
    {
        return in_array($this, [
            self::MusicAdditional,
            self::Animation,
            self::VoiceAdditional,
        ], true);
    }

    private static function labelFor(string $value): string
    {
        $value = trim($value);
        $custom = self::customLabel($value);

        if ($custom !== null) {
            return $custom;
        }

        return self::tryFrom($value)?->label() ?? $value;
    }

    /**
     * @param  list<mixed>  $values
     * @return list<string>
     */
    public static function labelsFor(array $values): array
    {
        return array_map(
            self::labelFor(...),
            self::normalizeStoredValues($values),
        );
    }

    /**
     * @param  list<mixed>  $values
     */
    public static function inputValue(array $values): string
    {
        $tokens = [];

        foreach (self::normalizeStoredValues($values) as $index => $value) {
            $tokens[] = self::inputValueForStoredValue($value, $index > 0);
        }

        return TagInput::format($tokens);
    }

    /**
     * Normalize form input into canonical DLsite codes or explicitly namespaced custom values.
     * Labels shared by main/additional formats use list position to choose the intended code.
     *
     * @return list<string>
     */
    public static function normalizeInput(mixed $value): array
    {
        $tokens = is_array($value)
            ? $value
            : TagInput::parse((string) ($value ?? ''));
        $values = [];

        foreach (array_values($tokens) as $index => $token) {
            if (! is_scalar($token)) {
                continue;
            }

            $token = self::inputToken((string) $token);

            if ($token === '') {
                continue;
            }

            $values[] = self::canonicalValueForInput($token, $index > 0)
                ?? self::CUSTOM_PREFIX . $token;
        }

        return self::normalizeStoredValues($values);
    }

    /**
     * Normalize already-stored/portable values without converting unnamespaced unknown strings.
     * Invalid values remain visible so callers such as transfer validation can reject them.
     *
     * @param  list<mixed>  $values
     * @return list<string>
     */
    public static function normalizeStoredValues(array $values): array
    {
        $normalized = [];
        $seen = [];

        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            $key = self::dedupeKey($value);

            if (! isset($seen[$key])) {
                $normalized[] = $value;
                $seen[$key] = true;
            }
        }

        return $normalized;
    }

    /**
     * Normalize DLsite-fetched values and suppress additional formats that mirror
     * the fetched main WorkType. Manual/storage normalization intentionally does
     * not apply this rule.
     *
     * @param  list<mixed>  $values
     * @return list<string>
     */
    public static function normalizeDlsiteValues(array $values): array
    {
        $formats = array_values(array_filter(
            self::normalizeStoredValues($values),
            fn(string $value): bool => self::tryFrom($value) !== null,
        ));
        $present = array_fill_keys($formats, true);

        return array_values(array_filter(
            $formats,
            function (string $value) use ($present): bool {
                $main = self::from($value)->mirroredMain();

                return $main === null || ! isset($present[$main->value]);
            },
        ));
    }

    /**
     * @param  list<mixed>  $values
     * @return list<string>
     */
    public static function customStoredValues(array $values): array
    {
        return array_values(array_filter(
            self::normalizeStoredValues($values),
            fn(string $value): bool => self::customLabel($value) !== null,
        ));
    }

    private function mirroredMain(): ?self
    {
        return match ($this) {
            self::VoiceAdditional => self::VoiceAsmr,
            self::MusicAdditional => self::Music,
            self::Animation => self::Video,
            default => null,
        };
    }

    /** @return list<string> */
    public static function matchingCodesForSearch(string $value): array
    {
        return self::matchingCodes(self::inputToken($value), true);
    }

    public static function isValidStoredValue(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        if (self::tryFrom($value) !== null) {
            return true;
        }

        $custom = self::customLabel($value);

        if (
            $custom === null
            || mb_strlen($custom) > self::MAX_CUSTOM_LABEL_LENGTH
            || str_contains($custom, "\n")
            || str_contains($custom, "\r")
            || str_starts_with($custom, self::CUSTOM_PREFIX)
        ) {
            return false;
        }

        // A custom value may not duplicate a canonical code or localized label.
        return self::matchingCanonicalValues($custom) === [];
    }

    /** @return list<string> */
    private function labels(): array
    {
        return array_values(array_unique([
            $this->label(),
            $this->label('en'),
            $this->label('ja'),
        ]));
    }

    private static function inputValueForStoredValue(string $value, bool $additional): string
    {
        $custom = self::customLabel($value);

        if ($custom !== null) {
            return $custom;
        }

        $format = self::tryFrom($value);

        if ($format === null) {
            return $value;
        }

        $label = $format->label();

        return self::canonicalValueForInput($label, $additional) === $value
            ? $label
            : $value;
    }

    private static function inputToken(string $value): string
    {
        $value = trim($value);

        if (str_starts_with($value, self::CUSTOM_PREFIX)) {
            $value = trim(substr($value, strlen(self::CUSTOM_PREFIX)));
        }

        return $value;
    }

    private static function customLabel(string $value): ?string
    {
        if (! str_starts_with($value, self::CUSTOM_PREFIX)) {
            return null;
        }

        $label = substr($value, strlen(self::CUSTOM_PREFIX));

        return $label !== '' && $label === trim($label) ? $label : null;
    }

    private static function canonicalValueForInput(string $value, bool $additional): ?string
    {
        $matches = self::matchingCanonicalValues($value);

        if ($matches === []) {
            return null;
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        foreach ($matches as $match) {
            $format = self::from($match);

            if ($additional === $format->isAdditional()) {
                return $match;
            }
        }

        return $matches[0];
    }

    /** @return list<string> */
    private static function matchingCanonicalValues(string $value): array
    {
        return self::matchingCodes($value, false);
    }

    /** @return list<string> */
    private static function matchingCodes(string $value, bool $partialLabels): array
    {
        $value = trim($value);

        if ($value === '') {
            return [];
        }

        $exactCode = self::tryFrom(strtoupper($value));

        if ($exactCode !== null) {
            return [$exactCode->value];
        }

        $needle = mb_strtolower($value);
        $matches = [];

        foreach (self::cases() as $format) {
            foreach ($format->labels() as $label) {
                $label = mb_strtolower(trim($label));

                if ($partialLabels ? str_contains($label, $needle) : $label === $needle) {
                    $matches[] = $format->value;
                    break;
                }
            }
        }

        return array_values(array_unique($matches));
    }

    private static function dedupeKey(string $value): string
    {
        $custom = self::customLabel($value);

        return $custom === null
            ? $value
            : self::CUSTOM_PREFIX . mb_convert_case($custom, MB_CASE_FOLD, 'UTF-8');
    }
}
