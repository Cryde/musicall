<?php

declare(strict_types=1);

namespace App\Service\Mail\Brevo;

/** Every email has a French Brevo template, so a locale without its own template gets that one. */
final class BrevoTemplateId
{
    private const string FALLBACK_LOCALE = 'fr';

    /**
     * @param array<string, string> $idsByLocale
     */
    public static function forLocale(array $idsByLocale, string $locale): string
    {
        return $idsByLocale[$locale] ?? $idsByLocale[self::FALLBACK_LOCALE];
    }
}
