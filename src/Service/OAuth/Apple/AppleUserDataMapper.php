<?php

declare(strict_types=1);

namespace App\Service\OAuth\Apple;

use App\Service\OAuth\OAuthUserData;
use App\Service\User\DisplayNameRules;

/**
 * An Apple identity (#1269). The token carries no name: Apple hands it to the app on the very first
 * sign-in only, unsigned, so it arrives beside the token and is held to the profile name rules.
 */
final class AppleUserDataMapper
{
    private const int DISPLAY_NAME_MAX_LENGTH = 100;

    /**
     * @param array<string, mixed> $claims `sub`, `email`, `email_verified`
     */
    public static function fromClaims(array $claims, ?string $givenName, ?string $familyName): OAuthUserData
    {
        $id = $claims['sub'] ?? null;
        $email = $claims['email'] ?? null;
        if (!is_string($id) || $id === '' || !is_string($email) || $email === '') {
            throw new InvalidAppleIdentityTokenException('An Apple identity needs an id and an email');
        }
        $displayName = self::displayName($givenName, $familyName);

        return new OAuthUserData(
            id: $id,
            email: $email,
            // The local part rather than the whole address, which for a private relay address would
            // make a username out of `privaterelayappleidcom`.
            username: $displayName ?? (strstr($email, '@', true) ?: $email),
            pictureUrl: null,
            // Untrusted unless Apple says otherwise. A private relay address is verified like any other.
            emailVerified: in_array($claims['email_verified'] ?? false, [true, 'true'], true),
            displayName: $displayName,
        );
    }

    /** Null rather than a refusal: a name the profile would not accept just leaves the account without one. */
    private static function displayName(?string $givenName, ?string $familyName): ?string
    {
        $name = DisplayNameRules::normalize(implode(' ', array_filter([$givenName, $familyName], is_string(...))));
        if ($name === null || mb_strlen($name) > self::DISPLAY_NAME_MAX_LENGTH || DisplayNameRules::violation($name) !== null) {
            return null;
        }

        return $name;
    }
}
