<?php

declare(strict_types=1);

namespace App\Service\OAuth\Google;

use App\Service\OAuth\OAuthUserData;

/**
 * A Google identity as both sign-in paths receive it: the web callback's userinfo and the app's ID
 * token carry the same claims (#1133).
 */
final class GoogleUserDataMapper
{
    /**
     * @param array<string, mixed> $claims `sub`, `email`, `email_verified`, `name`, `picture`
     */
    public static function fromClaims(array $claims): OAuthUserData
    {
        $id = $claims['sub'] ?? null;
        $email = $claims['email'] ?? null;
        if (!is_string($id) || $id === '' || !is_string($email) || $email === '') {
            throw new InvalidGoogleIdTokenException('A Google identity needs an id and an email');
        }
        $name = $claims['name'] ?? null;
        $picture = $claims['picture'] ?? null;

        return new OAuthUserData(
            id: $id,
            email: $email,
            username: is_string($name) && $name !== '' ? $name : $email,
            pictureUrl: is_string($picture) ? self::highResolution($picture) : null,
            // Untrusted unless Google says otherwise.
            emailVerified: in_array($claims['email_verified'] ?? false, [true, 'true'], true),
        );
    }

    private static function highResolution(string $pictureUrl): string
    {
        return (string) preg_replace('/=s\d+-c$/', '=s500-c', $pictureUrl);
    }
}
