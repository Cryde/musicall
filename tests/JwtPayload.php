<?php

declare(strict_types=1);

namespace App\Tests;

/**
 * Reads the claims out of a JWT without verifying it.
 *
 * Only tests use this, and only to assert on what was minted. The part worth writing once is the
 * alphabet: a JWT segment is base64url, so `-` and `_` stand where `+` and `/` would, and almost
 * every real token contains at least one of them. Feed it to base64_decode() untranslated and strict
 * mode returns false while lax mode quietly drops the offending characters, either way producing
 * something that is not the payload. The missing padding needs no attention, base64_decode() handles
 * an unpadded string at every length.
 */
final class JwtPayload
{
    /**
     * @return array<string, mixed> the claims, or an empty array when the value is not a JWT, so a
     *                              failure shows up as a missing claim in the assertion's diff
     */
    public static function of(string $jwt): array
    {
        $segments = explode('.', $jwt);
        if (count($segments) !== 3) {
            return [];
        }

        $claims = json_decode((string) base64_decode(strtr($segments[1], '-_', '+/'), true), true);

        return is_array($claims) ? $claims : [];
    }
}
