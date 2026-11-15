<?php

declare(strict_types=1);

namespace App\Service\Mail\Brevo;

/**
 * A calendar day as an email names it. On the site's civil clock, not the server's UTC, so a decision
 * taken at 00:30 in Paris is not dated the day before; the English month is spelled out because
 * 03/04 reads as March 4 to many English readers.
 */
final class BrevoDate
{
    private const string CIVIL_TIMEZONE = 'Europe/Paris';
    private const array FORMATS = ['fr' => 'd/m/Y', 'en' => 'j F Y'];
    private const string FALLBACK_LOCALE = 'fr';

    public static function format(\DateTimeImmutable $date, string $locale): string
    {
        return $date
            ->setTimezone(new \DateTimeZone(self::CIVIL_TIMEZONE))
            ->format(self::FORMATS[$locale] ?? self::FORMATS[self::FALLBACK_LOCALE]);
    }
}
