<?php

declare(strict_types=1);

namespace App\Enum\Service;

use Symfony\Contracts\Translation\TranslatorInterface;

/** What a webmaster builds (#1412), the kind's search filter. */
enum WebSpeciality: string implements ServiceSpeciality
{
    /** « Site de groupe ou d'artiste ». */
    case BandSite = 'band_site';
    /** « Dossier de presse en ligne (EPK) ». */
    case Epk = 'epk';
    /** « Boutique en ligne ». */
    case OnlineStore = 'online_store';
    /** « Refonte et maintenance ». */
    case RedesignMaintenance = 'redesign_maintenance';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $speciality): string => $speciality->value, self::cases());
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans(match ($this) {
            self::BandSite => 'service_speciality.web.band_site',
            self::Epk => 'service_speciality.web.epk',
            self::OnlineStore => 'service_speciality.web.online_store',
            self::RedesignMaintenance => 'service_speciality.web.redesign_maintenance',
        }, [], 'reference', $locale);
    }
}
