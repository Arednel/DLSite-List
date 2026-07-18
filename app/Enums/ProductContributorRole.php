<?php

namespace App\Enums;

enum ProductContributorRole: string
{
    case Circle = 'circle';
    case Scenario = 'scenario';
    case VoiceActor = 'voice_actor';
    case Illustration = 'illustration';
    case Author = 'author';

    public function label(): string
    {
        return match ($this) {
            self::Circle => __('Circle'),
            self::Scenario => __('Scenario Author'),
            self::VoiceActor => __('Voice Actor'),
            self::Illustration => __('Illustration Author'),
            self::Author => __('Author'),
        };
    }

    public function dlsiteKey(): string
    {
        return match ($this) {
            self::Circle => 'circle',
            self::Scenario => 'scenario',
            self::VoiceActor => 'voice_actor',
            self::Illustration => 'illustration',
            self::Author => 'author',
        };
    }

    public function productField(): ProductField
    {
        return match ($this) {
            self::Circle => ProductField::Circle,
            self::Scenario => ProductField::Scenario,
            self::VoiceActor => ProductField::VoiceActor,
            self::Illustration => ProductField::Illustration,
            self::Author => ProductField::Author,
        };
    }
}
