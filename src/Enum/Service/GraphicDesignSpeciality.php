<?php

declare(strict_types=1);

namespace App\Enum\Service;

use Symfony\Contracts\Translation\TranslatorInterface;

/** What a graphic designer makes (#1412), the kind's search filter. */
enum GraphicDesignSpeciality: string implements ServiceSpeciality
{
    /** « Pochette »: a record's cover. */
    case Cover = 'cover';
    case Logo = 'logo';
    /** « Affiche et flyer ». */
    case Poster = 'poster';
    case Merch = 'merch';
    /** « Identité visuelle ». */
    case VisualIdentity = 'visual_identity';
    /** « Visuels pour les réseaux ». */
    case SocialMedia = 'social_media';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $speciality): string => $speciality->value, self::cases());
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans(match ($this) {
            self::Cover => 'service_speciality.graphic_design.cover',
            self::Logo => 'service_speciality.graphic_design.logo',
            self::Poster => 'service_speciality.graphic_design.poster',
            self::Merch => 'service_speciality.graphic_design.merch',
            self::VisualIdentity => 'service_speciality.graphic_design.visual_identity',
            self::SocialMedia => 'service_speciality.graphic_design.social_media',
        }, [], 'reference', $locale);
    }
}
