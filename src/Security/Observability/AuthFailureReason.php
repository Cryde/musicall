<?php

declare(strict_types=1);

namespace App\Security\Observability;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * An allowlist rather than `getMessageKey()` passed through: Lexik's `UserNotFoundException` builds
 * its key out of the identity it failed to load, so verbatim would put usernames in Sentry.
 */
final class AuthFailureReason
{
    /**
     * Message keys that are fixed strings, carrying nothing about the account they refused.
     */
    private const array SAFE_MESSAGE_KEYS = [
        // Lexik
        'Expired JWT Token',
        'Invalid JWT Token',
        'JWT Token not found',
        // Gesdinet
        'Invalid JWT Refresh Token',
        'Missing JWT Refresh Token',
        'JWT Refresh Token Not Found',
        'Too many refresh requests, please try again later.',
    ];

    public function fromException(?AuthenticationException $exception): ?string
    {
        if (!$exception instanceof AuthenticationException) {
            return null;
        }

        $messageKey = $exception->getMessageKey();

        if (in_array($messageKey, self::SAFE_MESSAGE_KEYS, true)) {
            return $messageKey;
        }

        // Anything unrecognised is reported by type, which says what happened without quoting a
        // message that may have been built around an identity.
        $parts = explode('\\', $exception::class);

        return end($parts);
    }
}
