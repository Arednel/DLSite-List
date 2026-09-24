<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;

enum ContentFocus: string
{
    use ProvidesOptions;

    case General = 'general';
    case Listening = 'listening';
    case Reading = 'reading';
    case Games = 'games';
    case Video = 'video';
    case Music = 'music';
    case Artwork = 'artwork';

    public function label(): string
    {
        return match ($this) {
            self::General => __('General'),
            self::Listening => __('Listening (Voice / ASMR / Voice Dramas)'),
            self::Reading => __('Reading (Manga / Comics / Light Novels / Novels / Books)'),
            self::Games => __('Games (Games, PC Games)'),
            self::Video => __('Video (Anime / Videos)'),
            self::Music => __('Music (Music)'),
            self::Artwork => __('Artwork (CG)'),
        };
    }
}
