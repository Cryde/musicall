<?php declare(strict_types=1);

namespace App\Privacy;

/**
 * Turns an email address into something recognisable by the person who typed it and useless to
 * everybody else: "john.doe@gmail.com" becomes "j***@gmail.com".
 *
 * The asterisks are a fixed run rather than one per hidden character, so the mask does not even
 * leak how long the local part was. Anything that is not a plain "local@domain" pair is replaced
 * whole, because a value we cannot parse is a value we cannot safely show a fragment of.
 */
final class EmailMask
{
    private const string MASK = '***';

    public static function mask(string $email): string
    {
        // Last "@" wins: it is the one separating the domain, so a local part that itself contains
        // an "@" stays entirely hidden instead of spilling into the visible half.
        $separatorPosition = mb_strrpos($email, '@');
        if ($separatorPosition === false) {
            return self::MASK;
        }

        $localPart = mb_substr($email, 0, $separatorPosition);
        $domain = mb_substr($email, $separatorPosition + 1);

        if ($localPart === '' || $domain === '') {
            return self::MASK;
        }

        return mb_substr($localPart, 0, 1) . self::MASK . '@' . $domain;
    }
}
